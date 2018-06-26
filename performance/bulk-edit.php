<?php

namespace Automattic\VIP\Performance;

// Core defaults to 20, so let's assume that's safe
const BULK_EDIT_LIMIT = 20;

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

	// Get total number of entries
	$post_type = get_query_var( 'post_type' );
	$num_posts = wp_count_posts( $post_type, 'readable' );
	$total_posts = array_sum( (array) $num_posts );

	// Core defaults to 20 posts per page
	// If requesting more--or all--entries, hide bulk actions
	// Except, do not hide bulk actions if less than 20 total entries
	if ( BULK_EDIT_LIMIT > $total_posts ) {
		return false;
	} else {
		return $per_page > BULK_EDIT_LIMIT || -1 === $per_page;
	}
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

	$email_subject = sprintf( '[%s] Bulk Edit Help', home_url() );
	$mailto = 'mailto:vip-support@wordpress.com?subject=' . urlencode( $email_subject );
	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="notice notice-error is-dismissible">
		<p><?php printf( __( 'Bulk actions are disabled because more than %s items were requested. To re-enable bulk edit, please adjust the "Number of items" setting under <em>Screen Options</em>. If you have a large number of posts to update, please <a href="%s">get in touch</a> as we may be able to help.', 'wpcom-vip' ), number_format_i18n( BULK_EDIT_LIMIT ), esc_url( $mailto ) ); ?></p>

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
