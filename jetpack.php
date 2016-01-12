<?php

/*
 * Plugin Name: MU Jetpack by WordPress.com
 * Plugin URI: http://jetpack.me
 * Description: Bring the power of the WordPress.com cloud to your self-hosted WordPress. Jetpack enables you to connect your blog to a WordPress.com account to use the powerful features normally only available to WordPress.com users.
 * Author: Automattic
 * Version: 3.8.2
 * Author URI: http://jetpack.me
 * License: GPL2+
 * Text Domain: jetpack
 * Domain Path: /languages/
 */
 
add_filter( 'jetpack_client_verify_ssl_certs', '__return_true' );

$jetpack_to_load = __DIR__ . '/jetpack/jetpack.php';

// If the VIP_JETPACK_ALT constant is defined, we should attempt to load
// an alternative to the standard Jetpack
// Logic:
// * If no constant is specified, the Jetpack in `mu-plugins/jetpack/`
//   is loaded
// * If VIP_JETPACK_ALT alone is specified, the Jetpack in
//   `mu-plugins/jetpack-beta/` is loaded
// * If VIP_JETPACK_ALT and VIP_JETPACK_ALT_SUFFIX are specified,
//   the Jetpack in `mu-plugins/jetpack-VIP_JETPACK_ALT_SUFFIX/` is loaded
if ( defined( 'VIP_JETPACK_ALT' ) && VIP_JETPACK_ALT ) {

	// Set the default alternative Jetpack
	$jetpack_to_test = __DIR__ . '/jetpack-beta/jetpack.php';

	// Allow the alternative version of Jetpack to be specified on
	// a site by site basis
	if ( defined( 'VIP_JETPACK_ALT_SUFFIX' ) && VIP_JETPACK_ALT_SUFFIX ) {
		// Set a specific alternative Jetpack
		$jetpack_to_test = __DIR__ . '/jetpack' . VIP_JETPACK_ALT_SUFFIX . '/jetpack.php';
	}

	// Test that our proposed Jetpack exists, otherwise do not use it
	if ( file_exists( $jetpack_to_test ) ) {
		$jetpack_to_load = $jetpack_to_test;
	}
}
require_once( $jetpack_to_load );

require_once( __DIR__ . '/vip-jetpack/vip-jetpack.php' );
