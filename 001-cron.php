<?php
/*
Plugin Name: Cron Control
Plugin URI:
Description: Execute WordPress cron events in parallel, using a custom post type for event storage.
Author: Erick Hitter, Automattic
Version: 2.0
Text Domain: automattic-cron-control
*/

if ( file_exists( __DIR__ . '/cron/cron.php' ) ) {
	require_once __DIR__ . '/cron/cron.php';
}

/**
 * Determine if Cron Control is called for
 *
 * Inactive multisite subsites and local environments are generally unavailable
 *
 * @return bool
 */
function wpcom_vip_use_core_cron() {
	// Do not load outside of VIP environments, unless explicitly requested
	if ( false === WPCOM_IS_VIP_ENV && ( ! defined( 'WPCOM_VIP_LOAD_CRON_CONTROL_LOCALLY' ) || ! WPCOM_VIP_LOAD_CRON_CONTROL_LOCALLY ) ) {
		return true;
	}

	// Bail early for anything else that isn't a multisite subsite
	if ( ! is_multisite() || is_main_site() ) {
		return false;
	}

	$details = get_blog_details( get_current_blog_id(), false );

	// get_blog_details() uses numeric strings for backcompat
	if ( in_array( '1', array( $details->archived, $details->spam, $details->deleted ), true ) ) {
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
 * Callback for 'a8c_cron_control_uncacheable_cron_option' action. Send an alert to IRC and Slack in case of cron option being too big.
 *
 * @param $event object
 */
function wpcom_vip_log_cron_control_uncacheable_cron_option( $option_size, $buckets, $option_flat_count ) {
	$message = sprintf( 'Cron Control Cron Option Uncacheable Alert - home: %s | option size: %d | buckets: %d | option flat count: %d', home_url( '/' ), $option_size, $buckets, $option_flat_count );
	wpcom_vip_irc( 'vip-go-criticals', $message, 2, 'cache-control-uncacheable-cron-option', 900 );
}

/**
 * Should Cron Control load
 */
if ( ! wpcom_vip_use_core_cron() ) {
	/**
	 * Don't skip empty events, as it causes them to be rescheduled infinitely
	 *
	 * Functionality will be fixed or removed, but this stops the runaway event creation in the meantime
	 */
	add_filter( 'a8c_cron_control_run_event_with_no_callbacks', '__return_true' );

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
	add_action( 'a8c_cron_control_uncacheable_cron_option', 'wpcom_vip_log_cron_control_uncacheable_cron_option', 10, 3 );

	$cron_control_next_version = __DIR__ . '/cron-control-next/cron-control.php';

	if ( defined( 'VIP_CRON_CONTROL_USE_NEXT_VERSION' ) && true === VIP_CRON_CONTROL_USE_NEXT_VERSION && file_exists( $cron_control_next_version ) ) {
		// Use latest version for testing
		require_once $cron_control_next_version;
	} else {
		// Use regular version
		require_once __DIR__ . '/cron-control/cron-control.php';
	}
}
