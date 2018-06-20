<?php

namespace Automattic\VIP\Sunrise;

require_once( WPMU_PLUGIN_DIR . '/lib/utils/context.php' );

use Automattic\VIP\Utils\Context;

/**
 * Nothing to see here for single sites
 */
if ( ! is_multisite() ) {
	return;
}

/**
 * Log errors retrieving network for a given request
 *
 * @param string $domain
 * @param string $path
 */
function network_not_found( $domain, $path ) {
	$data = [
		'domain_requested' => $domain,
		'path_requested'   => $path,
	];
	$data = json_encode( $data );

	trigger_error( 'ms_network_not_found: ' . $data, E_USER_WARNING );

	handle_not_found_error( 'network' );
}
add_action( 'ms_network_not_found', __NAMESPACE__ . '\network_not_found', 9, 2 ); // Priority 9 to log before WP_CLI kills execution

/**
 * Log errors retrieving a site for a given request
 *
 * @param \WP_Network $network
 * @param string $domain
 * @param string $path
 */
function site_not_found( $network, $domain, $path ) {
	$data = [
		'network_id'       => $network->id,
		'domain_requested' => $domain,
		'path_requested'   => $path,
	];
	$data = json_encode( $data );

	trigger_error( 'ms_site_not_found: ' . $data, E_USER_WARNING );

	handle_not_found_error( 'site' );
}
add_action( 'ms_site_not_found', __NAMESPACE__ . '\site_not_found', 9, 3 ); // Priority 9 to log before WP_CLI kills execution

function handle_not_found_error( $error_type ) {
	$is_healthcheck = Context::is_healthcheck();
	if ( $is_healthcheck ) {
		http_response_code( 200 );
		header( 'Content-type: text/plain' );
		echo 'OK';
		exit;
	}

	$is_web_request = Context::is_web_request();
	if ( $is_web_request ) {
		http_response_code( 404 );
		echo file_get_contents( sprintf( '%s/errors/%s-not-found.html', WPMU_PLUGIN_DIR, $error_type ) );
		exit;
	}
}

/**
 * When provided, load a client's sunrise too
 */
$client_sunrise = ABSPATH . '/vip-config/client-sunrise.php';
if ( file_exists( $client_sunrise ) ) {
	require_once $client_sunrise;
}
