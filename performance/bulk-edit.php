<?php

namespace Automattic\VIP\Performance;

const BULK_EDIT_LIMIT = 40;

/**
 * Bulk edits of lots of posts can trigger slow term count queries for each post updated
 */
function defer_term_counting() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is not available
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
	// Do not hide bulk edit actions if number of total entries is less than 20, core's default.
	if ( isset( $GLOBALS['wp_query'] ) && is_a( $GLOBALS['wp_query'], 'WP_Query' ) ) {
		$total_posts = $GLOBALS['wp_query']->found_posts;

		if ( isset( $total_posts ) && BULK_EDIT_LIMIT > $total_posts ) {
			return false;
		}
	}

	$per_page = get_query_var( 'posts_per_page' );

	// Hierarchical post types set `posts_per_page` to -1 during the original query, but they still respect the per_page value in the posts list table.
	if ( isset( $GLOBALS['wp_list_table'] ) && is_a( $GLOBALS['wp_list_table'], 'WP_Posts_List_Table' ) ) {
		$per_page = isset( $GLOBALS['wp_list_table']->_pagination_args['per_page'] ) ? $GLOBALS['wp_list_table']->_pagination_args['per_page'] : $per_page;
	}

	// If requesting all entries, or more than 20, hide bulk actions
	if ( -1 === $per_page ) {
		return true;
	}

	return $per_page > BULK_EDIT_LIMIT;
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
	$mailto        = 'mailto:vip-support@wordpress.com?subject=' . urlencode( $email_subject );
	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="notice notice-error is-dismissible">
		<p>
		<?php
		printf(
			/* translators: 1: number of items, 2: email address */
			__( 'Bulk actions are disabled because more than %1$s items were requested. To re-enable bulk edit, please adjust the "Number of items" setting under <em>Screen Options</em>. If you have a large number of posts to update, please <a href="%2$s">get in touch</a> as we may be able to help.', 'wpcom-vip' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML string
			esc_html( number_format_i18n( BULK_EDIT_LIMIT ) ),
			esc_url( $mailto )
		);
		?>
		</p>

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
