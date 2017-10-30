<?php

/*
Plugin Name: WordPress Importer
Plugin URI: http://wordpress.org/extend/plugins/wordpress-importer/
Description: Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file.
Author: wordpressdotorg
Author URI: http://wordpress.org/
Version: 0.6.3
Text Domain: wordpress-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

require( __DIR__ . '/wordpress-importer/wordpress-importer.php' );

add_action( 'import_start', function() {
	if ( ! defined( 'WP_IMPORTING' ) || ! WP_IMPORTING ) {
		// Safety check: Don't suspend cache invalidation if we're not importing
		return;
	}

	wp_suspend_cache_addition( true );
	wp_suspend_cache_invalidation( true );
	wp_cache_flush();
});

add_action( 'import_end', function() {
	if ( ! defined( 'WP_IMPORTING' ) || ! WP_IMPORTING ) {
		// Safety check: Don't suspend cache invalidation if we're not importing
		return;
	}

	wp_suspend_cache_addition( false );
	wp_suspend_cache_invalidation( false );
	wp_cache_flush();
});
