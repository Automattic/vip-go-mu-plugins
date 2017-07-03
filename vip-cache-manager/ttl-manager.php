<?php

/*
Plugin name: TTL Manager
Description: Sets sane and reasonable cache TTLs for site responses.
Author: Automattic
Author URI: http://automattic.com/
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

namespace Automattic\VIP\Cache;

class TTL_Manager {
	const DEFAULT_API_TTL = \MINUTE_IN_SECONDS;

	public static function init() {
		add_filter( 'rest_post_dispatch', [ __CLASS__, 'enforce_rest_api_read_ttl' ], 10, 3 );
	}

	public static function enforce_rest_api_read_ttl( $response, $rest_server, $request ) {
		if ( $response->is_error() ) {
			return $response;
		}

		$method = $request->get_method();
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return $response;
		}

		$response_headers = $response->get_headers();
		if ( isset( $response_headers[ 'Cache-Control' ] ) ) {
			return $response;
		}

		if ( is_user_logged_in() ) {
			return $response;
		}

		$ttl = apply_filters( 'wpcom_vip_rest_response_ttl', self::DEFAULT_API_TTL, $response, $rest_server, $request );
		self::set_rest_response_ttl( $response, $ttl );

		return $response;
	}

	protected static function set_rest_response_ttl( \WP_REST_Response $response, $ttl ) {
		$response->header( 'Cache-Control', sprintf( 'max-age=%d', $ttl ) );
	}
}

TTL_Manager::init();
