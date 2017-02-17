<?php

namespace Automattic\VIP\Performance;

const EVENT = 'wpcom_async_publish_post';

// TODO: should this be save_post to catch publish => not (e.g. trash)
add_action( 'publish_post', 'queue_async_publish_post' );

function queue_async_publish_post( $post ) {
	$args = [ 'post_id' => $post->ID ]; // TODO: add before/after status?
	if ( ! wp_next_scheduled( EVENT, $args ) ) {
		wp_schedule_single_event( time(), EVENT, $args );  
	}
}
