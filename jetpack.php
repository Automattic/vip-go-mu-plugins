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

/**
 * Logs when Jetpack runs the update action
 */
function wpcom_vip_log_updating_jetpack_version() {
	$jetpack_version = 'Jetpack version not yet available';
	if ( class_exists( 'Jetpack_Options' ) ) {
		$jetpack_version = Jetpack_Options::get_option( 'version' );
	}
	$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	$caller = 'unknown caller';
	foreach ( $backtrace as $call ) {
		if ( 'do_action' == $call['function'] ) {
			$caller = sprintf( '%s on line %d', str_replace( WP_CONTENT_DIR, '', $call['file'] ), $call['line'] );
			break;
		}
	}
	error_log('updating_jetpack_version from ' . $jetpack_version  . ' | ' . $_SERVER['REQUEST_URI'] . '  (action: ' . $_REQUEST['action'] . ') ' . ' by ' . $caller . ': ' . print_r( func_get_args(), 1 ) );
}
if ( defined( 'WPCOM_VIP_JETPACK_LOG' ) && WPCOM_VIP_JETPACK_LOG ) {
	add_action( 'updating_jetpack_version', 'wpcom_vip_log_updating_jetpack_version', 10, 2 );
}

/**
 * Logs when Jetpack actually updates its version option
 */
function wpcom_vip_log_updating_jetpack_version_option( $option_name, $option_value ) {
	$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	$caller = 'unknown caller';
	foreach ( $backtrace as $call ) {
		if ( 'update_option' == $call['function'] ) {
			$caller = sprintf( 'called by %s on line %d', str_replace( WP_CONTENT_DIR, '', $call['file'] ), $call['line'] );
			break;
		}
	}
	$current_value = Jetpack_Options::get_option( 'version' );
	error_log( 'Update Jetpack version to ' . $option_value  . ', ' . $caller . ' - current value is ' . $current_value . ', constant is ' . JETPACK__VERSION );
}
if ( defined( 'WPCOM_VIP_JETPACK_LOG' ) && WPCOM_VIP_JETPACK_LOG ) {
	add_action( 'pre_update_jetpack_option_version', 'wpcom_vip_log_updating_jetpack_version_option', 10, 2 );
}


if ( ! @constant( 'WPCOM_IS_VIP_ENV' ) ) {
	add_filter( 'jetpack_is_staging_site', '__return_true' );
}

$jetpack_to_load = WPMU_PLUGIN_DIR . '/jetpack/jetpack.php';

if ( defined( 'WPCOM_VIP_JETPACK_LOCAL' ) && WPCOM_VIP_JETPACK_LOCAL ) {
	// Set a specific alternative Jetpack
	$jetpack_to_test = WP_PLUGIN_DIR . '/jetpack/jetpack.php';

	// Test that our proposed Jetpack exists, otherwise do not use it
	if ( file_exists( $jetpack_to_test ) ) {
		$jetpack_to_load = $jetpack_to_test;
	}
}

require_once( $jetpack_to_load );

require_once( __DIR__ . '/vip-jetpack/vip-jetpack.php' );
