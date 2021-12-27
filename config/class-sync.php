<?php

namespace Automattic\VIP\Config;

class Sync {
	private static $instance;

	const CRON_EVENT_NAME    = 'vip_config_sync_cron';
	const CRON_INTERVAL_NAME = 'vip_config_sync_cron_interval';
	const CRON_INTERVAL      = 5 * \MINUTE_IN_SECONDS;
	const LOG_FEATURE_NAME   = 'vip_config_sync';

	const JETPACK_PRIVACY_SETTINGS_SYNC_STATUS_OPTION_NAME = 'vip_config_jetpack_privacy_settings_synced_value';

	public static function instance() {
		if ( ! ( static::$instance instanceof Sync ) ) {
			static::$instance = new Sync();
			static::$instance->init();
		}

		return static::$instance;
	}

	public function init() {
		$this->maybe_setup_cron();
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
		} else {
			$this->log( 'info', 'Privacy Settings were determined to be consistent', array(
				'expected'    => $expected,
				'from_option' => $value_from_option,
			) );
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
