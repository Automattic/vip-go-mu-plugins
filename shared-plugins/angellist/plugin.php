<?php
/*
Plugin Name: AngelList
Plugin URI: https://github.com/niallkennedy/angellist
Description: Associate a post with an AngelList startup.
Author: Niall Kennedy
Author URI: http://www.niallkennedy.com/
Version: 1.3.1
*/

if ( ! class_exists( 'AngelList' ) ):
/**
 * Load the AngelList plugin
 *
 * @since 1.0
 */
class AngelList {
	public static function init() {
		$plugin_directory = dirname( __FILE__ );
		if ( is_admin() ) {
			// add a post meta box to the edit and create post screens
			// allows a user to associate a post with one or more AngelList companies
			if ( ! class_exists( 'AngelList_Post_Meta_Box' ) )
				require_once( $plugin_directory . '/edit.php' );
			AngelList_Post_Meta_Box::init();
		} else {
			// display AngelList content after a post in single post view
			if ( ! class_exists( 'AngelList_Content' ) )
				require_once( $plugin_directory . '/content.php' );
			new AngelList_Content();
		}
	}
}

add_action( 'init', 'AngelList::init' );

endif;
?>