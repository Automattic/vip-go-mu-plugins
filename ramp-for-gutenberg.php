<?php

/**
 * Plugin Name: Gutenberg Ramp
 * Description: Allows theme authors to control the circumstances under which the Gutenberg editor loads. Options include "load" (1 loads all the time, 0 loads never) "post_ids" (load for particular posts) "post_types" (load for particular posts types.)
 * Version:     0.1
 * Author:      Automattic, Inc.
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: ramp-for-gutenberg
 */

// This file loads Ramp, and modifies behaviors for Gutenberg on VIP Go

/** load Gutenberg Ramp **/
if ( file_exists( __DIR__ . '/ramp-for-gutenberg/ramp-for-gutenberg.php' ) ) {
	require_once( __DIR__ . '/ramp-for-gutenberg/ramp-for-gutenberg.php' );
}

/** Turn off the UI for Ramp **/
add_action( 'plugins_loaded', function() {
	remove_action( 'admin_init', 'ramp_for_gutenberg_initialize_admin_ui' );
} );

/** Loading helper **/
function wpcom_vip_load_gutenberg( $criteria = false ) {
	if ( ! function_exists( 'ramp_for_gutenberg_load_gutenberg' ) ) {
		return;
	}
	ramp_for_gutenberg_load_gutenberg( $criteria );
}
