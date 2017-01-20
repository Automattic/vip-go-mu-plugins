<?php
/*
 Plugin Name: WP-Cron Control
 Plugin URI: https://wordpress.org/plugins/wp-cron-control/
 Description: Take control of wp-cron execution.
 Author: Thorsten Ott, Erick Hitter, Automattic
 Version: 0.7.1
 Text Domain: wp-cron-control
 */

class WPCOM_VIP_Cron_Control {
	/**
	 * Register hooks
	 */
	public function __construct() {
		add_filter( 'pre_option_wpcroncontrol_settings', array( $this, 'set_options' ) );
		add_action( 'admin_menu', array( $this, 'remove_menu_page' ), 99 );
		add_action( 'admin_init', array( $this, 'block_admin_page' ) );

		// Load plugin
		require_once( __DIR__ . '/wp-cron-control/wp-cron-control.php' );
	}

	/**
	 * Enforce Platform-wide use of WP-Cron Control
	 *
	 * wpcroncontrol_settings: a:2:{s:6:"enable";s:1:"1";s:32:"enable_scheduled_post_validation";s:1:"1";}
	 */
	public function set_options( $options ) {
		return array(
			'enable' =>                           '1',
			'enable_scheduled_post_validation' => '1',

		);
	}

	/**
	 * Remove the menu page
	 */
	public function remove_menu_page() {
		remove_submenu_page( 'options-general.php', 'wp-cron-control' ); // WP_Cron_Control::instance()->dashed_name
	}

	/**
	 * Block access to the plugin's options page; everything is hardcoded
	 */
	public function block_admin_page() {
		global $plugin_page;

		if ( false !== stripos( $plugin_page, 'wp-cron-control' ) ) {
			wp_die( 'This plugin\'s options are unavailable.<br /><br />Please open a support ticket with any questions.', 'Unauthorized', array(
				'response' => 401,
				'back_link' => true,
			) );
		}
	}
}

// Allow testing of new approach to cron execution
$whitelisted_sites = array();
if ( true === WPCOM_IS_VIP_ENV && in_array( FILES_CLIENT_SITE_ID, $whitelisted_sites ) ) {
	add_filter( 'wpcom_vip_go_enable_new_cron_control', '__return_true' );
}

unset( $whitelisted_sites );

if ( apply_filters( 'wpcom_vip_go_enable_new_cron_control', false ) ) {
	require_once __DIR__ . '/cron-control/cron-control.php';
} else {
	new WPCOM_VIP_Cron_Control;
}
