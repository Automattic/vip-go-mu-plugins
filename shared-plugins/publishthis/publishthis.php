<?php
/*
Plugin Name: PublishThis Curation
Plugin URI: http://publishthis.com
Description: PublishThis plugin that creates pages/posts from curated content as well as widgets for automated content.
Version: 1.0.9
Author: PublishThis
Author URI: http://www.publishthis.com
License: ...
Copyright: ...
*/

if ( ! defined( 'ABSPATH' ) )
	exit();

class Publishthis {

	var $option_name = 'publishthis_options';
	var $post_type = 'publishthis_action';
	var $version = '1.0.9';

	var $api;
	var $cron;
	var $log;
	var $utils;

	private $_options;
	private $_plugin_path;
	private $_plugin_url;

	/**
	 *
	 *
	 * @desc Publishthis constructor.
	 */
	function __construct() {
		// Activation
		register_activation_hook( __FILE__, array ( $this, 'activate' ) );

		// Deactivation
		register_deactivation_hook( __FILE__, array ( $this, 'deactivate' ) );

		// Actions
		add_action( 'init', array ( $this, 'register_post_type' ), 0 );
		add_action( 'init', array ( $this, 'init_sub_classes' ), 0 );
		add_action( 'widgets_init', array ( $this, 'register_widgets' ) );
		add_action( 'wp_enqueue_scripts', array ( $this, 'enqueue_assets' ), 0 );
		add_action( 'add_meta_boxes', array ( $this, 'remove_unwanted_metaboxes' ) );
	}

	/**
	 *
	 *
	 * @desc Plugin activation
	 */
	function activate() {
		if ( get_option( $this->option_name ) ) {
			return;
		}

		add_option( $this->option_name,
			array( 'api_token'     => '',
				'api_version'   => '3.0',
				'debug'         => '1',
				'pause_polling' => '0',
				'styling'       => '1' )
		);
	}

	/**
	 *
	 *
	 * @desc Plugin dectivation: for future implementations
	 */
	function deactivate() {
	}

	/**
	 *
	 *
	 * @desc Remove the platinum seo box
	 */
	function remove_unwanted_metaboxes() {
		remove_meta_box( 'postpsp', $this->post_type, 'advanced' );
	}

	/**
	 *
	 *
	 * @desc Bring in CSS.
	 */
	function enqueue_assets() {
		wp_enqueue_style( 'publishthis-content-all', $this->plugin_url () . '/assets/css/content-on.css' );

		if ( ! $this->get_option( 'styling' ) ) {
			return;
		}

		wp_enqueue_style( 'publishthis-widgets', $this->plugin_url () . '/assets/css/widgets.css' );
		wp_enqueue_style( 'publishthis-content', $this->plugin_url () . '/assets/css/content.css' );
	}

	/**
	 *
	 *
	 * @desc Init Publishthis sub classes.
	 */
	function init_sub_classes() {
		// Admin functions
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			require $this->plugin_path() . '/admin/class-admin.php';
			new Publishthis_Admin();
		}

		// API functions
		require $this->plugin_path() . '/classes/class-api.php';
		$this->api = new Publishthis_API();

		// Cron functions
		require $this->plugin_path() . '/classes/class-cron.php';
		$this->cron = new Publishthis_Cron();

		// Logging functions
		require $this->plugin_path() . '/classes/class-log.php';
		$this->log = new Publishthis_Log();

