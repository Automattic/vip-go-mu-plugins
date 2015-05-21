<?php

// For backwards compatibility - always true. Will have a helper for determining
// the current environment
// @todo This needs to be set on VIP env only, and set to `false` here
if ( ! defined( 'WPCOM_IS_VIP_ENV' ) ) {
    define( 'WPCOM_IS_VIP_ENV', false );
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

// Load WP_CLI helpers
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( __DIR__ . '/vip-helpers/vip-wp-cli.php' );
}

do_action( 'vip_loaded' );
