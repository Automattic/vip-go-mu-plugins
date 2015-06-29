<?php

add_action( 'post_syndicated_item', 'fwp_track_affected_post_ids' );
add_action( 'update_syndicated_item', 'fwp_track_affected_post_ids' );

function fwp_track_affected_post_ids( $post_id ) {
	global $fwp_affected_posts;

	if ( ! isset( $fwp_affected_posts ) )
		$fwp_affected_posts = array();

	$fwp_affected_posts[] = $post_id;

	im( $fwp_affected_posts );
}

add_action( 'update_syndicated_feed_items', 'fwp_flush_affected_post_ids' );

function fwp_flush_affected_post_ids( $link_id ) {
	global $fwp_affected_posts;

	if ( ! isset( $fwp_affected_posts ) )
		return;

	$fwp_affected_posts = array_unique( $fwp_affected_posts );

	$data = new stdClass;
	$data->posts = $fwp_affected_posts;
	queue_async_job( $data, 'async_clean_post_cache' );

	// Reset for the next feed
	unset( $fwp_affected_posts );
}
