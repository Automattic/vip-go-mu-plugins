<?php

namespace Automattic\VIP\Sunrise;

/**
 * Nothing to see here for single sites
 */
if ( ! defined( 'ABSPATH' ) || ! is_multisite() ) {
	return;
}

require_once WP_CONTENT_DIR . '/mu-plugins/lib/utils/class-context.php';

use Automattic\VIP\Utils\Context;

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
	$data = wp_json_encode( $data );

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
	trigger_error( 'ms_network_not_found: ' . htmlspecialchars( $data ), E_USER_WARNING );

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
	$data = wp_json_encode( $data );

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
	trigger_error( 'ms_site_not_found: ' . htmlspecialchars( $data ), E_USER_WARNING );

	handle_not_found_error( 'site' );
}
add_action( 'ms_site_not_found', __NAMESPACE__ . '\site_not_found', 9, 3 ); // Priority 9 to log before WP_CLI kills execution

function handle_not_found_error( $error_type ) {
	$is_healthcheck = Context::is_healthcheck();
	if ( $is_healthcheck ) {
		http_response_code( 200 );
		header( 'Content-type: text/plain' );
		printf( '%s not found; but still OK', $error_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain
		exit;
	}

	$is_web_request = Context::is_web_request();
	if ( $is_web_request ) {
		$is_maintenance_mode = Context::is_maintenance_mode();
		if ( $is_maintenance_mode ) {
			// 503 prevents page from being cached.
			// We handle healthchecks earlier and don't have to worry about them.
			$status_code = 503;
			header( 'X-VIP-Go-Maintenance: true' );
			$error_doc = sprintf( '%s/mu-plugins/errors/site-maintenance.html', WP_CONTENT_DIR );
		} else {
			$status_code = 404;
			$error_doc   = sprintf( '%s/mu-plugins/errors/%s-not-found.html', WP_CONTENT_DIR, $error_type );
		}

		http_response_code( $status_code );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- this is a local pre-made HTML file
		echo file_get_contents( $error_doc );
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
