<?php
/*
 * @wordpress-plugin
 * Plugin Name:       WordPress Importer
 * Plugin URI:        https://wordpress.org/plugins/wordpress-importer/
 * Description:       Import posts, pages, comments, custom fields, categories, tags and more from a WordPress export file.
 * Author:            wordpressdotorg
 * Author URI:        https://wordpress.org/
 * Version:           0.9.7
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Text Domain:       wordpress-importer
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'WP_RUN_CORE_TESTS' ) || ! WP_RUN_CORE_TESTS ) {
	require __DIR__ . '/wordpress-importer/wordpress-importer.php';
}
