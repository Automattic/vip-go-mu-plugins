<?php
require_once __DIR__ . '/class-healthcheck.php';

// Execute the cache-healthcheck as quickly as possible
if ( isset( $_SERVER['REQUEST_URI'] ) && '/cache-healthcheck?' === $_SERVER['REQUEST_URI'] ) {
	if ( function_exists( 'newrelic_end_transaction' ) ) {
		// Discard the transaction (the `true` param)
		// See: https://docs.newrelic.com/docs/agents/php-agent/configuration/php-agent-api#api-end-txn
		newrelic_end_transaction( true );
	}

	http_response_code( 200 );

	die( 'ok' );
}

/**
 * `parse_request` provides a good balance between making sure the codebase is loaded and not running the main query.
 */
if ( isset( $_SERVER['REQUEST_URI'] ) && '/vip-healthcheck' === $_SERVER['REQUEST_URI'] ) {
	add_action( 'parse_request', 'vip_init_healthcheck_on_parse_request', PHP_INT_MIN );
}

function vip_init_healthcheck_on_parse_request( $wp ) {
	$hc = new Automattic\VIP\Healthcheck();
	$hc->check();
	$hc->render();
}
