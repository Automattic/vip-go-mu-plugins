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
 * Should Cron Control load
 */
if ( ! wpcom_vip_use_core_cron() ) {
	require_once __DIR__ . '/cron-control/cron-control.php';
}
