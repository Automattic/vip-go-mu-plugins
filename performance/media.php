<?php

require_once 'class-mime-types-caching.php';

// Prevent core from doing filename lookups for media search.
// https://core.trac.wordpress.org/ticket/39358
function vip_filter_query_attachment_filenames() {
	add_filter( 'wp_allow_query_attachment_by_filename', '__return_false', PHP_INT_MAX );
}

if ( ! defined( 'WP_RUN_CORE_TESTS' ) || ! WP_RUN_CORE_TESTS ) {
	// This breaks query search tests.
	add_action( 'pre_get_posts', 'vip_filter_query_attachment_filenames' );
}


function vip_cache_mime_types() {
	\Automattic\VIP\Performance\Mime_Types_Caching::init();
}

add_action( 'init', 'vip_cache_mime_types' );
