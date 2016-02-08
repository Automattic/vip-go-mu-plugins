<?php
/*
Plugin Name: Query Monitor
Description: Monitoring of database queries, hooks, conditionals and more.
Version:     2.8.1
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
function wpcom_vip_qm_enable() {

	if ( ! defined( 'QM_COOKIE' ) ) {
		$siteurl = get_site_option( 'siteurl' );
		if ( $siteurl ) {
			$cookiehash = md5( $siteurl );
		} else {
			$cookiehash = '';
		}
		define( 'QM_COOKIE', 'query_monitor_' . $cookiehash );
	}

	if ( defined( 'WPCOM_VIP_QM_ENABLE' ) && WPCOM_VIP_QM_ENABLE ) {
		return true;
	}
	if ( isset( $_COOKIE[QM_COOKIE] ) ) {
		return true;
	}

	return false;
}

/**
 * Require the plugin files for Query Monitor, faking a
 * plugin activation, if it's the first time.
 */
function wpcom_vip_qm_require() {
	if ( ! wpcom_vip_qm_enable() ) {
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

	// Because we're including Query Monitor as an MU plugin, we need to
	// manually call the `activate` method on `activation`.
	if ( 0 === get_option( 'wpcom_vip_qm_activated', 0 ) ) {
		QueryMonitor::init( $wpcom_vip_qm_file )->activate( true );
		update_option( 'wpcom_vip_qm_activated', 1, true );
	}

	// We don't want to share our environment information via Query Monitor
	remove_filter( 'qm/collectors', 'register_qm_collector_environment', 20, 2 );
}

wpcom_vip_qm_require();
