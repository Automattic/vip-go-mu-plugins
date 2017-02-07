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
