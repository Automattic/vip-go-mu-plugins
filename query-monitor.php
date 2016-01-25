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

$wpcom_vip_qm_file = __DIR__ . '/query-monitor/query-monitor.php';

require_once( $wpcom_vip_qm_file );

// Because we're including Query Monitor as an MU plugin, we need to
// manually call the `activate` method on `activation`.
if ( 0 === get_option( 'wpcom_vip_qm_activated', 0 ) ) {
	QueryMonitor::init( $wpcom_vip_qm_file )->activate( true );
	update_option( 'wpcom_vip_qm_activated', 1, true );
}
