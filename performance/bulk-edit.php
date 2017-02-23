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
 * Determine if bulk editing should be blocked
 */
function bulk_editing_is_limited() {
	$per_page = get_query_var( 'posts_per_page' );

	// Core defaults to 20 posts per page
	// If requesting more--or all--entries, hide bulk actions
	return $per_page > 20 || -1 === $per_page;
}

/**
 * Impose our bulk-edit limitations on all registered post types that provide an admin UI
 */
function limit_bulk_edit_for_registered_post_types() {
	$types = get_post_types( array(
		'show_ui' => true,
	) );

	foreach ( $types as $type ) {
		add_action( 'bulk_actions-edit-' . $type, __NAMESPACE__ . '\limit_bulk_edit' );
		add_action( 'admin_notices', __NAMESPACE__ . '\bulk_edit_admin_notice' );
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
	if ( bulk_editing_is_limited() ) {
		$bulk_actions = array();
	}

	return $bulk_actions;
}

/**
 * Display a dismissible admin notice when bulk editing is disabled
 */
function bulk_edit_admin_notice() {
	if ( ! bulk_editing_is_limited() ) {
		return;
	}

	// HTML class doubles as key used to track dismissed notices
	$id = 'notice-vip-bulk-edit-limited';

	$dismissed_pointers = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
	if ( in_array( $id, $dismissed_pointers, true ) ) {
		return;
	}

	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="notice notice-error is-dismissible">
		<p><?php _e( 'Bulk actions are disabled due to the number of items displayed in the table below.', 'wpcom-vip' ); ?></p>

		<script>jQuery(document).ready( function($) { $( '#<?php echo esc_js( $id ); ?>' ).on( 'remove', function() {
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				xhrFields: {
					withCredentials: true
				},
				data: {
					action: 'dismiss-wp-pointer',
					pointer: '<?php echo esc_js( $id ); ?>'
				}
			} );
		} ) } );</script>
	</div>
	<?php
}
