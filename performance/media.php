<?php

// Prevent core from doing filename lookups for media search.
// https://core.trac.wordpress.org/ticket/39358
if( ! ( defined( 'VIP_GO_ENABLE_FILENAMES_SEARCH' ) && VIP_GO_ENABLE_FILENAMES_SEARCH ) ) {
	add_action( 'pre_get_posts', function() {
		remove_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
	} );
}
