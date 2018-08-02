<?php

/**
 * Plugin Name: Gutenberg Ramp
 * Description: Allows theme authors to control the circumstances under which the Gutenberg editor loads. Options include "load" (1 loads all the time, 0 loads never) "post_ids" (load for particular posts) "post_types" (load for particular posts types.)
 * Version:     1.0.0
 * Author:      Automattic, Inc.
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: gutenberg-ramp
 */

// This file loads Ramp, and modifies behaviors for Gutenberg on VIP Go

if ( defined( 'VIP_GO_DISABLE_RAMP' ) && true === VIP_GO_DISABLE_RAMP ) {
	return;
}

/** load Gutenberg Ramp **/
if ( file_exists( __DIR__ . '/gutenberg-ramp/gutenberg-ramp.php' ) ) {
	require_once( __DIR__ . '/gutenberg-ramp/gutenberg-ramp.php' );
}

/** Turn off the UI for Ramp **/
add_action( 'plugins_loaded', function() {
	remove_action( 'admin_init', 'gutenberg_ramp_initialize_admin_ui' );
} );

/**
 * Remove Try Gutenberg callout introduced as part of 4.9.8
 */
remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );

/**
 * Load Gutenberg via the Gutenberg Ramp plugin.
 */
function wpcom_vip_load_gutenberg( $criteria = false ) {
	if ( ! function_exists( 'gutenberg_ramp_load_gutenberg' ) ) {
		return;
	}
	gutenberg_ramp_load_gutenberg( $criteria );
}
