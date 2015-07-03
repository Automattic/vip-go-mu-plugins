<?php
/*
Plugin Name: JSON feed
Plugin URI: http://wordpress.org/extend/plugins/json-feed/
Description: Provides feeds in JSON form
Version: 1.3
Author: Chris Northwood (modified by Dan Phiffer)
Author URI: http://www.pling.org.uk/
*/

add_filter( 'query_vars', 'json_feed_queryvars' );
function json_feed_queryvars( $qvars ) {
	$qvars[] = 'jsonp';
	$qvars[] = 'date_format';
	$qvars[] = 'remove_uncategorized';
	return $qvars;
}

add_action('do_feed_json', 'json_feed');
function json_feed() {
	global $wp_query;

	$query_args = $wp_query->query;

	// Required in order for things like posts_per_page to work
	unset( $query_args['feed'] );

	$query_args = apply_filters( 'json_feed_query_args', $query_args );

	$output = array();
	$json_feed = new WP_Query( $query_args );
	if ( $json_feed->have_posts() ) {
		while ( $json_feed->have_posts() ) {
			$json_feed->the_post();

			$item = json_feed_post_item();

			$item = apply_filters( 'json_feed_item', $item, get_the_ID(), $query_args, $json_feed );

			$output[] = $item;

			// This custom filter is deprecated
			$output = apply_filters( 'json_feed_output', $output);
		}
	}

	$output = apply_filters( 'json_feed_output_items', $output, $query_args, $json_feed );

	if ( get_query_var('jsonp') == '' ) {
		header('Content-Type: application/json; charset=' . get_option('blog_charset'), true);
		echo json_encode($output);
	} else {
		header('Content-Type: application/javascript; charset=' . get_option('blog_charset'), true);
		echo preg_replace( '/[^\w.]/', '', get_query_var('jsonp') ) . '(' . json_encode($output) . ')';
	}
}

/**
 * Helper to build an array of post data to be JSON encoded
 *
 * NOTE: required to be called in a loop
 *
 * @return array
 */
function json_feed_post_item() {

	$featured_image_url = wp_get_attachment_url( get_post_thumbnail_id( get_the_ID() ) );

	return array(
		'id'                 => (int) get_the_ID(),
		'type'               => get_post_type(), // WPCOM: post_type seems like a useful thing to have here
		'permalink'          => get_permalink(),
		'title'              => get_the_title(),
		'author'			 => get_the_author(),
		'content'            => do_shortcode( get_the_content() ), // WPCOM: process shortcodes (don't run the_content as it calls wpautop)
		'excerpt'            => get_the_excerpt(),
		'featured_image_url' => ( $featured_image_url ) ? $featured_image_url : '',
		'date'               => get_the_time(json_feed_date_format()),
		'categories'         => json_feed_categories(),
		'tags'               => json_feed_tags()
	);

}

function json_feed_date_format() {
	if (get_query_var('date_format')) {
		return get_query_var('date_format');
	} else {
		return 'F j, Y H:i';
	}
}

function json_feed_categories() {
	$categories = get_the_category();
	if ( is_array( $categories ) ) {
		$categories = array_values($categories);
		if ( get_query_var('remove_uncategorized') ) {
			$categories = array_filter($categories, 'json_feed_remove_uncategorized');
		}
		return array_map('json_feed_format_category', $categories);
	} else {
		return array();
	}
}

function json_feed_remove_uncategorized( $category ) {
	if ($category->cat_ID == 1 && $category->slug == 'uncategorized') {
		return false;
	} else {
		return true;
	}
}

function json_feed_format_category( $category ) {
	return array(
		'id' => (int) $category->cat_ID,
		'title' => $category->cat_name,
		'slug' => $category->slug
	);
}

function json_feed_tags() {
	$tags = get_the_tags();
	if ( is_array( $tags ) ){
		$tags = array_values($tags);
		return array_map('json_feed_format_tag', $tags);
	} else {
		return array();
	}
}

function json_feed_format_tag( $tag ) {
	return array(
		'id' => (int) $tag->term_id,
		'title' => $tag->name,
		'slug' => $tag->slug
	);
}

?>
