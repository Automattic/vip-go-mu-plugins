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
