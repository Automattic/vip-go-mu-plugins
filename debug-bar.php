<?php
/*
 Plugin Name: Debug Bar
 Plugin URI: http://wordpress.org/extend/plugins/debug-bar/
 Description: Adds a debug menu to the admin bar that shows query, cache, and other helpful debugging information.
 Author: wordpressdotorg
 Version: 0.8.2
 Author URI: http://wordpress.org/
 */
add_filter( 'debug_bar_enable', function( $enable ) {
	$enable = is_automattician();

	return $enable;
}, 99 );

// We only need to load the files if it's enabled
add_action( 'init', function() {
	$enable = apply_filters( 'debug_bar_enable', false );

	if ( ! $enable ) {
		return;
	}

	if ( ! defined( 'SAVEQUERIES' ) ) {
		define('SAVEQUERIES', true);
	}

	// For hyperdb, which doesn't use SAVEQUERIES
	global $wpdb;

	$wpdb->save_queries        = true;
	$wpdb->save_backtrace      = true;
	$wpdb->save_query_callback = 'wpcom_vip_save_query_callback';

	require_once( __DIR__ . '/debug-bar/debug-bar.php' );

	// Setup extra panels
	add_filter( 'debug_bar_panels', function( $panels ) {
		require_once( __DIR__ . '/vip-helpers/vip-debug-bar-panels.php' );

		$total = count( $panels );

		for ( $i = 0; $i < $total; $i++) {
			if ( $panels[ $i ] instanceof Debug_Bar_Queries ) {
				$panels[ $i ] = new WPCOM_VIP_Debug_Bar_Queries();
			} elseif ( $panels[ $i ] instanceof Debug_Bar_PHP ) {
				$panels[ $i ] = new WPCOM_VIP_Debug_Bar_PHP();
			}
		}

		$panels[] = new WPCOM_VIP_Debug_Bar_Query_Summary();
		$panels[] = new WPCOM_VIP_Debug_Bar_DB_Connections();
		$panels[] = new WPCOM_VIP_Debug_Bar_Remote_Requests();

		return $panels;
	}, 99);
}, 1 ); // Priority must be lower than that of Query Monitor
