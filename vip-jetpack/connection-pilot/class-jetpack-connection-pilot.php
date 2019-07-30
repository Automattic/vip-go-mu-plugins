<?php

require_once __DIR__ . '/class-jetpack-connection-controls.php';
require_once __DIR__ . '/class-jetpack-connection-status-check.php';

/**
 * The Pilot is in control of setting up the cron job for monitoring JP connections and sending out alerts if anything is wrong.
 * Will only run if the `WPCOM_VIP_RUN_CONNECTION_PILOT` constant is defined and set to true.
 */
class WPCOM_VIP_Jetpack_Connection_Pilot {

	/**
	 * Cron action that runs the connection pilot checks.
	 */
	const CRON_ACTION = 'wpcom_vip_run_jetpack_connection_pilot';

	/**
	 * The schedule the cron job runs on. Update in 000-vip-init.php as well.
	 *
	 * Schedule changes can take up to 24 hours to take effect.
	 * See the a8c_cron_control_clean_legacy_data event for more details.
	 */
	const CRON_SCHEDULE = 'hourly';

	/**
	 * Initiate an instance of this class if one doesn't exist already.
	 */
	public static function init() {
		if ( ! self::should_run_connection_pilot() ) {
			return;
		}

		// Ensure the internal cron job has been added. Should already exist as an internal Cron Control job.
		add_action( self::CRON_ACTION, array( __CLASS__, 'run_connection_pilot' ) );

		if ( ! wp_next_scheduled( self::CRON_ACTION ) ) {
			wp_schedule_event( strtotime( sprintf( '+%d minutes', mt_rand( 1, 60 ) ) ), self::CRON_SCHEDULE, self::CRON_ACTION );
		}
	}

	/**
	 * The main cron job callback.
	 * Checks the JP connection and alerts/auto-resolves when there are problems.
	 *
	 * Needs to be static due to how it is added to cron control.
	 */
	public static function run_connection_pilot() {
		if ( ! self::should_run_connection_pilot() ) {
			return;
		}

		$status_check = new WPCOM_VIP_Jetpack_Connection_Status_Check();
		$status_check->launch();

		// Send out notifications.
		if ( is_array( $status_check->pilot_notifications ) ) {
			foreach ( $status_check->pilot_notifications as $notification ) {
				self::send_alert( $notification['message'], $notification['error'], $notification['healthcheck'] );
			}
		}
	}

	/**
	 * Send an alert to IRC and Slack.
	 *
	 * Example message:
	 * Jetpack is disconnected, but was previously connected under the same domain.
	 * Site: example.go-vip.co (ID 123). The last known connection was on August 25, 12:11:14 UTC to Cache ID 65432 (example.go-vip.co).
	 * Jetpack connection error: [jp-cxn-pilot-not-active] Jetpack is not currently active.
	 *
	 * @param string   $message optional.
	 * @param WP_Error $wp_error optional.
	 * @param array    $last_healthcheck optional.
	 *
	 * @return mixed True if the message was sent to IRC, false if it failed. If sandboxed, will just return the message string.
	 */
	private static function send_alert( $message = '', $wp_error = null, $last_healthcheck = null ) {
		$message .= sprintf( ' Site: %s (ID %d).', get_site_url(), defined( 'VIP_GO_APP_ID' ) ? VIP_GO_APP_ID : 0 );

		if ( isset( $last_healthcheck['site_url'], $last_healthcheck['cache_site_id'], $last_healthcheck['last_healthcheck'] ) ) {
			$message .= sprintf(
				' The last known connection was on %s UTC to Cache Site ID %d (%s).',
				date( 'F j, H:i', $last_healthcheck['last_healthcheck'] ), $last_healthcheck['cache_site_id'], $last_healthcheck['site_url']
			);
		}

		if ( is_wp_error( $wp_error ) ) {
			$message .= sprintf( ' Jetpack connection error: [%s] %s', $wp_error->get_error_code(), $wp_error->get_error_message() );
		}

		if ( ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) || ( ! defined( 'ALERT_SERVICE_ADDRESS' ) ) ) {
			error_log( $message );

			return $message; // Just return the message, as posting to IRC won't work.
		}

		return wpcom_vip_irc( '#vip-jp-cxn-monitoring', $message );
	}

	/**
	 * Checks if the connection pilot should run.
	 *
	 * @return bool True if the connection pilot should run.
	 */
		if ( defined( 'WPCOM_VIP_RUN_CONNECTION_PILOT' ) && true === WPCOM_VIP_RUN_CONNECTION_PILOT ) {
			return true;
		}

		return false;
	public static function should_run_connection_pilot() {
	}
}

WPCOM_VIP_Jetpack_Connection_Pilot::init();
