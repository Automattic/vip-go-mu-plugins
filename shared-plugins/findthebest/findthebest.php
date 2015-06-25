<?php
/**
 * Plugin Name: FindTheBest Visual Search
 * Description: Discover and embed interactive data visualizations on people, organizations, products, and more.
 * Version: 3.0.4
 * Author: FindTheBest
 * Author URI: http://findthebest.com
 * Text Domain: findthebest
 * License: GPLv2
 */

define( 'FTB_WP_DEFAULT_KEY', 'cd3d6c2a036146d0e3b242c510ebc855' );

class FindTheBest_VisualSearch {

	/**
	 * Singleton
	 */
	public static function init() {
		static $instance = false;

		if ( !$instance ) {
			load_plugin_textdomain( 'findthebest', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			$instance = new FindTheBest_VisualSearch;
		}

		return $instance;
	}


	/**
	 * Constructor
	 */
	public function __construct() {

		if ( is_admin() ) {
			add_action( 'add_meta_boxes',         array( &$this, 'add_meta_box'          ) );
			add_action( 'media_buttons',          array( &$this, 'add_media_button'      ), 2000 );
			add_action( 'admin_enqueue_scripts',  array( &$this, 'admin_menu'            ) );
			add_action( 'admin_menu',             array( &$this, 'options_page_add_menu' ) );
			add_action( 'admin_init',             array( &$this, 'options_page_init'     ) );
			add_action( 'print_media_templates',  array( &$this, 'print_media_templates' ) );
		}

		add_shortcode( 'findthebest', array( &$this, 'shortcode_handler' ) );
	}

	public function FindTheBest_VisualSearch() {
		$this->__construct();
	}

	function add_media_button() {
		if( $this->post_type_supported() ) {
			echo $this->render( 'media-button', array(
				'title' => __( 'Add Visuals', 'findthebest' )
			) );
		}
	}

	function add_meta_box() {
		if( $this->post_type_supported() ) {
			add_meta_box(
				'ftb',
				__( 'FindTheBest Visual Search', 'findthebest' ),
				array( &$this, 'meta_box_shim' ),
				get_post_type(),
				'side'
			);
		}
	}

	private function post_type_supported() {
		// Add optional support for custom post types. Always add to post_type 'post'.
		$post_type = get_post_type();
		return post_type_supports( $post_type, 'findthebest' ) || $post_type == 'post' || $post_type == 'page';
	}

	function admin_menu( $hook ) {
		if ( 'post.php' == $hook || 'post-new.php' == $hook ) {
			$this->post_page_init();
		}
	}

	function post_page_init() {
		wp_enqueue_script(
			'ftb_cms_plugin',
			$this->file_path( '/js/cms-plugin.js' )
		);

		wp_enqueue_script(
			'ftb_script',
			$this->file_path( '/js/ftb.js' ),
			array( 'jquery', 'ftb_cms_plugin' )
		);

		global $wp_version;
		$user = wp_get_current_user();
		$api_key = $this->get_option( 'findthebest_option_api_key' );

		wp_localize_script( 'ftb_script', 'ftbData', array(
			'apiKey' => $api_key ? $api_key : FTB_WP_DEFAULT_KEY,
			'userID' => $user->user_login ? $user->user_login : $user->ID,
			'userEmail' => $user->user_email,
			'wpVersion' => floatval($wp_version),
			'locale' => get_locale()
		) );

		wp_enqueue_style( 'ftb_style', $this->file_path( '/css/ftb.css' ) );

		add_filter( 'tiny_mce_before_init', array( &$this, 'tiny_mce_init' ) );
		add_filter( 'mce_external_plugins', array( &$this, 'tiny_mce_plugin' ) );
	}

	function options_page_add_menu() {
		add_options_page(
			__( 'FindTheBest Options', 'findthebest' ), // Page title
			__( 'FindTheBest', 'findthebest' ),         // Menu title
			'manage_options',                           // Capability
			'findthebest-options',                      // Menu slug
			array( &$this, 'options_page_render' )      // Render callback
		);
	}

	function options_page_init() {
		register_setting(
			'findthebest_options',                   // Option group
			'findthebest_options',                   // Option name
			array( &$this, 'options_page_validate' ) // Sanitize
		);

		add_settings_section(
			'findthebest_options_plugin', // ID
			null,                         // Title
			null,                         // Callback
			'findthebest-options'         // Page
		);

		add_settings_field(
			'findthebest_option_api_key',        // ID
			__( 'Your API Key', 'findthebest' ), // Title
			array( &$this, 'options_api_key' ),  // Callback
			'findthebest-options',               // Page
			'findthebest_options_plugin'         // Section
		);
	}

	function options_api_key() {
		echo $this->render( 'options-input-text', array(
			'option' => $this->get_option( 'findthebest_option_api_key' ),
			'field' => 'findthebest_option_api_key'
		) );
	}

	protected function get_option( $name ) {
		$options = get_option('findthebest_options');
		return isset($options[$name]) ? $options[$name] : null;
	}

	function options_page_validate( $input ) {
		$validated_input = array();

		if( isset( $input['findthebest_option_api_key'] ) ) {
			$validated_input['findthebest_option_api_key'] = sanitize_key($input['findthebest_option_api_key']);
		}

		return $validated_input;
	}

	function options_page_render() {
		echo $this->render( 'options' );
	}

	function file_path( $path, $relativity = 'remote' ) {
		switch ( $relativity ) {
		case 'remote':
			return plugins_url( $path, __FILE__ );

		case 'local':
			return untrailingslashit( dirname( __FILE__ ) ) . $path;
		}

		return '';
	}

	function meta_box_shim() {
		echo $this->render( 'meta-box' );
	}

	/**
	 * The HTML generated from rendering a plugin view with the specified arguments.
	 *
	 * @param string $view The PHP file name without the extension.
	 * @param array $vars An associative array of variables made available.
	 * @return string The generated HTML.
	 */
	function render( $view, $vars = array() ) {
		$path = $this->file_path( "/views/{$view}.php", 'local' );
		$vars[ 'image_dir' ] = plugins_url( 'images/', __FILE__ );

		ob_start();
		require $path;

		return ob_get_clean();
	}

	/**
	 * Converts the FindTheBest shortcode into an HTML embed code.
	 *
	 * @param array $attributes An associative array of shortcode arguments.
	 * @return string The HTML embed code.
	 */
	function shortcode_handler( $attributes ) {
		if ( empty( $attributes ) ) {
			return null;
		}

		$defaults = array(
			'id'        => '',
			'url'       => '',
			'title'     => '',
			'width'     => '',
			'height'    => '',
			'link'      => '',
			'link_text' => ''
		);

		$arguments = wp_parse_args( $attributes, $defaults );

		if ( empty( $arguments['id'] ) || empty( $arguments['link'] ) ||
			empty( $arguments['title'] ) || empty( $arguments['url'] ) ) {
			return null;
		}

		$arguments['width'] = intval( $arguments['width'] );
		$arguments['height'] = intval( $arguments['height'] );

		if ( $arguments['width'] <= 0 || $arguments['height'] <= 0 ) {
			return null;
		}

		return $this->render( 'embed-code', $arguments );
	}

	function get_shortcode_strategy() {
		global $wp_version;

		return version_compare($wp_version, '3.9', '>=') ? 'view' : 'plugin';
	}

	function print_media_templates() {
		if ( ! isset( get_current_screen()->id ) || get_current_screen()->base != 'post' ) {
			return;
		}
		if ( $this->get_shortcode_strategy() == 'view' ) {
			echo $this->render( 'tiny-mce-view' );
		}
	}

	function tiny_mce_init( $init_options ) {
		$css_path = plugins_url( 'css/tiny-mce-plugin.css', __FILE__ );
		$init_options[ 'content_css' ] .= ',' . $css_path;

		return $init_options;
	}

	function tiny_mce_plugin( $plugins ) {
		if ( $this->get_shortcode_strategy() === 'view' ) {
			$plugins[ 'findthebest' ] = plugins_url( 'js/tiny-mce-view.js', __FILE__ );
		} else {
			$plugins[ 'findthebest' ] = plugins_url( 'js/tiny-mce-plugin.js', __FILE__ );
		}

		return $plugins;
	}

}

add_action( 'plugins_loaded', array( 'FindTheBest_VisualSearch', 'init' ) );
