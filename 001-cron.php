<?php
/*
Plugin Name: Cron Control
Plugin URI:
Description: Execute WordPress cron events in parallel, using a custom post type for event storage.
Author: Erick Hitter, Automattic
Version: 3.1
Text Domain: automattic-cron-control
*/

if ( file_exists( __DIR__ . '/cron/cron.php' ) ) {
	require_once __DIR__ . '/cron/cron.php';
}

/**
 * Determine if we should load cron control, which disables core WP cron running by default.
 *
 * @return bool True if we should not load cron control.
 */
function wpcom_vip_use_core_cron() {
	// Do not load outside of VIP environments, unless explicitly requested
	if ( false === WPCOM_IS_VIP_ENV && ( ! defined( 'WPCOM_VIP_LOAD_CRON_CONTROL_LOCALLY' ) || ! WPCOM_VIP_LOAD_CRON_CONTROL_LOCALLY ) ) {
		return true;
	}

	return false;
}

/**
 * Ensure sites don't block the Cron Control endpoints
 *
 * Cron Control handles authentication itself
 */
function wpcom_vip_permit_cron_control_rest_access( $allowed ) {
	if ( ! class_exists( '\Automattic\WP\Cron_Control\REST_API' ) ) {
		return $allowed;
	}

	$base_path      = '/' . rest_get_url_prefix() . '/' . \Automattic\WP\Cron_Control\REST_API::API_NAMESPACE . '/';
	$request_uri    = $_SERVER['REQUEST_URI'] ?? '';        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only used in comparison
	$request_method = $_SERVER['REQUEST_METHOD'] ?? '';     // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only used in comparison

	if ( 0 === strpos( $request_uri, $base_path . \Automattic\WP\Cron_Control\REST_API::ENDPOINT_LIST ) && 'POST' === $request_method ) {
		return true;
	}

	if ( 0 === strpos( $request_uri, $base_path . \Automattic\WP\Cron_Control\REST_API::ENDPOINT_RUN ) && 'PUT' === $request_method ) {
		return true;
	}

	return $allowed;
}

/**
 * Don't trigger Jetpack Sync's shutdown actions for cron requests
 *
 * Cron runs sync itself, and running sync on shutdown slows the endpoint response, sometimes beyond the 10-second timeout
 */
function wpcom_vip_disable_jetpack_sync_on_cron_shutdown( $load_sync ) {
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return false;
	}

	return $load_sync;
}

/**
 * Log details of fatal error in callback that Cron Control caught
 *
 * @param $event object
 * @param $error \Throwable
 */
function wpcom_vip_log_cron_control_event_for_caught_error( $event, $error ) {
	$message = sprintf( 'PHP Fatal error:  Caught Error: %1$s in %2$s:%3$d%4$sStack trace:%4$s# %5$s%4$s%6$s',
		$error->getMessage(),
		$error->getFile(),
		$error->getLine(),
		PHP_EOL,
		wpcom_vip_cron_control_event_object_to_string( $event ),
		$error->getTraceAsString()
	);
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $message );
}

/**
 * Convert event object to log entry
 *
 * @param $event object
 */
function wpcom_vip_log_cron_control_event_object( $event ) {
	$message  = 'Cron Control Uncaught Error - ';
	$message .= wpcom_vip_cron_control_event_object_to_string( $event );
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $message );
}

/**
 * Convert event object to string suitable for logging
 *
 * @param $event object
 * @return string
 */
function wpcom_vip_cron_control_event_object_to_string( $event ) {
	return sprintf( 'ID: %1$d | timestamp: %2$s | action: %3$s | action_hashed: %4$s | instance: %5$s | home: %6$s', $event->ID, $event->timestamp, $event->action, $event->action_hashed, $event->instance, home_url( '/' ) );
}

/**
 * Should Cron Control load
 */
if ( ! wpcom_vip_use_core_cron() ) {
	/**
	 * Prevent plugins/themes from blocking access to our routes
	 */
	add_filter( 'rest_authentication_errors', 'wpcom_vip_permit_cron_control_rest_access', 999 ); // hook in late to bypass any others that override our auth requirements

	/**
	 * Don't trigger Jetpack Sync on shutdown for cron requests
	 */
	add_filter( 'jetpack_sync_sender_should_load', 'wpcom_vip_disable_jetpack_sync_on_cron_shutdown' );

	/**
	 * Log details of events that fail
	 */
	add_action( 'a8c_cron_control_event_threw_catchable_error', 'wpcom_vip_log_cron_control_event_for_caught_error', 10, 2 );
	add_action( 'a8c_cron_control_freeing_event_locks_after_uncaught_error', 'wpcom_vip_log_cron_control_event_object' );

	require_once __DIR__ . '/cron-control/cron-control.php';
}
