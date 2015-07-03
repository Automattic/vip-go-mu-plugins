<?php
/**
 * Interface for accessing, storing, and editing plugin options
 * @package fork
 */

class Fork_Options {

	public $parent;
	public $key = 'fork';
	public $defaults = array( 'post_types' => array( 'post' => true ) );
	
	/**
	 * Hooks
	 */
	function __construct( &$parent ) {

		$this->parent = &$parent;
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

	}


	/**
	 * Magic method to allow easy getting of options
	 */
	function __get( $key ) {

		$options = $this->get();

		if ( !isset( $options[ $key ] ) )
			return false;

		return $options[ $key ];

	}


	/**
	 * Magic method to allow setting of options
	 */
	function __set( $key, $value ) {

		$options = $this->get();
		$options[ $key ] = $value;
		$this->set( $options );
		return $options;

	}


	/**
	 * Get all options
	 */
	function get() {

		$options = get_option( $this->key );
		$options = shortcode_atts( $this->defaults, $options );	
		return $options;
			
	}


	/**
	 * Set all options
	 */
	function set( $options, $merge = true ) {

		if ( $merge )
			$options = array_merge( $options, $this->get() );

		return update_option( $this->key, $options );

	}


	/**
	 * Register Settings menu
	 */
	function register_menu() {
		
		add_submenu_page( 'edit.php?post_type=fork', __( 'Fork Settings', 'post-forking' ), __( 'Settings', 'post-forking' ), 'manage_options', 'fork_settings', array( $this, 'options' ) );
	}


	/**
	 * Hook into settings API
	 */
	function register_settings() {

		register_setting( 'fork', 'fork', array( $this, 'sanitize' ) );
	}


	/**
	 * Callback to render options page
	 */
	function options() {

		$this->parent->template( 'options' );

	}


	/**
	 * Sanitize options on save
	 */
	function sanitize( $input ) {

		$output = array();
		foreach ( $input['post_types'] as $post_type => $state ) {
			$output['post_types'][$post_type] = ( $state == 'on' );
		}

		return $output;

	}


}