<?php

/**
 * Plugin Name: Gutenberg Ramp
 * Description: Control the circumstances under which the Gutenberg editor loads in code, using Gutenberg Ramp.
 * Version:     0.2
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
 * Load Gutenberg via the Gutenberg Ramp plugin.
 */
function wpcom_vip_load_gutenberg( $criteria = false ) {
	if ( ! function_exists( 'gutenberg_ramp_load_gutenberg' ) ) {
		return;
	}
	gutenberg_ramp_load_gutenberg( $criteria );
}
