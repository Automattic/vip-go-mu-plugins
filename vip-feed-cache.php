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
	require_once __DIR__ . '/vip-feed-cache/class-vip-go-feed-transient.php';

	SimplePie_Cache::register( 'vip_go_feed_cache', VIP_Go_Feed_Cache_Transient::class );
	$feed->set_cache_location( 'vip_go_feed_cache' );
}

add_action( 'wp_feed_options', 'vipgo_feed_options' );
