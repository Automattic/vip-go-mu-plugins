<?php

namespace Automattic\VIP\Config;

class Sync {
	private static $instance;

	const CRON_EVENT_NAME        = 'vip_config_sync_cron';
	const CRON_INTERVAL_NAME     = 'vip_config_sync_cron_interval';
	const CRON_INTERVAL          = 5 * \MINUTE_IN_SECONDS;
	const MAX_SYNCS_PER_INTERVAL = 15;
	const LOG_FEATURE_NAME       = 'vip_config_sync';

	const JETPACK_PRIVACY_SETTINGS_SYNC_STATUS_OPTION_NAME = 'vip_config_jetpack_privacy_settings_synced_value';

	/**
	 * List of blog IDs that will require syncs as soon as possible due to recent changes.
	 * @var int[]
	 */
	private $blogs_to_sync = [];

	public static function instance() {
		if ( ! ( static::$instance instanceof Sync ) ) {
			static::$instance = new Sync();
			static::$instance->init();
		}

		return static::$instance;
	}

	public function init() {
		$this->maybe_setup_cron();
		$this->init_listeners();
	}

	public function init_listeners() {
		add_action( 'update_option_siteurl', array( $this, 'queue_sync_for_blog' ) );
		add_action( 'update_option_home', [ $this, 'queue_sync_for_blog' ] );

		add_action( 'shutdown', [ $this, 'run_sync_checks' ], PHP_INT_MAX );
		// TODO should we also intercept deleted sites with add_action( 'wp_delete_site'...)?
	}

	public function queue_sync_for_blog() {
		$blog_id = get_current_blog_id();

		// Save the current blog id for wider support, such as option updates from within the network admin.
		if ( ! in_array( $blog_id, $this->blogs_to_sync ) ) {
			array_push( $this->blogs_to_sync, $blog_id );
		}
	}

	public function run_sync_checks() {
		// Sync the current blog if we need to.
		$this->maybe_sync_current_blog();

		// For any remaining blogs that need syncing due to changes in this request, we'll update their option to let them know.
		// We avoid syncing right away as switch_to_blog() is not reliable for gaining the full state (plugins, etc).
		$original_blog_id = get_current_blog_id();
		foreach ( $this->blogs_to_sync as $blog_id ) {
			if ( $blog_id === $original_blog_id ) {
				// We've already handled the current blog.
				continue;
			}

			switch_to_blog( $blog_id );
			update_option( 'vip_config_needs_sync', true, false );
			restore_current_blog();
		}
	}