		// Utils functions
		require $this->plugin_path() . '/classes/class-utils.php';
		$this->utils = new Publishthis_Utils();
	}

	/**
	 *
	 *
	 * @desc Register Publishthis api actions post type on 'init'.
	 */
	function register_post_type() {
		register_post_type( $this->post_type,
			array( 'labels' => array(
					'name' => __ ( 'Publishing Actions', 'publishthis' ),
					'singular_name' => __ ( 'Publishing Action', 'publishthis' ),
					'add_new' => __ ( 'Add New Publishing Action', 'publishthis' ),
					'all_items' => __ ( 'All Publishing Actions', 'publishthis' ),
					'add_new_item' => __ ( 'Add New Publishing Action', 'publishthis' ),
					'edit_item' => __ ( 'Edit Publishing Action', 'publishthis' ),
					'new_item' => __ ( 'New Publishing Action', 'publishthis' ),
					'view_item' => __ ( 'View Publishing Action', 'publishthis' ),
					'search_items' => __ ( 'Search Publishing Actions', 'publishthis' ),
					'not_found' => __ ( 'No Publishing Actions', 'publishthis' ),
					'not_found_in_trash' => __ ( 'No Publishing Actions found in Trash', 'publishthis' ),
					'menu_name' => __ ( 'Publishing Actions', 'publishthis' ) ),
				'capability_type' => 'post',
				'exclude_from_search' => true,
				'has_archive' => false,
				'hierarchical' => false,
				'public' => false,
				'publicly_queryable' => false,
				'show_in_admin_bar' => false,
				'show_in_menu' => false,
				'show_in_nav_menus' => false,
				'show_ui' => true,
				'supports' => array( 'title' ) ) );
	}

	/**
	 *
	 *
	 * @desc Loads and registers widgets.
	 */
	function register_widgets() {
		require $this->plugin_path() . '/widgets/widget-automated-feed-content.php';
		register_widget( 'Publishthis_Automated_Feed_Content_Widget' );

		require $this->plugin_path() . '/widgets/widget-automated-saved-search-content.php';
		register_widget( 'Publishthis_Automated_Saved_Search_Content_Widget' );

		require $this->plugin_path() . '/widgets/widget-topic-content.php';
		register_widget( 'Publishthis_Topic_Content_Widget' );
	}

	/*
	 * --- Helper methods ----------
	 */

	/**
	 *
	 *
	 * @desc Loads a template.
	 */
	function load_template( $template ) {
		$located = locate_template( array ( 'publishthis/' . $template ) );
		if ( ! $located ) {
			$located = $this->plugin_path() . '/templates/' . $template;
		}
		include $located;
	}

	/**
	 *
	 *
	 * @desc Gets the plugin path.
	 */
	function plugin_path() {
		if ( $this->_plugin_path )
			return $this->_plugin_path;
		return $this->_plugin_path = untrailingslashit( dirname( __FILE__ ) );
	}

	/**
	 *
	 *
	 * @desc Gets the plugin url.
	 */
	function plugin_url() {
		if ( $this->_plugin_url )
			return $this->_plugin_url;
		return $this->_plugin_url = plugins_url( '', __FILE__ );
	}

	/*
	 * --- Options ----------
	 */

	/**
	 *
	 *
	 * @desc Gets debug level.
	 */
	function debug() {
		return ( $this->get_option( 'debug' ) == "2" ) ? true : false;
	}

	/**
	 *
	 *
	 * @desc Gets error level.
	 */
	function error() {
		return ( $this->get_option( 'debug' ) == "1" ) ? true : false;
	}

	/**
	 *
	 *
	 * @desc Gets an option value by key
	 * @param unknown $key Option key
	 * @return Option value
	 */
	function get_option( $key ) {
		if ( isset( $this->_options[$key] ) ) {
			return $this->_options[$key];
		}
		$this->_init_options();
		return isset( $this->_options[$key] ) ? $this->_options[$key] : null;
	}

	/**
	 *
	 *
	 * @desc Gets the entire options set.
	 */
	function get_options() {
		if ( isset( $this->_options ) ) {
			return $this->_options;
		}
		$this->_init_options();
		return $this->_options;
	}

	/*
	 * --- Private methods ----------
	 */

	/**
	 *
	 *
	 * @desc Init publishthis options array
	 */
	private function _init_options() {
		$defaults = array( 'api_token' => '', 'api_version' => '3.0', 'debug' => '1', 'pause_polling' => '0', 'curatedby' => '1' );
		$options = get_option( $this->option_name );
		if ( ! isset( $options ) || ! is_array( $options ) ) {
			$options = array ();
		}
		$this->_options = $options + $defaults;
	}
}

// Init general handler
$GLOBALS['publishthis'] = new Publishthis();

// Include necessary files

//raw handler is used for putting the twitter card code into a post
require_once $GLOBALS['publishthis']->plugin_path() . '/ptraw-handler.php';

//footer contains our curated by logo displays
require_once $GLOBALS['publishthis']->plugin_path() . '/ptfooter.php';

//debug widget is placed on the dashboard so that clients and publishthis
//can ensure that api calls are made and if there are any issues with the plugin
require_once $GLOBALS['publishthis']->plugin_path() . '/publishthis-debug-widget.php';
