<?php
/*
 * @wordpress-plugin
 * Plugin Name:       WordPress Importer
 * Plugin URI:        https://wordpress.org/plugins/wordpress-importer/
 * Description:       Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file.
 * Author:            wordpressdotorg
 * Author URI:        https://wordpress.org/
 * Version:           0.8.4
 * Requires at least: 5.2
 * Requires PHP:      5.6
 * Text Domain:       wordpress-importer
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'WP_RUN_CORE_TESTS' ) || ! WP_RUN_CORE_TESTS ) {
	require __DIR__ . '/wordpress-importer/wordpress-importer.php';

	add_action( 'import_start', function () {
		if ( ! defined( 'WP_IMPORTING' ) || ! WP_IMPORTING ) {
			// Safety check: Don't suspend cache invalidation if we're not importing
			return;
		}

		wp_suspend_cache_addition( true );
		wp_suspend_cache_invalidation( true );
		wp_cache_flush();
	});

	add_action( 'import_end', function () {
		if ( ! defined( 'WP_IMPORTING' ) || ! WP_IMPORTING ) {
			// Safety check: Don't suspend cache invalidation if we're not importing
			return;
		}

		wp_suspend_cache_addition( false );
		wp_suspend_cache_invalidation( false );
		wp_cache_flush();
	});
}
