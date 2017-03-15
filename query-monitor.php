<?php
/*
Plugin Name: Query Monitor
Description: Monitoring of database queries, hooks, conditionals and more (including VIP Go tweaks).
Version:     2.13.4
Plugin URI:  https://querymonitor.com/
Author:      John Blackbourn
Author URI:  https://johnblackbourn.com/
Text Domain: query-monitor
Domain Path: /languages/
License:     GPL v2 or later

*/

/**
 * Determines if Query Monitor should be enabled. We don't
 * want to load it if we don't have to.
 *
 * * If a QM_COOKIE is detected, Query monitor is enabled
 * * If the WPCOM_VIP_QM_ENABLE constant is true, Query Monitor is enabled
 *
 * Note that we have to set the value for QM_COOKIE here,
 * in order to detect it.
 *
 * Note that we cannot use is_automattician this early, as
 * the user has not yet been set.
 *
 * @param $enable
 *
 * @return bool
 */
function wpcom_vip_qm_enable( $enable ) {

	if ( ! defined( 'QM_COOKIE' ) ) {
		define( 'QM_COOKIE', 'query_monitor_' . COOKIEHASH );
	}

	if ( current_user_can( 'view_query_monitor' ) ) {
		return true;
	}
	if ( isset( $_COOKIE[QM_COOKIE] ) ) {
		return true;
	}

	return $enable;
}
add_filter( 'wpcom_vip_qm_enable', 'wpcom_vip_qm_enable' );

/**
 * Require the plugin files for Query Monitor, faking a
 * plugin activation, if it's the first time.
 */
function wpcom_vip_qm_require() {
	/**
	 * Filter whether Query Monitor is activated; return true,
	 * if QM should be activated.
	 *
	 * @param bool $enable False by default
	 */
	if ( ! apply_filters( 'wpcom_vip_qm_enable', false ) ) {
		return;
	}

	if ( ! defined( 'SAVEQUERIES' ) ) {
		define( 'SAVEQUERIES', true );
	}

	// For hyperdb, which doesn't use SAVEQUERIES
	global $wpdb;
	$wpdb->save_queries = SAVEQUERIES;

	$wpcom_vip_qm_file = __DIR__ . '/query-monitor/query-monitor.php';

	require_once( $wpcom_vip_qm_file );
	require_once( __DIR__ . '/vip-helpers/vip-query-monitor.php' );

	// Because we're including Query Monitor as an MU plugin, we need to
	// manually call the `activate` method on `activation`.
	if ( 0 === get_option( 'wpcom_vip_qm_activated', 0 ) ) {
		QueryMonitor::init( $wpcom_vip_qm_file )->activate( true );
		update_option( 'wpcom_vip_qm_activated', 1, true );
	}

	// We don't want to share our environment information via Query Monitor
	remove_filter( 'qm/collectors', 'register_qm_collector_environment', 20, 2 );

	// We know we haven't got the QM DB drop-in in place, so don't show the message
	add_filter( 'qm/show_extended_query_prompt', '__return_false' );

	if ( function_exists( 'wpcom_vip_save_query_callback' ) ) {
		add_filter('qm/collectors', function (array $collectors, QueryMonitor $qm) {
			$collectors['db_queries'] = new WPCOM_VIP_QM_Collector_DB_Queries();

			return $collectors;
		}, 99, 2);
	}

}
add_action( 'plugins_loaded', 'wpcom_vip_qm_require', 1 );

/**
 * Hooks the wp action to avoid showing Query Monitor on 404 pages
 * to non-logged in users, as it is likely to get caught in the
 * Varnish cache.
 */
function wpcom_vip_qm_disable_on_404() {
	if ( is_404() && ! is_user_logged_in() && isset( $_COOKIE[ QM_COOKIE ] ) ) {
		add_filter( "qm/dispatch/ajax", '__return_false' );
		add_filter( "qm/dispatch/html", '__return_false' );
	}
}
add_action( 'wp', 'wpcom_vip_qm_disable_on_404' );

