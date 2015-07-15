<?php

/*
 * Plugin Name: MU Jetpack by WordPress.com
 * Plugin URI: http://wordpress.org/extend/plugins/jetpack/
 * Description: Bring the power of the WordPress.com cloud to your self-hosted WordPress. Jetpack enables you to connect your blog to a WordPress.com account to use the powerful features normally only available to WordPress.com users.
 * Author: Automattic
 * Version: 3.6
 * Author URI: http://jetpack.me
 * License: GPL2+
 * Text Domain: jetpack
 * Domain Path: /languages/
 */
 
add_filter( 'jetpack_client_verify_ssl_certs', '__return_true' );

require_once( __DIR__ . '/jetpack/jetpack.php' );
require_once( __DIR__ . '/vip-jetpack/vip-jetpack.php' );
