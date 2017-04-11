<?php

// Prevent core from doing filename lookups for media search.
// https://core.trac.wordpress.org/ticket/39358
add_action( 'pre_get_posts', function() {
	remove_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
} );
