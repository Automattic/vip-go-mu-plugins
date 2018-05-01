<?php

namespace Automattic\VIP\Performance;

const BLOCKED_PATHS = [
	'/autodiscover/autodiscover.xml'
];

add_action( 'template_redirect', __NAMESPACE__ . '\block_requests' );

function block_requests() {
	if ( ! is_404() ) {
		return;
	}

	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	$request_path = parse_url( $request_uri, PHP_URL_PATH );
	if ( $request_path && in_array( $request_path, BLOCKED_PATHS, true ) ) {
		status_header( 403 );
		die( '403 Forbidden' );
	}
}
