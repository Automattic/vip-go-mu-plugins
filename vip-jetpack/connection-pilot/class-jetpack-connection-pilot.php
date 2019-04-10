<?php

class WPCOM_VIP_Jetpack_Connection_Pilot {

	/**
	 * Cron action that runs the connection pilot checks.
	 */
	const CRON_ACTION = 'wpcom_vip_run_jetpack_connection_pilot';

	/**
	 * The schedule the cron job runs on. Update in 000-vip-init.php as well.
	 */
	const CRON_SCHEDULE = 'hourly';

	/**
	 * The option name used for keeping track of successful connection checks.
	 */
	const HEALTHCHECK_OPTION = 'vip_jetpack_connection_pilot_healthcheck';

	/**
	 * Initiate an instance of this class if one doesn't exist already.
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new WPCOM_VIP_Jetpack_Connection_Pilot;
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
	 * Checks the JP connection and alerts/auto-resolves when there are problems.
	 * 
	 * Needs to be static due to how it is added to cron control.
	 */
	public static function run_cron_check() {
		if ( ! self::should_run_connection_pilot() ) {
			return;
		}

		$connection_test = WPCOM_VIP_Jetpack_Connection_Controls::jetpack_is_connected();
		if ( true === $connection_test ) {
			// Everything checks out. Update the healthcheck option and move one.
			return update_option( self::HEALTHCHECK_OPTION, array(
				'site_url'         => get_site_url(),
				'cache_site_id'    => (int) Jetpack_Options::get_option( 'id' ),
				'last_healthcheck' => time(),
			), false );
		}

		// Something is wrong. Let's handle it.
		return self::handle_connection_issues( $connection_test );
	}

	/**
	* The connection checks failed and returned a WP_Error.
	* Here we will try to reconnect when possible, else send out alerts.
	*
	* @param WP_Error object
	*/
	private static function handle_connection_issues( $wp_error ) {
		if ( ! is_wp_error( $wp_error ) ) {
			// Not currently possible, but future-proofing just in case.
			return self::send_alert( 'Jetpack is disconnected, unknown error.' );
		}

		// 1) It is connected but not under the right account.
		if ( 'jp-cxn-pilot-not-vip-owned' === $wp_error->get_error_code() ) {
			// 1.1 🔆
			return self::send_alert( 'Jetpack is connected to a non-VIP account.', $wp_error );
		}

		$last_healthcheck = get_option( self::HEALTHCHECK_OPTION );
		$current_site_url = get_site_url();

		// 2) Check the last healthcheck to see if the URLs match.
		if ( ! empty( $last_healthcheck['site_url'] ) ) {
			if ( $last_healthcheck['site_url'] === $current_site_url ) {
				// 2.1 ✅
				return self::send_alert( 'Jetpack is disconnected, but was previously connected under the same domain.', $wp_error );
			} else {
				// 2.2 🔆
				return self::send_alert( 'Jetpack is disconnected, and it appears the domain has changed.', $wp_error );
			}
		}

		// 3) The healthcheck option doesn’t exist. Either it's a new site, or an unkown connection error.
		$site_parsed = wp_parse_url( $current_site_url );
		if ( wp_endswith( $site_parsed['host'], '.go-vip.co' ) || wp_endswith( $site_parsed['host'], '.go-vip.net' ) ) {
			// 3.1 A ✅
			// return self::send_alert( 'Jetpack is disconnected, though it appears this is a new site.', $wp_error );
			return true; // Leaving this alert disabled for now.
		}

		// TODO: Add this option when a new multi-site is created.
		if ( is_multisite() && get_option( 'vip_jetpack_connection_pilot_new_site' ) ) {
			// 3.1 B ✅
			return self::send_alert( 'Jetpack is disconnected, though it appears this is a new site on a MS network.', $wp_error );
		}

		// 3.2 🔴
		return self::send_alert( 'Jetpack is disconnected.', $wp_error );
	}

	/**
	 * Send an alert to IRC and Slack.
	 * 
	 * Example message:
	 * Jetpack is disconnected, but was previously connected under the same domain.
	 * Site: example.go-vip.co (ID 123). The last known connection was on August 25, 12:11:14 UTC to Cache ID 65432 (example.go-vip.co).
	 * Jetpack connection error: jp-cxn-pilot-not-active.
	 *
	 * @param string $message optional
	 * @param WP_Error optional
	 * 
	 * @return mixed True if the message was sent to IRC, false if it failed. If sandboxed, will just return the message string.
	 */
	private static function send_alert( $message = '', $error = null ) {
		$message .= sprintf( ' Site: %s (ID %d).', get_site_url(), defined( 'VIP_GO_APP_ID' ) ? VIP_GO_APP_ID : 0 );

		$last_healthcheck = get_option( self::HEALTHCHECK_OPTION );
		if ( isset( $last_healthcheck['site_url'], $last_healthcheck['cache_site_id'], $last_healthcheck['last_healthcheck'] ) ) {
			$message .= sprintf(
				' The last known connection was on %s UTC to Cache ID %d (%s).',
				date( 'F j, H:i', $last_healthcheck['last_healthcheck'] ), $last_healthcheck['cache_site_id'], $last_healthcheck['site_url']
			);
		}

		if ( is_wp_error( $error ) ) {
			$message .= sprintf( ' Jetpack connection error: %s.', $error->get_error_code() );
		}

		if ( ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) || ( ! defined( 'ALERT_SERVICE_ADDRESS' ) ) ) {
			return $message; // Just return the message, as posting to IRC won't work.
		}

		return wpcom_vip_irc( '#vip-jp-cxn-monitoring', $message );
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

WPCOM_VIP_Jetpack_Connection_Pilot::init();
