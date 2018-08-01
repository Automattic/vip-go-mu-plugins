<?php

/*
Plugin Name: Async Publish Actions
Description: Allow for async processing of tasks normally hooked to certain `transition_post_status` calls.
Author: Automattic
Author URI: http://automattic.com/
*/

namespace Automattic\VIP\Async_Publish_Actions;

const ASYNC_TRANSITION_EVENT = 'wpcom_vip_async_transition_post_status';

/**
 * Check if the current context is suitable for offloading
 *
 * @return bool
 */
function should_offload() {
	// Offload by default.
	$context = null;
	$offload = true;

	if ( wp_doing_cron() ) {
		$context = 'cron';
		$offload = false;
	} elseif ( defined( 'WP_CLI' ) && \WP_CLI ) {
		$context = 'wp-cli';
		$offload = false;
	} elseif ( defined( 'XMLRPC_REQUEST' ) && \XMLRPC_REQUEST ) {
		$context = 'xml-rpc';
		$offload = false;
	} elseif ( defined( 'WP_IMPORTING' ) && \WP_IMPORTING ) {
		$context = 'importing';
		$offload = false;
	}

	/**
	 * Filter if the current request is suitable for offloading
	 *
	 * @param bool   $bypass Whether or not to offload.
	 * @param string $context Request context that blocked offloading.
	 */
	return apply_filters( 'wpcom_async_transition_post_status_should_offload', $offload, $context );
}

/**
 * Cron callback to perform asynchronous tasks for a published post
 *
 * @access private
 *
 * @param int    $post_id Post ID.
 * @param string $new_status New post status.
 * @param string $old_status Previous post status.
 */
function _wpcom_do_async_transition_post_status( $post_id, $new_status, $old_status ) {
	$post = get_post( $post_id );

	// If post status has changed since this was queued, abort and let the next event handle this post.
	if ( $new_status !== get_post_status( $post ) ) {
		return;
	}

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

	if ( false !== wp_next_scheduled( ASYNC_TRANSITION_EVENT, $args ) ) {
		return;
	}

	if ( $new_status !== $old_status && in_array( 'publish', [ $new_status, $old_status ], true ) ) {
		wp_schedule_single_event( time(), ASYNC_TRANSITION_EVENT, $args );
	}
}

/**
 * Maybe schedule offloading.
 */
if ( should_offload() ) {
	// Trigger offloading.
	add_action( 'transition_post_status', __NAMESPACE__ . '\_queue_async_hooks', 10, 3 );

	/**
	 * Offload ping- and enclosure-related events
	 */
	remove_action( 'publish_post', '\_publish_post_hook', 5 );
	add_action( 'async_publish_post', '\_publish_post_hook', 5, 1 );
}

/**
 * Hook regardless `should_offload()`, lest unrelated requests be impacted
 */
add_action( ASYNC_TRANSITION_EVENT, __NAMESPACE__ . '\_wpcom_do_async_transition_post_status', 10, 3 );
