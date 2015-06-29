<?php
/*
Plugin Name: MostPopular Feed
Description: This plugin adds a most popular feed. It attaches to the rss2_head hook and alters the query according to the needs. It uses the WordPress.com stats system to determine top posts. Alterations of the feedoutput can be done using one of the various hooks provided in wp-includes/feed-rss2.php (see example below). This plugin is coded for WordPress.com VIP setups. $_GET - parameters "includepages", "limit" (limit <=100 ) and "duration" (days < 90) exist to alter content of rss feed. To match the content to the most-popular sidebar widget use duration=2. For site admins ( Automattic - staff ) the $_GET - paramter "forceupdate=1" updates the rewrite_rules option and "printrules=1" provides output of the existing rewrite rules for siteadmins so they can be copied easily.
Version: 1.01
Author: Thorsten Ott
*/

function mostpopular_feed_query( $args = array() ) {
    if ( !function_exists( 'stats_get_daily_history' ) ) {
		die( 'Call to undefined function stats_get_daily_history().' );
	}

	global $wpdb, $filtered_post_ids, $mostpopular_duration;
    
	$args['cachelife'] = 3600;
	$args['includepages'] = (bool) ( ( isset( $_GET['includepages'] ) ) && ( !empty( $_GET['includepages'] ) ) ) ? $_GET['includepages'] : false;
	$args['limit'] = (int) ( ( isset( $_GET['limit'] ) ) && ( !empty( $_GET['limit'] ) ) ) ? $_GET['limit'] : 10;
	
	if ( ( $args['limit'] >= 100 ) || ( $args['limit'] == -1 ) ) { 
		$args['limit'] = 100;
	}

	if ( $args['limit'] == 0 ) { 
		$args['limit'] = 10;
	}

    $args['duration'] = (int) ( ( isset( $_GET['duration'] ) ) && ( !empty( $_GET['duration'] ) ) ) ? $_GET['duration'] : 90;
    $mostpopular_duration = apply_filters( "mostpopular_max_duration", $args['duration'] );

	$cacheid = md5( "" . __FUNCTION__ . "|{$args['limit']}|{$args['duration']}|{$args['includepages']}|{$args['cachelife']}" );

    $filtered_post_ids = wp_cache_get( $cacheid, 'output' );
		
	if ( empty( $filtered_post_ids ) ) {

        $topposts = array();

        $topposts = array_shift(stats_get_daily_history(false, $wpdb->blogid, 'postviews', 'post_id', false, $mostpopular_duration, '', 100, true));

		if ( $topposts ) {

            $tpcacheid = md5( "topposts_" . $mostpopular_duration . '_' . $wpdb->blogid );
            wp_cache_add( $tpcacheid, $topposts, 'output', $args['cachelife'] );
            
			foreach ( $topposts as $id => $views ) {
				$post = get_post( $id );
				
				if ( empty( $post ) ) {
    					$post = get_page( $id );
				}
		
				if ( $post->post_status == 'publish' ) {
                    if ( ( $post->post_type == 'post' ) || ( $args['includepages'] ) ) {
                        $filtered_post_ids[] = $id;
					}
				}
			}
		}
		
		if ( $args['limit'] >= 0 ) { 
			$filtered_post_ids = array_slice( $filtered_post_ids, 0, $args['limit'] );
		}
		
        wp_cache_add( $cacheid, $filtered_post_ids, 'output', $args['cachelife'] );
	}

    query_posts( array( "post__in" => $filtered_post_ids, "orderby" => "none", "feed" => 1, 'posts_per_page' => count( $filtered_post_ids ) ) );
    
}

function mostpopular_max_duration( $my_duration ) {
    global $mostpopular_duration;
    if ( (int) $my_duration > 90 || (int) $my_duration < 0 )
        $mostpopular_duration = 90;
    if ( (int) $my_duration == 0 || empty( $my_duration ) )
        $mostpopular_duration = 90;
    else
        $mostpopular_duration = (int) $my_duration;
    return $mostpopular_duration;
}

function mostpopular_order_by_views( $orderby ) {
    global $wpdb,$filtered_post_ids;

    if ( !empty( $filtered_post_ids ) ) {
		$filtered_post_ids = array_filter( $filtered_post_ids, 'is_numeric' );

        $posts_order = join( ",", $filtered_post_ids );
        $orderby = "FIELD($wpdb->posts.ID, $posts_order)";
    }

    return $orderby;
}

function mostpopular_feed() {
    global $post;
    do_action( "mostpopular_adjust" );

    add_filter( "mostpopular_max_duration", "mostpopular_max_duration", 0, 1 );
    add_action( "rss2_head", "mostpopular_feed_query" );
    require_once( ABSPATH . "/wp-includes/feed-rss2.php" );
}

function add_mostpopular_feed() {
    global $wp_rewrite, $wpdb;

    $rules = get_option( "rewrite_rules" );

    if ( isset( $_GET['forceupdate'] ) && 1 == $_GET['forceupdate'] ) {
        add_feed('mostpopular', 'mostpopular_feed');
        $wp_rewrite->flush_rules();
        if ( $rules != $wp_rewrite->rules ) {
            update_option( "rewrite_rules", $wp_rewrite->rules );
        }
        
    } else {
        add_feed('mostpopular', 'mostpopular_feed');
    }

}
add_action( "init", "add_mostpopular_feed" );




//
// A simple demo on how adjustments to the output can be done within the theme without altering the plugin
// This just attach filters to the action mostpopular_adjust
// this example adds the views to the title in the feed
//
function mostpopular_adjustments() {
    // adds the views to the title
    add_filter( "the_title_rss", "mostpopular_adjust_title", 100, 1 );
    // make sure posts order is preserved and posts are ordered with top viewed posts first.
    add_filter( "posts_orderby", "mostpopular_order_by_views" );
}

// remove this action in order to de-activate alterations to title and ordering
add_action( "mostpopular_adjust", "mostpopular_adjustments" );

function mostpopular_adjust_title( $title = '' ) {
    if ( !function_exists( 'stats_get_daily_history' ) ) {
		die( 'Call to undefined function stats_get_daily_history().' );
	}

    global $post, $wpdb, $mostpopular_duration;
    $mostpopular_duration = apply_filters( "mostpopular_max_duration", $mostpopular_duration );
    
    $tpcacheid = md5( "topposts_" . $mostpopular_duration . $wpdb->blogid );
    $topposts = wp_cache_get( $tpcacheid, 'output' );
    // just in the case we do not have a cache hit fill it 
    if ( empty( $topposts ) ) {
        $topposts = array_shift(stats_get_daily_history(false, $wpdb->blogid, 'postviews', 'post_id', false, $mostpopular_duration, '', 100, true));

        if ( ! empty( $topposts ) )
            wp_cache_add($tpcacheid, $topposts, 'output', 3600);
    }

    $title_addon = '';

    if ( isset( $topposts[ $post->ID ] ) )
        $title_addon = " (" . $topposts[ $post->ID ] . " " . __("views") . ")";
    
    return $title . $title_addon;
}

?>
