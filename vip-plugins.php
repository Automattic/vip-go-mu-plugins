<?php
/*
 * Plugin Name: VIP Plugins
 * Plugin URI: https://github.com/Automattic/vip-go-mu-plugins
 * Description: VIP specific plugin UI changes
 * Author: Automattic
 * Version: 1.0.0
 * License: GPL2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

require_once( __DIR__ . '/vip-plugins/vip-plugins.php' );

/** loading helper and behaviors for Gutenberg on VIP Go **/

/** load Gutenberg Ramp **/
if ( file_exists( __DIR__ . '/ramp-for-gutenberg/ramp-for-gutenberg.php' ) ) {
	require_once( __DIR__ . '/ramp-for-gutenberg/ramp-for-gutenberg.php' );
}

/** Turn off the UI for Ramp **/
add_action( 'plugins_loaded', function() {
	remove_action( 'admin_init', 'ramp_for_gutenberg_initialize_admin_ui' );
} );

/** Loading helper **/
function wpcom_vip_load_gutenberg( $criteria ) {
	if ( !function_exists( 'ramp_for_gutenberg_load_gutenberg' ) ) {
		return;
	}
	ramp_for_gutenberg_load_gutenberg( $criteria );
}