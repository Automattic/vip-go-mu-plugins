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
	if ( true === A8C_PROXIED_REQUEST ) {
		return true;
	}
	return $enable;
}, 99 );

add_action( 'send_headers', function() {
	if ( true === A8C_PROXIED_REQUEST ) {
		header( 'Cache-Control: private' );
	}
});

// We only need to load the files if it's enabled
add_action( 'init', function() {
	$enable = apply_filters( 'debug_bar_enable', false );

	if ( ! $enable ) {
		return;
	}

	require_once( __DIR__ . '/debug-bar/debug-bar.php' );

	// Load additional plugins
	if ( ! class_exists( 'ZT_Debug_Bar_Cron' ) ) {
		require_once( __DIR__ . '/debug-bar-cron/debug-bar-cron.php' );
	}

	// Setup extra panels
	add_filter( 'debug_bar_panels', function( $panels ) {
		require_once( __DIR__ . '/vip-helpers/vip-debug-bar-panels.php' );

		$total = count( $panels );

		for ( $i = 0; $i < $total; $i++) {
			if ( $panels[ $i ] instanceof Debug_Bar_Queries && function_exists( 'wpcom_vip_save_query_callback' ) ) {
				$panels[ $i ] = new WPCOM_VIP_Debug_Bar_Queries();
			} elseif ( $panels[ $i ] instanceof Debug_Bar_Object_Cache ) {
				$panels[ $i ] = new WPCOM_VIP_Debug_Bar_Memcached();
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
