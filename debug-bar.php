<?php
/*
Plugin Name: Debug Bar
Plugin URI: https://wordpress.org/plugins/debug-bar/
Description: Adds a debug menu to the admin bar that shows query, cache, and other helpful debugging information.
Author: wordpressdotorg
Version: 1.1.3
Author URI: https://wordpress.org/
Text Domain: debug-bar
*/

// We only need to load the files if it's enabled
add_action( 'set_current_user', function() {
	$enable = apply_filters( 'debug_bar_enable', false );

	if ( ! $enable ) {
		return;
	}

	trigger_error( 'Debug Bar will no longer be included in VIP MU Plugins as of January 31, 2023. Use Query Monitor instead, see https://lobby.vip.wordpress.com/2022/12/14/deprecation-notice-debug-bar-january-31-2023/.', E_USER_WARNING );

	if ( ! class_exists( 'Debug_Bar' ) ) {
		require_once __DIR__ . '/debug-bar/debug-bar.php';
	}

	// Load additional plugins
	if ( ! class_exists( 'ZT_Debug_Bar_Cron' ) ) {
		require_once __DIR__ . '/debug-bar-cron/debug-bar-cron.php';
	}

	// Setup extra panels
	add_filter( 'debug_bar_panels', function( $panels ) {
		require_once __DIR__ . '/vip-helpers/vip-debug-bar-panels.php';
		require_once __DIR__ . '/debug-bar/panels/class-debug-bar-elasticsearch.php';
		require_once __DIR__ . '/debug-bar/panels/class-debug-bar-apc-cache-interceptor.php';


		$total = count( $panels );

		for ( $i = 0; $i < $total; $i++ ) {
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
		$panels[] = new Debug_Bar_Elasticsearch();
		$panels[] = new WPCOM_Debug_Bar_Apcu_Hotcache();

		return $panels;
	}, 5 );
}, 1 ); // Priority must be lower than that of Query Monitor
