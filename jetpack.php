<?php

/*
 * Plugin Name: MU Jetpack by WordPress.com
 * Plugin URI: http://jetpack.com
 * Description: Bring the power of the WordPress.com cloud to your self-hosted WordPress. Jetpack enables you to connect your blog to a WordPress.com account to use the powerful features normally only available to WordPress.com users.
 * Author: Automattic
 * Version: 4.3.1
 * Author URI: http://jetpack.com
 * License: GPL2+
 * Text Domain: jetpack
 * Domain Path: /languages/
 */

add_filter( 'jetpack_client_verify_ssl_certs', '__return_true' );

if ( ! @constant( 'WPCOM_IS_VIP_ENV' ) ) {
	add_filter( 'jetpack_is_staging_site', '__return_true' );
}

$jetpack_to_load = WPMU_PLUGIN_DIR . '/jetpack/jetpack.php';

require_once( $jetpack_to_load );

require_once( __DIR__ . '/vip-jetpack/vip-jetpack.php' );
