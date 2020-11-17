<?php
/*
 * Plugin Name: Test jQuery Updates
 * Plugin URI: https://wordpress.org/plugins/wp-jquery-update-test
 * Description: A feature plugin to help with testing updates of the jQuery and jQuery UI JavaScript libraries (not intended for use in production).
 * Version: 1.0.1
 * Requires at least: 5.4
 * Tested up to: 5.5
 * Requires PHP: 5.6
 * Author: The WordPress Team
 * Author URI: https://wordpress.org
 * Contributors: wordpressdotorg, azaozz
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-jquery-update-test
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

// Only for WP 5.5.x or lower (for now)
if ( version_compare( $GLOBALS['wp_version'], '5.6-alpha', '<' ) ) {
	include_once __DIR__ . '/class_wp_jquery_update_test.php';
}