	private function maybe_sync_current_blog() {
		$needs_sync = false;

		// Check if the current request changed important data on this blog.
		$blog_had_changes = false !== array_search( get_current_blog_id(), $this->blogs_to_sync );
		if ( $blog_had_changes ) {
			$needs_sync = true;
		}

		// Check if another request changed important data on this blog.
		if ( get_option( 'vip_config_needs_sync', false ) ) {
			$deleted = delete_option( 'vip_config_needs_sync' );
			// Protection from race conditions, only 1 request will be able to delete from the database.
			if ( $deleted ) {
				$needs_sync = true;
			}
		}

		// Check if we should perform a catch-up sync, helping if cron is backed up or broken.
		$sync_data = get_option( 'vip_config_sync_data', [] );
		if ( isset( $sync_data['last_sync'] ) ) {
			$seconds_elapsed = time() - $sync_data['last_sync'];

			if ( $seconds_elapsed > ( self::CRON_INTERVAL * 10 ) ) {
				// Protection from race conditions. Ensures only 1 request will run the catch-up sync.
				// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
				if ( wp_cache_add( 'sync_catchup_concurrency_lock', 'locked', 'vip_config', self::CRON_INTERVAL ) ) {
					$needs_sync = true;
				}
			}
		}

		if ( $needs_sync && ! $this->is_sync_ratelimited() ) {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}

			$this->put_site_details();
		}
	}

	private function is_sync_ratelimited(): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_add( 'sync_ratelimit', 0, 'vip_config', self::CRON_INTERVAL );
		$count = wp_cache_incr( 'sync_ratelimit', 1, 'vip_config' );

		return (int) $count > self::MAX_SYNCS_PER_INTERVAL;
	}

	public function maybe_setup_cron() {
		add_filter( 'cron_schedules', [ $this, 'filter_cron_schedules' ] ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::CRON_EVENT_NAME, [ $this, 'do_cron' ] );

		// Only register cron event from admin or CLI, to keep it out of frontend request path
		$is_cli = defined( 'WP_CLI' ) && WP_CLI;

		if ( ! is_admin() && ! $is_cli ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL_NAME, self::CRON_EVENT_NAME );
		}
	}

	/**
	 * Filter `cron_schedules` output
	 *
	 * Add the custom interval to WP cron schedule
	 *
	 * @param array $schedule
	 *
	 * @return mixed
	 */
	public function filter_cron_schedules( $schedule ) {
		if ( isset( $schedule[ self::CRON_INTERVAL_NAME ] ) ) {
			return $schedule;
		}

		$schedule[ self::CRON_INTERVAL_NAME ] = [
			'interval' => self::CRON_INTERVAL,
			'display'  => __( 'Custom interval for VIP Config Sync. Currently set to 5 minutes' ),
		];

		return $schedule;
	}

	public function do_cron() {
		$this->maybe_sync_jetpack_privacy_settings();
		$this->put_site_details();
	}

	public function maybe_sync_jetpack_privacy_settings() {
		if ( ! class_exists( '\\Automattic\\Jetpack\\Sync\\Actions' ) ) {
			$this->log( 'error', '\\Automattic\\Jetpack\\Sync\\Actions class not found when syncing settings - unabled to sync' );

			return false;
		}

		// If sync is not available (not connected, dev mode, etc), skip entirely
		if ( ! \Automattic\Jetpack\Sync\Actions::sync_allowed() ) {
			return false;
		}

		// NOTE - we default to the string `'false'` here so that sites that aren't using privacy features won't cause an initial sync when the option
		// is not present - the default `'false'` is returned which matches the expected value if the VIP_JETPACK_IS_PRIVATE constant is not set to `true`
		$value_from_option = get_option( self::JETPACK_PRIVACY_SETTINGS_SYNC_STATUS_OPTION_NAME, 'false' );

		$enabled = \Automattic\VIP\Security\Private_Sites::is_jetpack_private();

		$expected = $enabled ? 'true' : 'false';

		if ( $value_from_option !== $expected ) {
			$this->sync_jetpack_privacy_settings( $expected, $value_from_option );
		}
	}

	public function sync_jetpack_privacy_settings( $expected_value, $value_from_option ) {
		$modules_to_sync = array(
			'options'   => true,
			'functions' => true,
		);

		$success = \Automattic\Jetpack\Sync\Actions::do_full_sync( $modules_to_sync );

		if ( $success ) {
			// Started new full sync, so we can update our local option value. This isn't the _most_ reliable...in the future perhaps we
			// can dynamically check to see if anything is out of sync here
			update_option( self::JETPACK_PRIVACY_SETTINGS_SYNC_STATUS_OPTION_NAME, $expected_value, false );

			$this->log( 'info', 'Inconsistent privacy settings detected, scheduled new sync', array(
				'expected'    => $expected_value,
				'from_option' => $value_from_option,
			) );
		} else {
			$this->log( 'error', 'There was an error scheduling a sync of private site settings', array(
				'result' => $success,
			) );
		}

		return $success;
	}

	public function put_site_details() {
		require_once __DIR__ . '/class-site-details-index.php';

		Site_Details_Index::instance()->put_site_details();

		$option              = get_option( 'vip_config_sync_data', [] );
		$option['last_sync'] = time();
		update_option( 'vip_config_sync_data', $option, false );
	}

	public function log( $severity, $message, $extra = array() ) {
		\Automattic\VIP\Logstash\log2logstash( array(
			'severity' => $severity,
			'feature'  => self::LOG_FEATURE_NAME,
			'message'  => $message,
			'extra'    => $extra,
		) );
	}
}
