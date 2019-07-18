<?php

/*
 * Plugin Name: Jetpack by WordPress.com
 * Plugin URI: https://jetpack.com
 * Description: Bring the power of the WordPress.com cloud to your self-hosted WordPress. Jetpack enables you to connect your blog to a WordPress.com account to use the powerful features normally only available to WordPress.com users.
 * Author: Automattic
 * Version: 7.5.3
 * Author URI: https://jetpack.com
 * License: GPL2+
 * Text Domain: jetpack
 * Domain Path: /languages/
 */

// Bump up the batch size to reduce the number of queries run to build a Jetpack sitemap.
if ( ! defined( 'JP_SITEMAP_BATCH_SIZE' ) ) {
	define( 'JP_SITEMAP_BATCH_SIZE', 200 );
}

add_filter( 'jetpack_client_verify_ssl_certs', '__return_true' );

if ( ! @constant( 'WPCOM_IS_VIP_ENV' ) ) {
	add_filter( 'jetpack_is_staging_site', '__return_true' );
}

/**
 * Add JP broken connection debug headers
 * 
 * NOTE - this _must_ come _before_ jetpack/jetpack.php is loaded, b/c the signature verification is
 * performed in __construct() of the Jetpack class, so hooking after it has been loaded is too late
 * 
 * $error is a WP_Error (always) and contains a "signature_details" data property with this structure:
 * The error_code has one of the following values:
 * - malformed_token
 * - malformed_user_id
 * - unknown_token
 * - could_not_sign
 * - invalid_nonce
 * - signature_mismatch
 */
function vip_jetpack_token_send_signature_error_headers( $error ) {
	if ( ! vip_is_jetpack_request() || headers_sent() || ! is_wp_error( $error ) ) {
		return;
	}

	$error_data = $error->get_error_data();

	if ( ! isset( $error_data['signature_details'] ) ) {
		return;
	}

	header( sprintf(
		'X-Jetpack-Signature-Error: %s',
		$error->get_error_code()
	) );

	header( sprintf(
		'X-Jetpack-Signature-Error-Message: %s',
		$error->get_error_message()
	) );

	header( sprintf(
		'X-Jetpack-Signature-Error-Details: %s',
		base64_encode( json_encode( $error_data['signature_details'] ) )
	) );
}

add_action( 'jetpack_verify_signature_error', 'vip_jetpack_token_send_signature_error_headers' );

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
