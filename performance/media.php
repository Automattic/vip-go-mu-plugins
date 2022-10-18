<?php

// Prevent core from doing filename lookups for media search.
// https://core.trac.wordpress.org/ticket/39358
function vip_filter_query_attachment_filenames() {
	add_filter( 'wp_allow_query_attachment_by_filename', '__return_false', PHP_INT_MAX );
}

if ( ! defined( 'WP_RUN_CORE_TESTS' ) || ! WP_RUN_CORE_TESTS ) {
	// This breaks query search tests.
	add_action( 'pre_get_posts', 'vip_filter_query_attachment_filenames' );
}
