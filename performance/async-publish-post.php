<?php

namespace Automattic\VIP\Performance;

const EVENT = 'wpcom_async_publish_post';

add_action( 'transition_post_status', 'queue_async_publish_post', 10, 3 );

function queue_async_publish_post( $new_status, $old_status, $post ) {
	// Pass only consistent data, allowing cron's duplicate handling to take effect
	// For example, don't include post status here
	// TODO: When clients can schedule their own events, include consistent name in $args to avoid duplicates
	$args = [ 'post_id' => $post->ID ];

	if ( in_array( 'publish', [ $new_status, $old_status ], true ) && false === wp_next_scheduled( EVENT, $args ) ) {
		wp_schedule_single_event( time(), EVENT, $args );
	}
}
