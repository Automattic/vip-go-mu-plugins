<?php

namespace Automattic\VIP\Performance;

/**
 * Bulk edits of lots of posts can trigger slow term count queries for each post updated
 */
function defer_term_counting() {
	if ( isset( $_REQUEST['bulk_edit'] ) ) {
		wp_defer_term_counting( true );
		add_action( 'shutdown', function() {
			wp_defer_term_counting( false );
		} );
	}
}

add_action( 'load-edit.php', __NAMESPACE__ . '\defer_term_counting' );

/**
 * Impose our bulk-edit limitations on all registered post types that provide an admin UI
 */
function limit_bulk_edit_for_registered_post_types() {
	$types = get_post_types( array(
		'show_ui' => true,
	) );

	foreach ( $types as $type ) {
		add_action( 'bulk_actions-edit-' . $type, __NAMESPACE__ . '\limit_bulk_edit' );
	}
}

add_action( 'wp_loaded', __NAMESPACE__ . '\limit_bulk_edit_for_registered_post_types' );

/**
 * Suppress bulk actions when too many posts would be affected
 *
 * Often causes database issues when too many posts are modified,
 * but since Core expects this to work with 20 posts, we limit to the same.
 */
function limit_bulk_edit( $bulk_actions ) {
	$per_page = get_query_var( 'posts_per_page' );

	// Core defaults to 20 posts per page
	// If requesting more, or all entries, hide bulk actions
	if ( $per_page > 20 || -1 === $per_page ) {
		$bulk_actions = array();
	}

	return $bulk_actions;
}
