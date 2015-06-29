<?php
/**
 * Holds main SocialFlow plugin class
 *
 * @package SocialFlow
 */
class SocialFlow extends SocialFlow_Methods {

	/**
	 * Holds api object, use get api function to access this object
	 *
	 * @since 2.0
	 * @access private
	 * @var object SocialFlow api
	 */
	var $api;

	/**
	 * Holds options object
	 *
	 * @since 2.0
	 * @access public
	 * @var object
	 */
	var $options;

	/**
	 * Holds array of admin page names
	 * is set in SocialFlow_Admin
	 * 
	 * @since 2.0
	 * @access public
	 * @var array
	 */
	var $pages;

	/**
	 * Holds array of WP_Error objects
	 *
	 * @since 2.0
	 * @access public
	 * @var array
	 */
	var $errors;

	/**
	 * Holds default plugin options
	 *
	 * @since 2.0
	 * @access public
	 * @var array
	 */
	var $default_options = array(
		'initial_nag' => 1,
		'accounts' => array(),
		'shorten_links' => 1,
		'post_type' => array( 'post' ),
		'publish_option' => 'optimize',
		'optimize_period' => 'anytime'
	);

	/**
	 * PHP5 constructor
	 *
	 * @since 2.0
	 * @access public
	 */
	function __construct() {

		// Initialize plugin options
		$this->init_options();

		// Load sub classes
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * All classes are initialized on plugins loaded action
	 *
	 * @since 2.0
	 * @access public
	 */
	function init() {

		// Initialize admin only objects
		if ( is_admin() ) {

			// Initialize main admin object
			new SocialFlow_Admin;

			// Initialize update handler if versions doesn't match
			if ( SF_VERSION !== $this->options->get( 'version' ) ) {
				new SocialFlow_Update;
			}
		}

		// This part is moved outside to let it handle cron scheduled post publication

		// Initialize Post object
		new SocialFlow_Post;

		// Initialize Accounts object
		$this->accounts = new SocialFlow_Accounts;
	}

	/**
	 * Initializes plugin options object
	 * set default options
	 *
	 * @since 2.0
	 * @access public
	 */
	function init_options() {
		$this->options = new SF_Plugin_Options( 'socialflow', apply_filters( 'sf_init_options', $this->default_options ) );
	}
}
