<?php
/*
Plugin Name: Playbuzz
Plugin URI:  https://www.playbuzz.com/
Description: Embed customized playful content from Playbuzz.com into your WordPress site
Version:     0.9.0
Author:      Playbuzz
Author URI:  https://www.playbuzz.com/
Text Domain: playbuzz
Domain Path: /lang
*/



/*
 * Exit if file accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/*
 * Include plugin files
 */
include_once ( plugin_dir_path( __FILE__ ) . 'i18n.php' );           // Add Internationalization support
include_once ( plugin_dir_path( __FILE__ ) . 'admin.php' );          // Add Admin Page
include_once ( plugin_dir_path( __FILE__ ) . 'scripts-styles.php' ); // Load Scripts and Styles
include_once ( plugin_dir_path( __FILE__ ) . 'oembed.php' );         // Add oEmbed support
include_once ( plugin_dir_path( __FILE__ ) . 'shortcodes.php' );     // Add WordPress Shortcodes
include_once ( plugin_dir_path( __FILE__ ) . 'widgets.php' );        // Add WordPress Sidebar Widgets
include_once ( plugin_dir_path( __FILE__ ) . 'tinymce.php' );        // Add TinyMCE plugin



/*
 * Add settings link on plugin page
 */
function playbuzz_settings_link( $links ) {
	$links[] = '<a href="options-general.php?page=playbuzz">' . __( 'Settings' ) . '</a>';
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'playbuzz_settings_link' );
