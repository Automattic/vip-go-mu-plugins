<?php

/*
 * Plugin Name: Jetpack by WordPress.com
 * Plugin URI: https://jetpack.com
 * Description: Bring the power of the WordPress.com cloud to your self-hosted WordPress. Jetpack enables you to connect your blog to a WordPress.com account to use the powerful features normally only available to WordPress.com users.
 * Author: Automattic
 * Version: 6.4.2
 * Author URI: https://jetpack.com
 * License: GPL2+
 * Text Domain: jetpack
 * Domain Path: /languages/
 */

add_filter( 'jetpack_client_verify_ssl_certs', '__return_true' );

if ( ! @constant( 'WPCOM_IS_VIP_ENV' ) ) {
	add_filter( 'jetpack_is_staging_site', '__return_true' );
}

$jetpack_to_load = WPMU_PLUGIN_DIR . '/jetpack/jetpack.php';

if ( defined( 'WPCOM_VIP_JETPACK_LOCAL' ) && WPCOM_VIP_JETPACK_LOCAL ) {
	// Set a specific alternative Jetpack
	$jetpack_to_test = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/jetpack/jetpack.php';

	// Test that our proposed Jetpack exists, otherwise do not use it
	if ( file_exists( $jetpack_to_test ) ) {
		$jetpack_to_load = $jetpack_to_test;
	}
}

require_once( $jetpack_to_load );

require_once( __DIR__ . '/vip-jetpack/vip-jetpack.php' );
