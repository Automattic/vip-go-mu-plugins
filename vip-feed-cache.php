<?php

/**
 * Plugin Name: VIP Feed Cache
 * Description: Sets VIP Go Cache Class.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/**
 * Sets the SimplePie Cache Class to our custom VIP Go Class.
 *
 * @param SimplePie &$feed SimplePie feed object, passed by reference.
 *
 * @return void
 */
function vipgo_feed_options( &$feed ) {
	require_once __DIR__ . '/vip-feed-cache/class-vip-go-feed-cache.php';
	require_once __DIR__ . '/vip-feed-cache/class-vip-go-feed-transient.php';

	$feed->set_cache_class( VIP_Go_Feed_Cache::class );
}

add_action( 'wp_feed_options', 'vipgo_feed_options' );
