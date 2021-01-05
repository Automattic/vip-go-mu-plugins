<?php
namespace Automattic\VIP\Search\Dev_Tools;

// Bail early if VIP Search is not active to not waste resources
if ( ! ( defined( 'VIP_ENABLE_VIP_SEARCH' ) && VIP_ENABLE_VIP_SEARCH ) ) {
	return;
}

define( 'SEARCH_DEV_TOOLS_CAP', 'edit_others_posts' );
define( 'SEARCH_DEV_TOOLS_AJAX_ACTION', 'vip_search_ajax' );
define( 'SEARCH_DEV_TOOLS_NONCE', 'vip_search_ajax_nonce' );

add_action( 'wp_ajax_' . SEARCH_DEV_TOOLS_AJAX_ACTION, '\Automattic\VIP\Search\Dev_Tools\ajax_callback' );

/**
 * AJAX callback to proxy the query to ES and return the result
 *
 * @return void
 */
function ajax_callback() {
	$req = json_decode( file_get_contents( 'php://input' ) );

	if ( json_last_error() ) {
		wp_send_json_error( [ 'error' => 'Malformed payload' ], 400 );
	}

	if ( ! ( should_enable_search_dev_tools() && wp_verify_nonce( $req['nonce'] ?? '', SEARCH_DEV_TOOLS_NONCE ) ) ) {
		wp_send_json_error( [ 'error' => 'Unauthorized' ], 403 );
	}

	$ep = \ElasticPress\Elasticsearch::factory();
}

/**
 * A capability-based check for whether Dev Tools should be enabled or not
 *
 * @return boolean
 */
function should_enable_search_dev_tools() {
	return current_user_can( SEARCH_DEV_TOOLS_CAP );
}
