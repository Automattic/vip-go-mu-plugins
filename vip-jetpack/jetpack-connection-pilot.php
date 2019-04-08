<?php

class WPCOM_VIP_JETPACK_CONNECTION_PILOT {

	/**
	 * Cron action that runs the connection pilot checks.
	 */
	const CRON_ACTION = 'wpcom_vip_run_jetpack_connection_pilot';

	/**
	 * The schedule the cron job runs on. Update in 000-vip-init.php as well.
	 */
	const CRON_SCHEDULE = 'hourly';

	/**
	 * Initiate an instance of this class if one doesn't exist already.
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new WPCOM_VIP_JETPACK_CONNECTION_PILOT;
		}

		return $instance;
	}

	/**
	 * Class constructor.
	 * Ensures the cron job is set up correctly and ready.
	 */
	private function __construct() {
		if ( ! self::should_run_connection_pilot() ) {
			return;
		}

		// Avoid the overhead on frontend requests.
		if ( is_admin() && ! wp_doing_ajax() ) {
			self::maybe_update_cron_schedule();
		}
	}

	/**
	 * The main cron job callback.
	 */
	public static function run_cron_check() {
		if ( ! self::should_run_connection_pilot() ) {
			return;
		}

		error_log( 'Running Jetpack Connection Pilot' );
	}

	/**
	 * Ensures the connection pilot should run.
	 *
	 * Will only run if we are on a live VIP environment,
	 * or if specifically told otherwise via a special constant.
	 * 
	 * @return bool True if the connection pilot should run.
	 */
	private static function should_run_connection_pilot() {
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) {
			return true;
		}

		if ( defined( 'WPCOM_VIP_RUN_CONNECTION_PILOT_LOCALLY' ) && WPCOM_VIP_RUN_CONNECTION_PILOT_LOCALLY ) {
			return true;
		}

		return false;
	}

	/**
	 * Sanity checks on the cron job. Ensure it is set up and with the right schedule.
	 */
	private function maybe_update_cron_schedule() {
		// Ensure the internal cron job has been added. Should already exist as an internal Cron Control job.
		if ( ! has_action( self::CRON_ACTION ) ) {
			add_action( self::CRON_ACTION, array( __CLASS__, 'run_cron_check' ) );

			if ( ! wp_next_scheduled( self::CRON_ACTION ) ) {
				wp_schedule_event( time(), self::CRON_SCHEDULE, self::CRON_ACTION );
			}
		}

		// Next, check that the schedule is correct. If not, update it.
		$event = wp_get_scheduled_event( self::CRON_ACTION );
		if ( is_object( $event ) && self::CRON_SCHEDULE !== $event->schedule ) {
			// Cron Control is picky about adding/updating existing events, so just going to remove and add back with new schedule.
			wp_clear_scheduled_hook( self::CRON_ACTION );
			wp_schedule_event( $event->timestamp, self::CRON_SCHEDULE, self::CRON_ACTION );
		}
	}
}

WPCOM_VIP_JETPACK_CONNECTION_PILOT::init();
