<?php

// Execute the healthcheck as quickly as possible
// See `mu-plugins/vip-cache-manager/` for other caching functionality
if ( '/cache-healthcheck?' === $_SERVER['REQUEST_URI'] ) {
	if ( function_exists( 'newrelic_end_transaction' ) ) {
		# See: https://docs.newrelic.com/docs/agents/php-agent/configuration/php-agent-api#api-end-txn
		newrelic_end_transaction( true );
	}
	die( 'ok' );
}

if ( file_exists( __DIR__ . '/.secrets/vip-secrets.php' ) ) {
	require __DIR__ . '/.secrets/vip-secrets.php';
}

if ( ! defined( 'A8C_PROXIED_REQUEST' ) ) {
	/**
	 * @var constant A8C_PROXIED_REQUEST Set to true if the current request is made via the Automattic proxy, which is only available to Automatticians
	 */
	define( 'A8C_PROXIED_REQUEST', false );
}

/**
 * @constant VIP_GO_ENV The name of the current VIP Go environment. Falls back to `false`.
 */
if ( ! defined( 'VIP_GO_ENV' ) ) {
	define( 'VIP_GO_ENV', false );
}

// For backwards compatibility - always true.
if ( ! defined( 'WPCOM_IS_VIP_ENV' ) ) {
    define( 'WPCOM_IS_VIP_ENV', false );
}

$hostname = gethostname();
define( 'WPCOM_SANDBOXED', false !== strpos( $hostname, '_web_dev_' ) );

if ( WPCOM_SANDBOXED ) {
	require __DIR__ . '/vip-helpers/sandbox.php';
}

// Load our development and environment helpers
require_once( __DIR__ . '/vip-helpers/vip-utils.php' );
require_once( __DIR__ . '/vip-helpers/vip-caching.php' );
require_once( __DIR__ . '/vip-helpers/vip-roles.php' );
require_once( __DIR__ . '/vip-helpers/vip-permastructs.php' );
require_once( __DIR__ . '/vip-helpers/vip-mods.php' );
require_once( __DIR__ . '/vip-helpers/vip-media.php' );
require_once( __DIR__ . '/vip-helpers/vip-elasticsearch.php' );
require_once( __DIR__ . '/vip-helpers/vip-stats.php' );
require_once( __DIR__ . '/vip-helpers/vip-deprecated.php' );
require_once( __DIR__ . '/vip-helpers/vip-syndication-cache.php' );

//enabled on selected sites for now
if ( true === defined( 'WPCOM_VIP_CLEAN_TERM_CACHE' ) && true === constant( 'WPCOM_VIP_CLEAN_TERM_CACHE' ) ) {
	require_once dirname( __FILE__ ) . '/vip-helpers/vip-clean-term-cache.php';
}

// Load WP_CLI helpers
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( __DIR__ . '/vip-helpers/vip-wp-cli.php' );
}

// Add Automattic's custom header
add_action( 'send_headers', function() {
	if ( ! defined( 'WP_INSTALLING' ) || ! WP_INSTALLING ) {
		header( "X-hacker: If you're reading this, you should visit automattic.com/jobs and apply to join the fun, mention this header." );
	}
} );

do_action( 'vip_loaded' );
