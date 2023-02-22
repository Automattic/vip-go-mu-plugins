<?php

namespace Automattic\VIP\Config;

use Automattic\VIP\Utils\Context;

class Sync {
	private static $instance;

	const CRON_EVENT_NAME        = 'vip_config_sync_cron';
	const CRON_INTERVAL_NAME     = 'vip_config_sync_cron_interval';
	const CRON_INTERVAL          = 5 * \MINUTE_IN_SECONDS;
	const MAX_SYNCS_PER_INTERVAL = 15;
	const LOG_FEATURE_NAME       = 'vip_config_sync';

	const JETPACK_PRIVACY_SETTINGS_SYNC_STATUS_OPTION_NAME = 'vip_config_jetpack_privacy_settings_synced_value';

	// The maximum amount of blogs we want to register immediant syncs for.
	const BLOGS_TO_SYNC_LIMIT = 10;

	// List of blog IDs that need a sync, capped by BLOGS_TO_SYNC_LIMIT.
	private $blogs_to_sync = [];
	private $original_blog_id;

	public static function instance() {
		if ( ! ( static::$instance instanceof Sync ) ) {
			static::$instance = new Sync();
			static::$instance->init();
		}

		return static::$instance;
	}

	public function init() {
		// Saving the initial blog_id in the init to be extra sure nobody changed it later on via a switch_to_blog().
		if ( is_null( $this->original_blog_id ) ) {
			$this->original_blog_id = get_current_blog_id();
		}

		$this->maybe_setup_cron();
		$this->init_listeners();
	}

	public function init_listeners() {
		add_action( 'update_option_siteurl', [ $this, 'queue_sync_for_blog' ] );
		add_action( 'update_option_home', [ $this, 'queue_sync_for_blog' ] );

		add_action( 'shutdown', [ $this, 'run_sync_checks' ], PHP_INT_MAX );
		// TODO should we also intercept deleted sites with add_action( 'wp_delete_site'...)?
	}

	public function maybe_setup_cron() {
		add_filter( 'cron_schedules', [ $this, 'filter_cron_schedules' ] ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::CRON_EVENT_NAME, [ $this, 'do_cron' ] );

		// Only register cron event from admin or CLI, to keep it out of frontend request path
		if ( ! is_admin() && ! Context::is_wp_cli() ) {
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

	public function get_blogs_to_sync() {
		return $this->blogs_to_sync;
	}

	public function queue_sync_for_blog() {
		// Queue the sync only from admin or CLI, to keep it out of frontend request path
		if ( ! is_admin() && ! Context::is_wp_cli() ) {
			return;
		}
		$blog_id = get_current_blog_id();

		// To avoid performance issues, don't add if the count would surpass BLOGS_TO_SYNC_LIMIT.
		// Can rely on the default cron schedules to sync the rest.
		if ( count( $this->blogs_to_sync ) >= self::BLOGS_TO_SYNC_LIMIT ) {
			return;
		}

		if ( ! in_array( $blog_id, $this->blogs_to_sync ) ) {
			array_push( $this->blogs_to_sync, $blog_id );
		}
	}

	public function run_sync_checks() {
		if ( 0 === count( $this->blogs_to_sync ) ) {
			return;
		}

		$needs_sync = false !== array_search( $this->original_blog_id, $this->blogs_to_sync );

		if ( $needs_sync && ! $this->is_sync_ratelimited() ) {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}

			$this->put_site_details();
		}

		foreach ( $this->blogs_to_sync as $blog_id ) {
			if ( $blog_id !== $this->original_blog_id ) {
				switch_to_blog( $blog_id );

				// Schedule a cron event to sync the blog data asap.
				// Avoid syncing in this request as switch_to_blog() is not reliable for gaining the full state (plugins, etc).
				if ( ! wp_next_scheduled( self::CRON_EVENT_NAME, [ 'is_faster_cron' => true ] ) ) {
					wp_schedule_single_event( time() + 1, self::CRON_EVENT_NAME, [ 'is_faster_cron' => true ] );
				}

				restore_current_blog();
			}
		}
	}

	public function do_cron( $is_faster_cron = false ) {
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

	private function is_sync_ratelimited(): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_add( 'sync_ratelimit', 0, 'vip_config', self::CRON_INTERVAL );
		$count = wp_cache_incr( 'sync_ratelimit', 1, 'vip_config' );

		return (int) $count > self::MAX_SYNCS_PER_INTERVAL;
	}


	public function put_site_details() {
		require_once __DIR__ . '/class-site-details-index.php';

		Site_Details_Index::instance()->put_site_details();
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
