<?php
/**
 * Helpers shared by the Site Details Index and sync tests.
 *
 * @package Automattic\Test\Utils
 */

namespace Automattic\Test\Utils;

/**
 * Answer every outgoing HTTP request with a 418 so tests never reach the network.
 *
 * The filter is removed when WP_UnitTestCase restores hooks after the test.
 */
function block_http_requests(): void {
	add_filter( 'pre_http_request', __NAMESPACE__ . '\\teapot_http_response' );
}

/**
 * `pre_http_request` callback for block_http_requests().
 *
 * @param false|array|\WP_Error $result A preempted response, or false to continue with the request.
 * @return array|\WP_Error
 */
function teapot_http_response( $result ) {
	if ( false !== $result ) {
		return $result;
	}

	return http_response( 418, '' );
}

/**
 * Drop the Site_Details_Index singleton, so the next instance() call creates (and hooks) a fresh one.
 */
function reset_site_details_index(): void {
	get_static_property_as_public( \Automattic\VIP\Config\Site_Details_Index::class, 'instance' )->setValue( null, null );
}
