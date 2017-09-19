<?php

namespace Automattic\VIP\Performance;

const ASYNC_TRANSITION_EVENT = 'wpcom_vip_async_transition_post_status';

/**
 * Perform asynchronous tasks for a published post
 *
 * @access private
 *
 * @param int    $post_id Post ID.
 * @param string $new_status New post status.
 * @param string $old_status Previous post status.
 */
function _wpcom_do_async_transition_post_status( $post_id, $new_status, $old_status ) {
	$post = get_post( $post_id );

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	do_action( 'async_transition_post_status', $new_status, $old_status, $post );

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * The dynamic portions of the hook name, `$new_status` and `$old status`,
	 * refer to the old and new post statuses, respectively.
	 *
	 * @param \WP_Post $post Post object.
	 */
	do_action( "async_{$old_status}_to_{$new_status}", $post );

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * The dynamic portions of the hook name, `$new_status` and `$post->post_type`,
	 * refer to the new post status and post type, respectively.
	 *
	 * Please note: When this action is hooked using a particular post status (like
	 * 'publish', as `publish_{$post->post_type}`), it will fire both when a post is
	 * first transitioned to that status from something else, as well as upon
	 * subsequent post updates (old and new status are both the same).
	 *
	 * Therefore, if you are looking to only fire a callback when a post is first
	 * transitioned to a status, use the {@see 'transition_post_status'} hook instead.
	 *
	 * @param int     $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	do_action( "async_{$new_status}_{$post->post_type}", $post->ID, $post );
}
add_action( ASYNC_TRANSITION_EVENT, __NAMESPACE__ . '\_wpcom_do_async_transition_post_status', 10, 3 );

/**
 * Skip offloading in certain contexts
 */
if (
	wp_doing_cron() ||
	( defined( 'WP_CLI' ) && \WP_CLI ) ||
	( defined( 'XMLRPC_REQUEST' ) && \XMLRPC_REQUEST ) ||
	( defined( 'WP_IMPORTING' ) && \WP_IMPORTING )
) {
	/**
	 * Bypass offloading to the async hook, unless specifically requested
	 *
	 * @param bool $bypass Whether or not to bypass async offloading.
	 */
	if ( ! apply_filters( 'wpcom_async_transition_post_status_force_queue_event', false ) ) {
		return;
	}
}

/**
 * Queue async event when status was or is `publish`
 *
 * @access private
 *
 * @param string $new_status New post status.
 * @param string $old_status Old post status.
 * @param object $post \WP_Post object.
 */
function _queue_async_hooks( $new_status, $old_status, $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		return;
	}

	// Pass only consistent data, allowing cron's duplicate handling to take effect.
	// For example, don't include post status here.
	$args = [
		'post_id'    => (int) $post->ID,
		'new_status' => $new_status,
		'old_status' => $old_status,
	];

	if ( $new_status !== $old_status && in_array( 'publish', [ $new_status, $old_status ], true ) && false === wp_next_scheduled( ASYNC_TRANSITION_EVENT, $args ) ) {
		wp_schedule_single_event( time(), ASYNC_TRANSITION_EVENT, $args );
	}
}
add_action( 'transition_post_status', __NAMESPACE__ . '\_queue_async_hooks', 10, 3 );

/**
 * Offload ping- and enclosure-related events
 */
remove_action( 'publish_post', '\_publish_post_hook', 5 );
add_action( 'async_publish_post', '\_publish_post_hook', 5, 1 );
