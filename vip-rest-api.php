<?php
/*
Plugin Name: REST API Enhancements
Plugin URI: https://vip.wordpress.com/
Description: Add custom REST API endpoints for VIP requests; these endpoints are private and only for the use of WordPress.com VIP
Author: Erick Hitter, Automattic
Version: 0.1
*/

/**
 * Generate token for requests made from WordPress.com to a given REST API route namespace
 *
 * Follows the approach of `wp_create_nonce()`, with a shortened duration and sha256 instead of md5
 *
 * @param  string $namespace  REST API route's namespace
 * @param  string $nonce_salt Salt to use with this hash
 * @return string
 */
function wpcom_vip_generate_go_rest_api_request_token( $namespace, $nonce_salt = NONCE_SALT ) {
	// Copies of this function exist in places without MINUTE_IN_SECONDS
	$minute_in_seconds = 60;

	// Two-minute nonce tick
	// Generate a four-minute tick per wp_nonce_tick(), but only accept the first half of the tick in wpcom_vip_verify_go_rest_api_request_authorization(), rendering it a two-minute tick
	$hash_data = ceil( time() / ( ( 4 * $minute_in_seconds ) / 2 ) );

	$hash_data .= '|';
	$hash_data .= $namespace;

	$hash = hash_hmac( 'sha256', $hash_data, $nonce_salt );

	return $hash;
}

/**
 * Verify that a given authorization header is valid for a REST API route namespace
 *
 * Follows the approach of `wp_verify_nonce()`, but only accepts the first half of the tick, for shorter durations
 *
 * @param  string $namespace   REST API route's namespace
 * @param  string $auth_header Authorization header to verify
 * @return bool
 */
function wpcom_vip_verify_go_rest_api_request_authorization( $namespace, $auth_header ) {
	$auth_header = explode( ' ', $auth_header );

	// Malformed auth header
	if ( 2 !== count( $auth_header ) ) {
		return false;
	}

	list( $auth_mechanism, $token ) = $auth_header;

	// Invalid auth mechanism
	if ( 'VIP-MACHINE-TOKEN' !== $auth_mechanism ) {
		return false;
	}

	$expected = wpcom_vip_generate_go_rest_api_request_token( $namespace );

	return hash_equals( $expected, $token );
}

/**
 * Check if necessary authentication header allows access to an endpoint
 *
 * Not always called as a REST API permission callback, hence going directly to the global
 *
 * @param  string $namespace RESET API route's namespace
 * @return bool
 */
function wpcom_vip_go_rest_api_request_allowed( $namespace ) {
	// Do we have a header to check?
	if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) && ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		$header = $_SERVER['HTTP_AUTHORIZATION'];
	} else {
		return false;
	}

	return wpcom_vip_verify_go_rest_api_request_authorization( $namespace, $header );
}

/**
 * Include customizations
 */
require_once __DIR__ . '/rest-api/vip-endpoints.php';
