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
 * Inactive multisite subsites can't be reached by our cron runners, so should use Core's native approach
 *
 * @return bool
 */
function wpcom_vip_use_core_cron() {
	// Bail early for anything that isn't a multisite subsite
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
	if ( 0 === strpos( $_SERVER['REQUEST_URI'], '/wp-json/cron-control/v1/events' ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		return true;
	}

	if ( 0 === strpos( $_SERVER['REQUEST_URI'], '/wp-json/cron-control/v1/event' ) && 'PUT' === $_SERVER['REQUEST_METHOD'] ) {
		return true;
	}

	return $allowed;
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

	require_once __DIR__ . '/cron-control/cron-control.php';
}
