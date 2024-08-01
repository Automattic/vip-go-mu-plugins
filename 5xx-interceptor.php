<?php 
/**
 * This plugin intercepts the 50x status code and changes it to 58x
 * 
 * The status code will be rewritten at the edge.
 * 
 * This is a WPVIP-specific plugin and will only work on WPVIP infrastructure.
 */
namespace Automattic\VIP;

use Automattic\VIP\Utils\Context;
if ( ! ( Context::is_web_request() || Context::is_ajax() ) ) {
	return;
}

// We hook on parse_request to side-step the handle_404 logic.
// Strictly speaking send_headers would be but at that point WP()->handle_404() has already been called, potentially overriding any user 503 to 200. 
add_action( 'parse_request', '\Automattic\VIP\rewrite_50x_headers', PHP_INT_MIN );
function rewrite_50x_headers() {
	$current_response_code = http_response_code();
	$override_codes        = [
		503 => [
			'code'        => 583,
			'description' => 'Service Unavailable',
		],
		502 => [
			'code'        => 582,
			'description' => 'Bad Gateway',
		],
	];

	// Technically headers_sent() should never be true at this moment.
	if ( ! headers_sent() && isset( $override_codes[ $current_response_code ] ) ) {
		// 404 handler may override a 404 with a 200, so we need to ensure that the 404 handler is not triggered.
		add_filter( 'pre_handle_404', '__return_true' );
		// The description is important for the core logic, otherwise the non-standard codes get discarded and replaced by 200.
		// @see wp-includes/functions.php:status_header()
		status_header( $override_codes[ $current_response_code ]['code'], $override_codes[ $current_response_code ]['description'] );
	}
}
