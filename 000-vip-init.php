<?php


/**
 * Takes the value of a deprecated constant, and sets the currently valid constant to
 * that value… if that valid constant is not already set.
 *
 * @param string $deprecated_constant The deprecated constant name
 * @param string $valid_constant The valid constant name
 */
function wpcom_vip_maybe_convert_deprecated_constant( $deprecated_constant, $valid_constant ) {
	if ( defined( $deprecated_constant ) && ! defined( $valid_constant ) ) {
		define( $valid_constant, constant( $deprecated_constant ) );
	}
}

wpcom_vip_maybe_convert_deprecated_constant( 'VIP_JETPACK_ALT', 'WPCOM_VIP_JETPACK_ALT' );
wpcom_vip_maybe_convert_deprecated_constant( 'VIP_JETPACK_ALT_SUFFIX', 'WPCOM_VIP_JETPACK_ALT_SUFFIX' );
wpcom_vip_maybe_convert_deprecated_constant( 'VIP_DO_PINGS', 'WPCOM_VIP_DO_PINGS' );
wpcom_vip_maybe_convert_deprecated_constant( 'VIP_VERIFY_PATH', 'WPCOM_VIP_VERIFY_PATH' );
wpcom_vip_maybe_convert_deprecated_constant( 'VIP_VERIFY_STRING', 'WPCOM_VIP_VERIFY_STRING' );


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
