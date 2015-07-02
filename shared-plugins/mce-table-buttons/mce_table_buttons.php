<?php
/**
 Plugin Name: MCE Table Buttons
 Plugin URI: http://10up.com/plugins-modules/wordpress-mce-table-buttons/
 Description: Add <strong>controls for table editing</strong> to the visual content editor with this <strong>light weight</strong> plug-in.
 Version: 3.0
 Author: Jake Goldman, 10up, Oomph
 Author URI: http://10up.com
 License: GPLv2 or later
*/

class MCE_Table_Buttons {

	/**
	 * Handles initializing this class and returning the singleton instance after it's been cached.
	 *
	 * @return null|MCE_Table_Buttons
	 */
	public static function get_instance() {
		// Store the instance locally to avoid private static replication
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
			self::_add_actions();
		}

		return $instance;
	}

	/**
	 * An empty constructor
	 */
	public function __construct() { /* Purposely do nothing here */ }

	/**
	 * Handles registering hooks that initialize this plugin.
	 */
	public static function _add_actions() {
		add_filter( 'the_editor', array( __CLASS__, 'the_editor' ) ); // most convenient hook
		add_action( 'content_save_pre', array( __CLASS__, 'content_save_pre'), 100 );
	}

	/**
	 * The Editor is really a filter, but happens to be our most convenient hook to set everything up
	 *
	 * @param string $editor
	 * @return string Editor fields
	 */
	public static function the_editor( $editor ) {
		global $tinymce_version;

		if ( version_compare( $tinymce_version, '400', '<' ) ) {
			add_filter( 'mce_external_plugins', array( __CLASS__, 'mce_external_plugins_3_8' ) );
			add_filter( 'mce_buttons_3', array( __CLASS__, 'mce_buttons_3_8' ) );
			wp_register_style( 'mce-table-buttons', plugin_dir_url( __FILE__ ) . 'tinymce3-assets/mce-table-buttons.css' );
			wp_print_styles( 'mce-table-buttons' );
		} else {
			add_filter( 'mce_external_plugins', array( __CLASS__, 'mce_external_plugins_3_9' ) );
			add_filter( 'mce_buttons_2', array( __CLASS__, 'mce_buttons_3_9' ) );
		}

		remove_filter( 'the_editor', array( __CLASS__, 'the_editor' ) ); // only needs to run once

		return $editor;
	}

	/**
	 * Initialize TinyMCE 3.x table plugin and custom TinyMCE plugin for third editor row
	 *
	 * @param array $plugin_array Array of TinyMCE plugins
	 * @return array Array of TinyMCE plugins
	 */
	public static function mce_external_plugins_3_8( $plugin_array ) {
		$plugin_dir_url = plugin_dir_url( __FILE__ );
		$plugin_array['table'] = $plugin_dir_url . 'tinymce3-table/editor_plugin.js';
		$plugin_array['mcetablebuttons'] = $plugin_dir_url . 'tinymce3-assets/mce-table-buttons.js';
		return $plugin_array;
	}

	/**
	 * Add TinyMCE 3.x table control buttons to a third row of editor buttons
	 *
	 * @param array $buttons Buttons for the third row
	 * @return array Buttons for the third row
	 */
	public static function mce_buttons_3_8( $buttons ) {
		array_push( $buttons, 'tablecontrols' );
		return $buttons;
	}

	/**
	 * Initialize TinyMCE 4.x table plugin
	 *
	 * @param array $plugin_array Array of TinyMCE plugins
	 * @return array Array of TinyMCE plugins
	 */
	public static function mce_external_plugins_3_9( $plugin_array ) {
		$variant = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '' : '.min';
		$plugin_array['table'] = plugin_dir_url( __FILE__ ) . 'tinymce4-table/plugin' . $variant . '.js';
   		return $plugin_array;
	}

	/**
	 * Add TinyMCE 4.x table control to the second row, after other formatting controls
	 *
	 * @param array $buttons Buttons for the second row
	 * @return array Buttons for the second row
	 */
	public static function mce_buttons_3_9( $buttons ) {
		// in case someone is manipulating other buttons, drop table controls at the end of the row
		if ( ! $pos = array_search( 'undo', $buttons ) ) {
			array_push( $buttons, 'table' );
			return $buttons;
		}

		return array_merge( array_slice( $buttons, 0, $pos ), array( 'table' ), array_slice( $buttons, $pos ) );
	}

	/**
	 * Fixes weirdness resulting from wpautop and formatting clean up not built for tables
	 *
	 * @param string $content Editor content before WordPress massaging
	 * @return string Editor content before WordPress massaging
	 */
	public static function content_save_pre( $content ) {
		if ( substr( $content, -8 ) == '</table>' )
			$content .= "\n<br />";
		
		return $content;
	}
}

MCE_Table_Buttons::get_instance();