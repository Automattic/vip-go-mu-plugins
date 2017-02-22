<?php

namespace Automattic\VIP\Performance;

const EVENT = 'wpcom_async_publish_post';

/**
 * Queue async event when status was or is `publish`
 *
 * @param string $new_status
 * @param string $old_status
 * @param object $post
 */
function queue_async_publish_post( $new_status, $old_status, $post ) {
	// Pass only consistent data, allowing cron's duplicate handling to take effect
	// For example, don't include post status here
	$args = [ 'post_id' => (int) $post->ID ];

	if ( $new_status !== $old_status && in_array( 'publish', [ $new_status, $old_status ], true ) && false === wp_next_scheduled( EVENT, $args ) ) {
		wp_schedule_single_event( time(), EVENT, $args );
	}
}

add_action( 'transition_post_status', __NAMESPACE__ . '\queue_async_publish_post', 10, 3 );

/**
 * Perform asynchronous tasks for a published post
 *
 * @param int $post_id
 */
function wpcom_async_publish_post( $post_id ) {
	$post_object = get_post( $post_id );

	do_action( 'async_publish_post', $post_id, $post_object );
}

add_action( EVENT, __NAMESPACE__ . '\wpcom_async_publish_post' );
