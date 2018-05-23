<?php

/**
 * Plugin Name: Gutenberg Ramp
 * Description: Control the circumstances under which the Gutenberg editor loads in code, using Gutenberg Ramp.
 * Version:     0.2
 * Author:      Automattic, Inc.
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: ramp-for-gutenberg
 */

// This file loads Ramp, and modifies behaviors for Gutenberg on VIP Go

if ( defined( 'VIP_GO_DISABLE_RAMP' ) && VIP_GO_DISABLE_RAMP ) {
	return;
}

/** load Gutenberg Ramp **/
if ( file_exists( __DIR__ . '/ramp-for-gutenberg/ramp-for-gutenberg.php' ) ) {
	require_once( __DIR__ . '/ramp-for-gutenberg/ramp-for-gutenberg.php' );
}

/** Turn off the UI for Ramp **/
add_action( 'plugins_loaded', function() {
	remove_action( 'admin_init', 'ramp_for_gutenberg_initialize_admin_ui' );
} );

/**
 * Load Gutenberg via the Gutenberg Ramp plugin.
 *
 * @param array|false $criteria Use `false` or [ 'load' => 1 ] to always load, [ 'load' => 0 ] to never load, [ 'post_ids' => [] ] to load for particular posts, and [ 'post_types' => [] ] to load for particular post types.
 */
function wpcom_vip_load_gutenberg( $criteria = false ) {
	if ( ! function_exists( 'ramp_for_gutenberg_load_gutenberg' ) ) {
		return;
	}
	ramp_for_gutenberg_load_gutenberg( $criteria );
}
