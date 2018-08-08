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
 * @param object &$feed SimplePie feed object, passed by reference.
 * @param mixed  $url   URL of feed to retrieve. If an array of URLs, the feeds are merged.
 *
 * @return void
 */
function vipgo_feed_options( &$feed, $url ) {
	require_once( __DIR__ . '/vip-feed-cache/class-vip-go-feed-cache.php' );

	$feed->set_cache_class( 'VIP_Go_Feed_Cache' );
}

add_action( 'wp_feed_options', 'vipgo_feed_options', 10, 2 );

