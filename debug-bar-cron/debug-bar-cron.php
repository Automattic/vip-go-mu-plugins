<?php
/**
 * Debug Bar Cron, a WordPress plugin.
 *
 * @package     WordPress\Plugins\Debug Bar Cron
 * @author      Zack Tollman, Helen Hou-Sandi, Juliette Reinders Folmer
 * @link        https://github.com/tollmanz/debug-bar-cron
 * @version     0.1.2
 * @license     http://creativecommons.org/licenses/GPL/2.0/ GNU General Public License, version 2 or higher
 *
 * @wordpress-plugin
 * Plugin Name: Debug Bar Cron
 * Plugin URI:  http://wordpress.org/extend/plugins/debug-bar-cron/
 * Description: Debug Bar Cron adds information about WP scheduled events to the Debug Bar.
 * Version:     0.1.2
 * Author:      Zack Tollman, Helen Hou-Sandi
 * Author URI:  http://github.com/tollmanz/
 * Depends:     Debug Bar
 * Text Domain: debug-bar-cron
 * Domain Path: /languages/
 */

// Avoid direct calls to this file.
if ( ! function_exists( 'add_action' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! function_exists( 'debug_bar_cron_has_parent_plugin' ) ) {
	/**
	 * Show admin notice & de-activate if debug-bar plugin not active.
	 */
	function debug_bar_cron_has_parent_plugin() {
		$file = plugin_basename( __FILE__ );

		if ( is_admin() && ( ! class_exists( 'Debug_Bar' ) && current_user_can( 'activate_plugins' ) ) && is_plugin_active( $file ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="error"><p>', sprintf( __( 'Activation failed: Debug Bar must be activated to use the <strong>Debug Bar Cron</strong> Plugin. %sVisit your plugins page to install & activate.', 'debug-bar-cron' ), '<a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&s=debug+bar' ) ) . '">' ), '</a></p></div>';
			} );

			deactivate_plugins( $file, false, is_network_admin() );

			// Add to recently active plugins list.
			$insert = array(
				$file => time(),
			);

			if ( ! is_network_admin() ) {
				update_option( 'recently_activated', ( $insert + (array) get_option( 'recently_activated' ) ) );
			} else {
				update_site_option( 'recently_activated', ( $insert + (array) get_site_option( 'recently_activated' ) ) );
			}

			// Prevent trying again on page reload.
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
		}
	}
	add_action( 'admin_init', 'debug_bar_cron_has_parent_plugin' );
}


if ( ! function_exists( 'zt_add_debug_bar_cron_panel' ) ) {
	/**
	 * Adds panel, as defined in the included class, to Debug Bar.
	 *
	 * @param array $panels Existing debug bar panels.
	 *
	 * @return array
	 */
	function zt_add_debug_bar_cron_panel( $panels ) {
		if ( ! class_exists( 'ZT_Debug_Bar_Cron' ) ) {
			require_once 'class-zt-debug-bar-cron.php';
			$panels[] = new ZT_Debug_Bar_Cron();
		}
		return $panels;
	}
	add_filter( 'debug_bar_panels', 'zt_add_debug_bar_cron_panel' );
}
