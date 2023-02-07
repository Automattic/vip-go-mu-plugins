<?php

/**
 * Plugin Name: Memcached
 * Description: Memcached backend for the WP Object Cache.
 * Version: 4.0.0
 * Author: Automattic
 * Plugin URI: https://wordpress.org/plugins/memcached/
 * License: GPLv2 or later
 *
 * This file is require'd from wp-content/object-cache.php
 */

// Will use the "next" version on these specified environment types by default.
if ( ! defined( 'VIP_USE_NEXT_OBJECT_CACHE_DROPIN' ) ) {
	if ( in_array( VIP_GO_APP_ENVIRONMENT, [ 'develop', 'preprod', 'staging' ], true ) ) {
		define( 'VIP_USE_NEXT_OBJECT_CACHE_DROPIN', true );
	}
}

if ( defined( 'VIP_USE_ALPHA_OBJECT_CACHE_DROPIN' ) && true === VIP_USE_ALPHA_OBJECT_CACHE_DROPIN ) {
	require_once __DIR__ . '/wp-cache-memcached/object-cache.php';
} elseif ( defined( 'VIP_USE_NEXT_OBJECT_CACHE_DROPIN' ) && true === VIP_USE_NEXT_OBJECT_CACHE_DROPIN ) {
	require_once __DIR__ . '/object-cache/object-cache-next.php';
} else {
	require_once __DIR__ . '/object-cache/object-cache-stable.php';
}

// Load in the apc user cache.
if ( file_exists( dirname( __DIR__ ) . '/lib/class-apc-cache-interceptor.php' ) ) {
	require_once dirname( __DIR__ ) . '/lib/class-apc-cache-interceptor.php';
}
