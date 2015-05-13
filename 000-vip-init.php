<?php

// For backwards compatibility - always true. Will have a helper for determining
// the current environment
define( 'WPCOM_IS_VIP_ENV', true );

// Load our development and environment helpers
require_once( __DIR__ . '/vip/vip-utils.php' );
require_once( __DIR__ . '/vip/vip-caching.php' );
require_once( __DIR__ . '/vip/vip-roles.php' );
require_once( __DIR__ . '/vip/vip-permastructs.php' );
require_once( __DIR__ . '/vip/vip-mods.php' );
require_once( __DIR__ . '/vip/vip-media.php' );
require_once( __DIR__ . '/vip/vip-elasticsearch.php' );
require_once( __DIR__ . '/vip/vip-stats.php' );
require_once( __DIR__ . '/vip/vip-deprecated.php' );

// Load WP_CLI helpers
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( __DIR__ . '/vip/vip-wp-cli.php' );
}

do_action( 'vip_loaded' );
