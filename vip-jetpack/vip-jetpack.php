<?php

/*
 * Plugin Name: Jetpack: VIP Specific Changes
 * Plugin URI: https://github.com/Automattic/vipv2-mu-plugins/blob/master/jetpack-mandatory.php
 * Description: VIP-specific customisations to Jetpack.
 * Author: Automattic
 * Version: 1.0.2
 * License: GPL2+
 */

/**
 * Enable VIP modules required as part of the platform
 */
require_once( __DIR__ . '/jetpack-mandatory.php' );

/**
 * Remove certain modules from the list of those that can be activated
 * Blocks access to certain functionality that isn't compatible with the platform.
 */
add_filter( 'jetpack_get_available_modules', function( $modules ) {
	unset( $modules['photon'] );
	unset( $modules['site-icon'] );
	unset( $modules['protect'] );

	return $modules;
}, 999 );

// Prevent Jetpack version ping-pong when a sandbox has an old version of stacks
if ( true === WPCOM_SANDBOXED ) {
	add_action( 'updating_jetpack_version', function() {
		wp_die( '😱😱😱 Oh no! Looks like your sandbox is trying to change the version of Jetpack. This is probably not a good idea. As a precaution, we\'re killing this request to prevent this from happening (this === 💥💥💥). Please run `vip stacks update` on your sandbox before doing anything else.', 400 );
	}, 0 ); // No need to wait till priority 10 since we're going to die anyway
}

function wpcom_vip_did_jetpack_search_query( $query ) {
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		return;
	}

	global $wp_elasticsearch_queries_log;

	if ( ! isset( $wp_elasticsearch_queries_log ) || ! is_array( $wp_elasticsearch_queries_log ) ) {
		$wp_elasticsearch_queries_log = array();
	}

	$query['backtrace'] = wp_debug_backtrace_summary();

	$wp_elasticsearch_queries_log[] = $query;
}

add_action( 'did_jetpack_search_query', 'wpcom_vip_did_jetpack_search_query' );

/**
 * Decide when Jetpack's Sync Listener should be loaded.
 *
 * Sync Listener looks for events that need to be added to the sync queue. On
 * many requests, such as frontend views, we wouldn't expect there to be any DB
 * writes so there should be nothing for Jetpack to listen for.
 *
 * @param  bool $should_load Current value.
 * @return bool              Whether (true) or not (false) Listener should load.
 */
function wpcom_vip_jetpack_sync_listener_should_load( $should_load ) {

	// Don't run listener when we're on the frontend, not running cron and dealing
	// with a GET request as opposed to POST/PUT etc.
	if (
		! is_admin() &&
		! DOING_CRON &&
		( isset( $_SERVER["REQUEST_METHOD"] ) && 'GET' === $_SERVER['REQUEST_METHOD'] )
	) {
		$should_load = false;
	}

	return $should_load;

}
add_filter( 'jetpack_sync_listener_should_load', 'wpcom_vip_jetpack_sync_listener_should_load' );
