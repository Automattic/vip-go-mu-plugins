<?php
/*
 Plugin Name: Cron Control
 Plugin URI:
 Description: Execute WordPress cron events in parallel, using a custom post type for event storage.
 Author: Erick Hitter, Automattic
 Version: 1.5
 Text Domain: automattic-cron-control
 */

/**
 * Determine if Cron Control is called for
 *
 * Inactive multisite subsites, sites using Basic Auth, and local environments are generally unavailable
 *
 * @return bool
 */
function wpcom_vip_use_core_cron() {
	// Do not load outside of VIP environments, unless explicitly requested
	if ( false === WPCOM_IS_VIP_ENV && ( ! defined( 'WPCOM_VIP_LOAD_CRON_CONTROL_LOCALLY' ) || ! WPCOM_VIP_LOAD_CRON_CONTROL_LOCALLY ) ) {
		return true;
	}

	// Basic Auth sites are unreachable
	if ( defined( 'WPCOM_VIP_BASIC_AUTH' ) && WPCOM_VIP_BASIC_AUTH ) {
		define( 'ALTERNATE_WP_CRON', true );
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

	$base_path = '/' . rest_get_url_prefix() . '/' . \Automattic\WP\Cron_Control\REST_API::API_NAMESPACE . '/';

	if ( 0 === strpos( $_SERVER['REQUEST_URI'], $base_path . \Automattic\WP\Cron_Control\REST_API::ENDPOINT_LIST ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		return true;
	}

	if ( 0 === strpos( $_SERVER['REQUEST_URI'], $base_path . \Automattic\WP\Cron_Control\REST_API::ENDPOINT_RUN ) && 'PUT' === $_SERVER['REQUEST_METHOD'] ) {
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
	$message = sprintf( 'PHP Fatal error:  Caught Error: %1$s in %2$s:%3$d', $error->getMessage(), $error->getFile(), $error->getLine() );
	error_log( $message );

	wpcom_vip_log_event_object( $event, 'Caught' );
}

/**
 * Convert event object to log entry
 *
 * @param $event object
 * @param $type string
 */
function wpcom_vip_log_event_object( $event, $type = 'Uncaught' ) {
	$message = sprintf( 'PHP Fatal error:  %1$s Error: Cron Control event failed - ID: %2$d | timestamp: %3$s | action: %4$s | action_hashed: %5$s | instance: %6$s | home: %7$s', $type, $event->ID, $event->timestamp, $event->action, $event->action_hashed, $event->instance, home_url( '/' ) );
	error_log( $message );
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
	add_action( 'a8c_cron_control_freeing_event_locks_after_uncaught_error', 'wpcom_vip_log_event_object' );

	require_once __DIR__ . '/cron-control/cron-control.php';
}
