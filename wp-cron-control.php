<?php
/*
 Plugin Name: WP-Cron Control
 Plugin URI: https://wordpress.org/plugins/wp-cron-control/
 Description: Take control of wp-cron execution.
 Author: Thorsten Ott, Erick Hitter, Automattic
 Version: 0.7.1
 Text Domain: wp-cron-control
 */

// TODO:
// Global WP_CRON_CONTROL_SECRET
// Remove menu - add_action( 'admin_menu', array( &$this, 'register_settings_page' ) ); WP_Cron_Control::instance

/**
 * Enforce Platform-wide use of WP-Cron Control
 *
 * wpcroncontrol_settings: a:2:{s:6:"enable";s:1:"1";s:32:"enable_scheduled_post_validation";s:1:"1";}
 */
function wpcom_vip_cron_control_options( $options ) {
	return array(
		'enable' =>                           '1',
		'enable_scheduled_post_validation' => '1',

	);
}
add_filter( 'pre_get_option_wpcroncontrol_settings', 'wpcom_vip_cron_control_options' );

/**
 * Load plugin
 */
require( __DIR__ . '/wp-cron-control/wp-cron-control.php' );