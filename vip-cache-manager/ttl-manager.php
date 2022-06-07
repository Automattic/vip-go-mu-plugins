<?php

/*
Plugin name: TTL Manager
Description: Sets cache TTLs for site responses, see https://docs.wpvip.com/technical-references/caching/page-cache/
Author: Automattic
Author URI: https://automattic.com/
Version: 1.0
License: GPL version 2 or later - https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

namespace Automattic\VIP\Cache\TTL_Manager;

const DEFAULT_API_TTL = \MINUTE_IN_SECONDS;

function init() {
	add_filter( 'rest_post_dispatch', __NAMESPACE__ . '\enforce_rest_api_read_ttl', 10, 3 );
}

function enforce_rest_api_read_ttl( $response, $rest_server, $request ) {
	if ( $response->is_error() ) {
		return $response;
	}

	$method = $request->get_method();
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return $response;
	}

	// Don't override existing Cache-Control headers sent via PHP
	$php_headers = headers_list();
	foreach ( $php_headers as $header ) {
		if ( 0 === stripos( $header, 'Cache-Control:' ) ) {
			return $response;
		}
	}

	// Don't override existing Cache-Control headers set via REST Response
	$response_headers = $response->get_headers();
	if ( isset( $response_headers['Cache-Control'] ) ) {
		return $response;
	}

	if ( is_user_logged_in() ) {
		return $response;
	}

	/**
	 * The fallback TTL (Cache-Control: max-age) value to use for unauthenticated, non-error, read (GET / HEAD) requests to the API.
	 *
	 * Defaults to 60 seconds.
	 *
	 * @param int $ttl The TTL value to use.
	 * @param WP_REST_Response $response The outbound REST API response object.
	 * @param WP_REST_Server $rest_server The REST API server object.
	 * @param WP_REST_Request $request The incoming REST API request object.
	 */
	$ttl = apply_filters( 'wpcom_vip_rest_read_response_ttl', DEFAULT_API_TTL, $response, $rest_server, $request );
	set_rest_response_ttl( $response, $ttl );

	return $response;
}

function set_rest_response_ttl( \WP_REST_Response $response, $ttl ) {
	$response->header( 'Cache-Control', sprintf( 'max-age=%d', $ttl ) );
}

// Let's do it!
init();
