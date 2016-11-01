<?php
/**
 * Sets the SimplePie Cache Class to our custom VIP Go Class.
 *
 * @param object &$feed SimplePie feed object, passed by reference.
 * @param mixed  $url   URL of feed to retrieve. If an array of URLs, the feeds are merged.
 *
 * @return void
 */
function vipgo_feed_options( &$feed, $url ) {
	require_once( 'vip-feed-cache/vipgo-feed-cache-classes.php' );

	$feed->set_cache_class( 'VIPGO_Feed_Cache' );
}

add_action( 'wp_feed_options', 'vipgo_feed_options', 10, 2 );

