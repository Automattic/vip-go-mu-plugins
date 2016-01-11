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
if ( defined( 'VIP_JETPACK_ALT' ) && VIP_JETPACK_ALT ) {

	// Set a default alternative Jetpack to load
	$jp_alt_suffix = '-beta';
	// Allow the alternative version of Jetpack to be specified on
	// a site by site basis
	if ( defined( 'VIP_JETPACK_ALT_SUFFIX' ) && VIP_JETPACK_ALT_SUFFIX ) {
		$jp_alt_suffix = VIP_JETPACK_ALT_SUFFIX;
	}

	// Save the original Jetpack path, in case we need to revert
	$jetpack_to_load_orig = $jetpack_to_load;
	// Construct the new Jetpack path we want loaded, and test that it exists,
	// falling back to the original if it does not.
	$jetpack_to_load = __DIR__ . '/jetpack' . $jp_alt_suffix . '/jetpack.php';
	if ( ! file_exists( $jetpack_to_load ) ) {
		// We assume that the regular VIP Go Jetpack folder DOES exist
		$jetpack_to_load = $jetpack_to_load_orig;
	}
}
require_once( $jetpack_to_load );

require_once( __DIR__ . '/vip-jetpack/vip-jetpack.php' );
