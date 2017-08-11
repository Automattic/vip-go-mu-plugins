<?php

namespace Automattic\VIP\Sunrise;

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

	error_log( 'Network Not Found! ' . $data );
}
add_action( 'ms_network_not_found', __NAMESPACE__ . '\network_not_found', 9, 2 );

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

	error_log( 'Site Not Found! ' . $data );
}
add_action( 'ms_site_not_found', __NAMESPACE__ . '\site_not_found', 9, 3 );

/**
 * When provided, load a client's sunrise too
 */
$client_sunrise = ABSPATH . '/vip-config/client-sunrise.php';
if ( file_exists( $client_sunrise ) ) {
	require_once $client_sunrise;
}
