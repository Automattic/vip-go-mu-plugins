<?php

namespace Automattic\VIP\Performance;

const ASYNC_PUBLISH_EVENT = 'wpcom_async_publish_post';

/**
 * Queue async event when status was or is `publish`
 *
 * @param string $new_status
 * @param string $old_status
 * @param object $post
 */
function _queue_async_publish_post( $new_status, $old_status, $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		return;
	}

	// Pass only consistent data, allowing cron's duplicate handling to take effect
	// For example, don't include post status here
	$args = [ 'post_id' => (int) $post->ID ];

	if ( $new_status !== $old_status && in_array( 'publish', [ $new_status, $old_status ], true ) && false === wp_next_scheduled( ASYNC_PUBLISH_EVENT, $args ) ) {
		wp_schedule_single_event( time(), ASYNC_PUBLISH_EVENT, $args );
	}
}

add_action( 'transition_post_status', __NAMESPACE__ . '\_queue_async_publish_post', 10, 3 );

/**
 * Perform asynchronous tasks for a published post
 *
 * @param int $post_id
 */
function _wpcom_do_async_publish_post( $post_id ) {
	$post_object = get_post( $post_id );

	do_action( 'async_publish_post', $post_id, $post_object );
}

add_action( ASYNC_PUBLISH_EVENT, __NAMESPACE__ . '\_wpcom_do_async_publish_post' );

/**
 * Offload ping- and enclosure-related events
 */
remove_action( 'publish_post', '_publish_post_hook', 5, 1 );
add_action( 'async_publish_post', '_publish_post_hook', 5, 1 );
