<?php
/**
 * Plugin utilities
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

/**
 * Parse request using Core's logic
 *
 * We have occasion to check the request before Core has done so, such as when preparing the environment to run a cron job
 */
function parse_request() {
	// Hold onto this as it won't change during the request.
	static $parsed_request = null;
	if ( is_array( $parsed_request ) ) {
		return $parsed_request;
	}

	// Starting somewhere.
	$rewrite_index = 'index.php';

	/**
	 * Start what's borrowed from Core
	 *
	 * References to $wp_rewrite->index were replaced with $rewrite_index, and whitespace updated, but otherwise, this is directly from WP::parse_request()
	 */
	// Borrowed from Core. @codingStandardsIgnoreStart
	$pathinfo = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '';
	list( $pathinfo ) = explode( '?', $pathinfo );
	$pathinfo = str_replace( "%", "%25", $pathinfo );

	list( $req_uri ) = explode( '?', $_SERVER['REQUEST_URI'] );
	$self = $_SERVER['PHP_SELF'];

	$home_path = parse_url( home_url(), PHP_URL_PATH );
	$home_path_regex = '';
	if ( is_string( $home_path ) && '' !== $home_path ) {
		$home_path       = trim( $home_path, '/' );
		$home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );
	}

	/*
	 * Trim path info from the end and the leading home path from the front.
	 * For path info requests, this leaves us with the requesting filename, if any.
	 * For 404 requests, this leaves us with the requested permalink.
	 */
	$req_uri  = str_replace( $pathinfo, '', $req_uri );
	$req_uri  = trim( $req_uri, '/' );
	$pathinfo = trim( $pathinfo, '/' );
	$self     = trim( $self, '/' );

	if ( ! empty( $home_path_regex ) ) {
		$req_uri  = preg_replace( $home_path_regex, '', $req_uri );
		$req_uri  = trim( $req_uri, '/' );
		$pathinfo = preg_replace( $home_path_regex, '', $pathinfo );
		$pathinfo = trim( $pathinfo, '/' );
		$self     = preg_replace( $home_path_regex, '', $self );
		$self     = trim( $self, '/' );
	}

	// The requested permalink is in $pathinfo for path info requests and
	//  $req_uri for other requests.
	if ( ! empty( $pathinfo ) && ! preg_match( '|^.*' . $rewrite_index . '$|', $pathinfo ) ) {
		$requested_path = $pathinfo;
	} else {
		// If the request uri is the index, blank it out so that we don't try to match it against a rule.
		if ( $req_uri == $rewrite_index ) {
			$req_uri = '';
		}

		$requested_path = $req_uri;
	}

	$requested_file = $req_uri;
	// Borrowed from Core. @codingStandardsIgnoreEnd
	/**
	 * End what's borrowed from Core
	 */

	// Return array of data about the request.
	$parsed_request = compact( 'requested_path', 'requested_file', 'self' );

	return $parsed_request;
}

/**
 * Consistently set flag Core uses to indicate cron execution is ongoing
 */
function set_doing_cron() {
	if ( ! defined( 'DOING_CRON' ) ) {
		define( 'DOING_CRON', true );
	}

	// WP 4.8 introduced the `wp_doing_cron()` function and filter.
	// These can be used to override the `DOING_CRON` constant, which may cause problems for plugin's requests.
	add_filter( 'wp_doing_cron', '__return_true', 99999 );
}

// Helper method for deprecating publicly accessibly functions/methods.
function _deprecated_function( string $function, string $replacement = '', $error_level = 2 ) {
	$error_levels = [
		'debug'  => 1,
		'notice' => 2,
		'warn'   => 3,
	];

	$message = sprintf( 'Cron-Control: Deprecation. %s is deprecated and will soon be removed.', $function );
	if ( ! empty( $replacement ) ) {
		$message .= sprintf( ' Use %s instead.', $replacement );
	}

	// Use E_WARNING error level.
	$warning_constant = defined( 'CRON_CONTROL_WARN_FOR_DEPRECATIONS' ) && CRON_CONTROL_WARN_FOR_DEPRECATIONS;
	if ( $warning_constant || $error_level >= $error_levels['warn'] ) {
		trigger_error( $message, E_USER_WARNING );
		return;
	}

	// Use E_USER_NOTICE regardless of Debug mode.
	if ( $error_level >= $error_levels['notice'] ) {
		trigger_error( $message, E_USER_NOTICE );
		return;
	}

	// Use E_USER_NOTICE only in Debug mode.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		trigger_error( $message, E_USER_NOTICE );
		return;
	}
}
