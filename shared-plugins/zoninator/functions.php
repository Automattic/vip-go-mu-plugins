<?php

function z_get_zoninator() {
	global $zoninator;
	return $zoninator;
}

/**
 * Get a list of all zones
 * @return array List of all zones
 */
function z_get_zones() {
	return z_get_zoninator()->get_zones();
}

/**
 * @param $zone int|string ID or Slug of the zone
 * @return array Zone object
 */
function z_get_zone( $zone ) {
	return z_get_zoninator()->get_zone( $zone );
}

/**
 * @param $zone int|string ID or Slug of the zone
 * @param $args array override default zoninator args
 * @return array List of orders post objects
 */
function z_get_posts_in_zone( $zone, $args = array() ) {
	return z_get_zoninator()->get_zone_posts( $zone, $args );
}

/**
 * @param $zone int|string ID or Slug of the zone
 * @return WP_Query List of orders post objects
 */
function z_get_zone_query( $zone, $args = array() ) {
	return z_get_zoninator()->get_zone_query( $zone, $args );
}

/**
 * @param $zone int|string ID or Slug of the zone
 * @param $post_id int ID of the post (or, null if in The Loop)
 * @return array|false Returns next post relative to post_id for the given zone
 */
function z_get_next_post_in_zone( $zone, $post_id = 0 ) {
	$post_id = z_get_loop_post_id_or_default( $post_id );
	return z_get_zoninator()->get_next_post_in_zone( $zone, $post_id );
}

/**
 * @param $zone int|string ID or Slug of the zone
 * @param $post_id int ID of the post (or, null if in The Loop)
 * @return array|false Returns previous post relative to post_id for the given zone 
 */
function z_get_prev_post_in_zone( $zone, $post_id = 0 ) {
	$post_id = z_get_loop_post_id_or_default( $post_id );
	return z_get_zoninator()->get_prev_post_in_zone( $zone, $post_id );
}

/**
 * @param $post_id int ID of the post (or, null if in The Loop)
 * @return array List of of zones that the given post is in
 */
function z_get_post_zones( $post_id = 0 ) {
	$post_id = z_get_loop_post_id_or_default( $post_id );
	return z_get_zoninator()->get_zones_for_post( $post_id );
}

function z_get_loop_post_id_or_default( $post_id = 0 ) {
	if( ! $post_id ) {
		global $post;
		if( $post && isset( $post->ID ) ) $post_id = $post->ID;
	}
	return $post_id;
}

/**
 * Handy function to disable the locking mechanism
 */
function z_disable_zoninator_locks() {
	return -1;
}

// (Should probably publicly expose set_zone_posts as well, e.g. if we wanted to add a metabox on the Edit Post page)
