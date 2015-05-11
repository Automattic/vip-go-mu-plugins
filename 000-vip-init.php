<?php

// For backwards compatibility - always true. Will have a helper for determining
// the current environment
define( 'WPCOM_IS_VIP_ENV', true );

// Load our development and environment helpers
require_once( __DIR__ . '/vip/vip-utils.php' );
require_once( __DIR__ . '/vip/vip-roles.php' );
require_once( __DIR__ . '/vip/vip-permastructs.php' );
require_once( __DIR__ . '/vip/vip-mods.php' );
require_once( __DIR__ . '/vip/vip-media.php' );

// Load WP_CLI helpers
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( __DIR__ . '/vip/vip-wp-cli.php' );
}



// These are helper functions specific to WP.com-related functionality
wpcom_vip_load_helper_wpcom(); // vip-helper-wpcom.php
wpcom_vip_load_helper_stats(); // vip-helper-stats-wpcom.php

// Load the "works everywhere" helper file
wpcom_vip_load_helper(); // vip-helper.php

do_action( 'vip_loaded' );
