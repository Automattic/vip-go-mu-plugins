<?php

namespace Automattic\VIP\Performance;

// Bulk edits of lots of posts can trigger slow term count queries for each post updated
add_action( 'load-edit.php', function() {
	if ( isset( $_REQUEST['bulk_edit'] ) ) {
		wp_defer_term_counting( true );
		add_action( 'shutdown', function() {
			wp_defer_term_counting( false );
		} );
	}
} );

class Bulk_Edit {
	/**
	 * Generic callback to register class's hooks
	 */
	public static function register_hooks() {
		add_action( 'bulk_actions-edit-post', array( __CLASS__, 'limit_bulk_edit' ) );
	}

	/**
	 * Suppress bulk actions when too many posts would be affected
	 *
	 * Often causes database issues when too many posts are modified,
	 * but since Core expects this to work with 20 posts, we limit to the same.
	 */
	public static function limit_bulk_edit( $bulk_actions ) {
		$posts_per_page = get_query_var( 'posts_per_page' );

		// Core defaults to 20 posts per page
		// If requesting more, hide bulk actions
		if ( $posts_per_page > 20 ) {
			$bulk_actions = array();
		}

		return $bulk_actions;
	}
}

Bulk_Edit::register_hooks();
