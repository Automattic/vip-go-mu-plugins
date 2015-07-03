<?php

class Shopify_Assets
{
	public function __construct() {
		#widget
		add_action( 'wp_enqueue_scripts', array( $this, 'widget_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'widget_scripts' ) );

		#settings
		add_action( 'admin_enqueue_scripts', array( $this, 'settings_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'settings_scripts' ) );
	}

	public function widget_styles() {
		wp_enqueue_style( 'shopify-widget', plugins_url( 'css/widget.css', __FILE__ ) );
	}

	public function settings_styles( $hook ) {
		if( 'settings_page_shopify_menu' != $hook )
			return;

		$this->widget_styles();
		wp_enqueue_style( 'shopify-settings', plugins_url( 'css/settings.css', __FILE__ ) );
	}

	public function widget_scripts() {
		wp_enqueue_script( 'json2' );
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'shopify-money', plugins_url( 'javascripts/money.js', __FILE__ ) );
		wp_enqueue_script( 'shopify-widget', plugins_url( 'javascripts/widget.js', __FILE__ ) );
	}

	public function settings_scripts( $hook ) {
		if( 'settings_page_shopify_menu' != $hook )
			return;

		$this->widget_scripts();
		wp_enqueue_script( 'iris' );
		wp_enqueue_script( 'shopify-settings', plugins_url( 'javascripts/settings.js', __FILE__ ) );
	}
}
