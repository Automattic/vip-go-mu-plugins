<?php

/* Various functions dealing with how we cache feeds on WordPress.com
 * Feeds are cached upstream from PHP via nginx, so we need to make sure
 * that we invalidate the cache when something happens
 *
 * We also want to make sure we only cache feeds we can invalidate - this means
 * no query strings
 * */


function wpcom_invalidate_post_data( $post_id ) {
	global $wpdb;
	
	// Let's try to skip this when importing - it can queue tons of jobs that are un-necessary
	if ( defined( 'WP_IMPORTING' ) && constant('WP_IMPORTING') )
		return;

	$blog = get_blog_details( $wpdb->blogid );

	// Invalidate feedbag and output cache for WP.com sites
	if ( 1 == $blog->site_id ) { 
		$home = get_option( 'home' );
		update_option( 'feed_invalidated', time() );
		$data = array(
			'feedbag'      => array( 'blog_id' => $wpdb->blogid, 'post_id' => $post_id ),
			'output_cache' => array( 'url' => array( "$home/feed/", "$home/feed/atom/", "$home/sitemap.xml" ) )
		);
	// Only feedbag for Jetpack external sites since we aren't serving their feeds
	} else {
		$has_feed = $wpdb->get_var( $wpdb->prepare( 'SELECT feed_id FROM feedbag WHERE blog_id = %d LIMIT 1', $wpdb->blogid ) );
		if ( ! empty( $has_feed ) )
			$data = array( 'feedbag' => array( 'blog_id' => $wpdb->blogid ) );
	}

	if ( ! empty( $data ) )
		queue_async_job( $data, 'wpcom_invalidate_output_cache_job', -16 );
}

function wpcom_invalidate_feed_cache() {
	$home = get_option( 'home' );
	update_option( 'feed_invalidated', time() );
	$data = array( 'output_cache' => array( 'url' => array( "$home/feed/", "$home/feed/atom/", "$home/sitemap.xml" ) ) );

	queue_async_job( $data, 'wpcom_invalidate_output_cache_job', -16 );
}

function wpcom_invalidate_feed_cache_domains( $ref_blog_id ) {
	// flush cache for all of the mapped domains on the blog
	$feed_urls = wpcom_get_all_blog_domains_with_path( $ref_blog_id, '/feed/', true );
	$atom_urls = wpcom_get_all_blog_domains_with_path( $ref_blog_id, '/feed/atom/', true );
	$sitemap_urls = wpcom_get_all_blog_domains_with_path( $ref_blog_id, '/sitemap.xml', true );

	update_blog_option( $ref_blog_id, 'feed_invalidated', time() );

	$all_feed_urls = array_merge( $feed_urls, $atom_urls, $sitemap_urls );
	if ( ! empty( $all_feed_urls ) )
		queue_async_job( array( 'output_cache' => array ( 'url' => $all_feed_urls ) ), 'wpcom_invalidate_output_cache_job', -16 );
}

/**
 * If a feed has been invalidated, that means we think it's changed.  Make sure the last_modified time
 * in the feed and headers reflects that.
 *
 * Filter on get_lastpostmodified
 *
 * @param string $date MySQL formatted date
 * @param string $timezone 'gmt', 'blog', or 'server'
 * @return string modified $date
 */
function wpcom_get_lastpostmodified( $date, $timezone ) {
	if ( !$last = get_option( 'feed_invalidated' ) )
		return $date;

	// Get $date in GMT
	switch ( $timezone ) {
	case 'gmt' :
		$offset = 0;
		break;
	case 'blog' :
		$offset = 3600 * -1 * get_option( 'gmt_offset' );
		break;
	case 'server' :
		$offset = -1 * date( 'Z' );
		break;
	}

	$date = strtotime( "{$date}+0000 {$offset} seconds" );
	if ( $date < $last )
		$date = $last;

	// Put $date in $timezone
	$date -= $offset;

	return gmdate( 'Y-m-d H:i:s', $date );
}
add_filter( 'pre_get_lastpostmodified', 'wpcom_get_lastpostmodified', 10, 2 );

// Invalidate the feed when we get a new comment. We have comment counts in the feed
add_action( 'wp_update_comment_count', 'wpcom_invalidate_post_data' );

// Invalidate the feed when a post is publised or unpublished (e.g. trashed or set to private or draft)
add_action( 'transition_post_status', 'wpcom_maybe_invalidate_feed_cache_on_post_transition', 10, 3 );
function wpcom_maybe_invalidate_feed_cache_on_post_transition( $new_status, $old_status, $post ) {
	if ( ! in_array( 'publish', array( $old_status, $new_status ) ) )
		return;

	if ( ! in_array( $post->post_type, get_post_types( array( 'public' => true ) ) ) )
		return;
	wpcom_invalidate_post_data( $post->ID );
}

// Invalidate the feed when domain mapping changes happen
add_action( 'wpcom_makeprimaryblog', 'wpcom_invalidate_feed_cache_domains' );

// Invalidate the feed when the blog privacy changes
add_action( 'update_option_blog_public', 'wpcom_invalidate_feed_cache' );

// Invalidate the feed when feed related settings change
add_action( 'update_option_posts_per_rss', 'wpcom_invalidate_feed_cache' );
add_action( 'update_option_rss_use_excerpt', 'wpcom_invalidate_feed_cache' );
add_action( 'update_option_enhanced_feeds', 'wpcom_invalidate_feed_cache' );

// Only do this setup routine for the main WP query
add_action( 'send_headers', 'wpcom_feed_cache_setup' );
function wpcom_feed_cache_setup( &$wp ) {
	if ( !isset( $wp->query_vars['feed'] ) || !$wp->query_vars['feed'] )
		return;

	add_action( 'parse_query', 'wpcom_feed_cache_headers' );
}

// Send X-Accel-Expires headers to tell nginx what to cache and what not to cache
function wpcom_feed_cache_headers( &$query ) {
	global $current_blog;

	// Only do this once
	remove_action( 'parse_query', 'wpcom_feed_cache_headers' );

	// if the X-Accel-Expires header has already been set
	// don't try to over write it
	foreach ( (array) headers_list( ) as $header ) {
		if ( strpos( $header, 'X-Accel-Expires' ) === 0 ) {
			return;
		}
	}

	// Using constants here for safety
	// No cache for private blogs - this needs to be first!
	if ( -1 == $current_blog->public )
		define( WPCOM_FEED_CACHE_EXPIRES, 0 );

	// Longer cache for things we invalidate explicitly
	if ( ( '/feed/' == $_SERVER['REQUEST_URI'] || '/feed/atom/' == $_SERVER['REQUEST_URI'] ) && empty( $_GET ) )
		define( WPCOM_FEED_CACHE_EXPIRES, 31536000 );

	// Default feed expires is 300 seconds for public blogs
	if ( !defined( 'WPCOM_FEED_CACHE_EXPIRES' ) && ( 1 == $current_blog->public || 0 == $current_blog->public ) ) 
		define( WPCOM_FEED_CACHE_EXPIRES, 300 );

	// And 0 for everything else (safer)
	if ( !defined( 'WPCOM_FEED_CACHE_EXPIRES' ) )
		define( WPCOM_FEED_CACHE_EXPIRES, 0 );

	header( 'X-Accel-Expires: ' . WPCOM_FEED_CACHE_EXPIRES );
}
