<?php

class Ecwid_Settings_Page {

	public function __construct()
	{
		$this->add_hooks();
	}

	protected function add_hooks()
	{
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_styles' ) );
	}

	public function add_admin_menu()
	{
		add_options_page(
			$this->get_page_title(),
			__( 'Ecwid Store', 'ecwid-wordpress-shortcode' ),
			'manage_options',
			'ecwid',
			array( $this, 'render_page' )
		);
	}

	public function render_page()
	{
		require( ECWID_PLUGIN_DIR . '/templates/settings.php' );
	}

	protected function get_page_title()
	{
		static $page_title;

		if ( !$page_title ) {
			$page_title = __( 'Ecwid Shopping Cart', 'ecwid-wordpress_shortcode' );
		}

		return $page_title;
	}

	public function register_settings()
	{
		register_setting( 'ecwid_options_page', 'ecwid_store_id', 'abs' );
	}

	public function register_styles($hook)
	{
		if ( strpos( $hook, 'ecwid' ) !== false ) {
			wp_enqueue_style( 'ecwid-settings-css', ECWID_PLUGIN_URL . '/css/settings.css' );
		}
	}
}