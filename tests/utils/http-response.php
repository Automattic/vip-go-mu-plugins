<?php
/**
 * HTTP response fixtures for mocking `pre_http_request` and WP_Http.
 *
 * @package Automattic\Test\Utils
 */

namespace Automattic\Test\Utils;

/**
 * Build a response array in the shape returned by WP_Http::request().
 *
 * @param int    $code    HTTP status code.
 * @param string $body    Response body.
 * @param array  $headers Response headers.
 *
 * @return array
 */
function http_response( int $code = 200, string $body = '{}', array $headers = [] ): array {
	return [
		'headers'  => $headers,
		'body'     => $body,
		'response' => [
			'code'    => $code,
			'message' => get_status_header_desc( $code ),
		],
		'cookies'  => [],
		'filename' => null,
	];
}
