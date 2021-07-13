<?php

/**
 * Purge the Varnish cache for a liveblog on each new entry.
 *
 * Ensures that a Liveblog page isn't cached with stale meta data during an
 * active liveblog.
 *
 * @param  int $comment_id ID of the comment for this new entry.
 * @param  int $post_id    ID for this liveblog post.
 */
function wpcom_vip_liveblog_purge_on_new_entries( int $comment_id, int $post_id ){

	if ( ! function_exists( 'wpcom_vip_purge_edge_cache_for_url' ) ) {
		return;
	}

	// Get the URL for this Liveblog post.
	$permalink = get_permalink( absint( $post_id ) );
	if ( ! $permalink ) {
		return;
	}

	// Purge the Varnish cache for the liveblog post so that new loads of the
	// post include the newest entries.
	wpcom_vip_purge_edge_cache_for_url( $permalink );

}
add_action( 'liveblog_insert_entry', 'wpcom_vip_liveblog_purge_on_new_entries', 10, 2 );
