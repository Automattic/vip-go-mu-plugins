<?php
/*
  Plugin Name: thePlatform Video Manager
  Plugin URI: http://theplatform.com/
  Description: Manage video assets hosted in thePlatform mpx from within WordPress.
  Version: 2.0.0
  Author: thePlatform
  Author URI: http://theplatform.com/
  License: GPL2

  Copyright 2013-2015 thePlatform LLC

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This is thePlatform's plugin entry class, all initalization and AJAX handlers are defined here.
 */
class ThePlatform_Plugin {

	private static $instance;

	/**
	 * Creates one instance of the plugin
	 * @return ThePlatform_Plugin New or existing instance of ThePlatform_Plugin
	 */
	public static function init() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new ThePlatform_Plugin;
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	function __construct() {
		require_once( dirname( __FILE__ ) . '/thePlatform-constants.php' );
		require_once( dirname( __FILE__ ) . '/thePlatform-proxy.php' );

		$this->tp_admin_cap    = apply_filters( TP_ADMIN_CAP, TP_ADMIN_DEFAULT_CAP );
		$this->tp_uploader_cap = apply_filters( TP_UPLOADER_CAP, TP_UPLOADER_DEFAULT_CAP );
		$this->tp_editor_cap   = apply_filters( TP_EDITOR_CAP, TP_EDITOR_DEFAULT_CAP );

		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_scripts' ) );
		add_action( 'admin_init', array( $this, 'theplatform_register_plugin_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		if ( current_user_can( $this->tp_editor_cap ) ) {
			add_filter( 'media_upload_tabs', array( $this, 'tp_upload_tab' ) );
			add_action( 'media_upload_theplatform', array( $this, 'add_tp_media_form' ) );

			add_action( 'admin_init', array( $this, 'theplatform_tinymce_button' ) );
			add_action( 'media_buttons', array( $this, 'theplatform_media_button' ), 20 );
			add_action( 'wp_enqueue_media', array( $this, 'theplatform_enqueue_media_button_scripts' ) );
		}

		add_shortcode( 'theplatform', array( $this, 'shortcode' ) );
	}

	/**
	 * Media Browser Region
	 */

	/**
	 * Add thePlatform the the Media tabs
	 *
	 * @param  array $tabs Array of tabs in the Media dialog
	 *
	 * @return array The updated array of tabs
	 */
	function tp_upload_tab( $tabs ) {
		$tabs['theplatform'] = "mpx Video Manager";

		return $tabs;
	}

	/**
	 * Callback to load thePlatform media template
	 */
	function add_tp_media_form() {
		wp_iframe( array( $this, 'tp_media_form' ) );
	}

	/**
	 * Method to load thePlatform media browser from the Media Tab
	 */
	function tp_media_form() {
		require_once( dirname( __FILE__ ) . '/thePlatform-browser.php' );
	}

	/**
	 * Script Region
	 */

	/**
	 * Enqueue CSS and JS on thePlatform admin pages
	 *
	 * @param  string $hook Page hook
	 */
	function admin_enqueue_scripts( $hook ) {
		// Media Browser
		if ( $hook == 'toplevel_page_theplatform' || $hook == 'media-upload-popup' ) {
			wp_enqueue_script( 'tp_browser_js' );
			wp_enqueue_style( 'tp_browser_css' );
		}
		// Edit/Upload Form
		if ( $hook == 'theplatform_page_theplatform-uploader' ) {
			wp_enqueue_script( 'tp_edit_upload_js' );
			wp_enqueue_style( 'tp_edit_upload_css' );
		}
		// Upload popup
		if ( $hook == 'admin_page_theplatform-upload-window' ) {
			wp_enqueue_script( 'tp_file_uploader_js' );
			wp_enqueue_style( 'tp_file_uploader_css' );
		}
	}

	/**
	 * Registers javascripts and css used throughout the plugin
	 */
	function register_scripts() {
		wp_register_script( 'tp_pdk_js', "//pdk.theplatform.com/next/pdk/tpPdk.js" );
		wp_register_script( 'tp_holder_js', plugins_url( '/js/holder.js', __FILE__ ) );
		wp_register_script( 'tp_nprogress_js', plugins_url( '/js/nprogress.js', __FILE__ ) );
		wp_register_script( 'tp_edit_upload_js', plugins_url( '/js/thePlatform-edit-upload.js', __FILE__ ), array( 'jquery' ) );
		wp_register_script( 'tp_file_uploader_js', plugins_url( '/js/theplatform-uploader.js', __FILE__ ), array( 'jquery', 'tp_nprogress_js' ) );
		wp_register_script( 'tp_browser_js', plugins_url( '/js/thePlatform-browser.js', __FILE__ ), array( 'jquery', 'underscore', 'jquery-ui-dialog', 'tp_holder_js', 'tp_pdk_js', 'tp_edit_upload_js' ) );
		wp_register_script( 'tp_options_js', plugins_url( '/js/thePlatform-options.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable', 'underscore' ) );
		wp_register_script( 'tp_media_button_js', plugins_url( '/js/thePlatform-media-button.js', __FILE__ ) );

		wp_localize_script( 'tp_edit_upload_js', 'tp_edit_upload_local', array(
			'ajaxurl'             => admin_url( 'admin-ajax.php' ),
			'uploader_window_url' => admin_url( 'admin.php?page=theplatform-upload-window' ),
			'tp_nonce'            => array(
				'theplatform_edit'    => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_edit' ),
				'theplatform_media'   => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_media' ),
				'theplatform_upload'  => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_upload' ),
				'theplatform_publish' => wp_create_nonce( 'theplatform-ajax-nonce-publish_media' ),
				'theplatform_revoke'  => wp_create_nonce( 'theplatform-ajax-nonce-revoke_media' )
			)
		) );

		wp_localize_script( 'tp_file_uploader_js', 'tp_file_uploader_local', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'tp_nonce' => array(
				'initialize_media_upload' => wp_create_nonce( 'theplatform-ajax-nonce-initialize_media_upload' ),
				'publish_media'           => wp_create_nonce( 'theplatform-ajax-nonce-publish_media' )
			)
		) );

		wp_localize_script( 'tp_browser_js', 'tp_browser_local', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'tp_nonce' => array(
				'get_videos'          => wp_create_nonce( 'theplatform-ajax-nonce-get_videos' ),
				'get_video_count'     => wp_create_nonce( 'theplatform-ajax-nonce-get_video_count' ),
				'get_video_by_id'     => wp_create_nonce( 'theplatform-ajax-nonce-get_video_by_id' ),
				'get_categories'      => wp_create_nonce( 'theplatform-ajax-nonce-get_categories' ),
				'get_profile_results' => wp_create_nonce( 'theplatform-ajax-nonce-get_profile_results' ),
				'set_thumbnail'       => wp_create_nonce( 'theplatform-ajax-nonce-set_thumbnail' ),
				'generate_thumbnail'  => wp_create_nonce( 'theplatform-ajax-nonce-generate_thumbnail' )
			)
		) );

		wp_localize_script( 'tp_options_js', 'tp_options_local', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'tp_nonce' => array(
				'verify_account' => wp_create_nonce( 'theplatform-ajax-nonce-verify_account' )
			)
		) );

		wp_register_style( 'tp_edit_upload_css', plugins_url( '/css/thePlatform-edit-upload.css', __FILE__ ) );
		wp_register_style( 'tp_browser_css', plugins_url( '/css/thePlatform-browser.css', __FILE__ ), array( 'tp_edit_upload_css', 'wp-jquery-ui-dialog' ) );
		wp_register_style( 'tp_options_css', plugins_url( '/css/thePlatform-options.css', __FILE__ ) );
		wp_register_style( 'tp_nprogress_css', plugins_url( '/css/nprogress.css', __FILE__ ) );
		wp_register_style( 'tp_file_uploader_css', plugins_url( '/css/thePlatform-file-uploader.css', __FILE__ ), array( 'tp_nprogress_css' ) );
	}

	/**
	 * Admin Menus Region
	 */

	/**
	 * Add admin pages to Wordpress sidebar
	 */
	function add_admin_page() {
		$slug = 'theplatform';
		add_menu_page( 'thePlatform', 'thePlatform', $this->tp_editor_cap, $slug, array( $this, 'media_page' ), 'dashicons-video-alt3', '10.0912' );
		add_submenu_page( $slug, 'thePlatform Video Browser', 'mpx Video Manager', $this->tp_editor_cap, $slug, array( $this, 'media_page' ) );
		add_submenu_page( $slug, 'thePlatform Video Uploader', 'Upload Video', $this->tp_uploader_cap, 'theplatform-uploader', array( $this, 'upload_page' ) );
		add_submenu_page( $slug, 'thePlatform Plugin Settings', 'Settings', $this->tp_admin_cap, 'theplatform-settings', array( $this, 'admin_page' ) );
		add_submenu_page( $slug, 'thePlatform Plugin About', 'About', $this->tp_editor_cap, 'theplatform-about', array( $this, 'about_page' ) );
		add_submenu_page( 'options.php', 'thePlatform Plugin Uploader', 'Uploader', $this->tp_uploader_cap, 'theplatform-upload-window', array( $this, 'upload_window' ) );
	}

	/**
	 * Calls the plugin's options page template
	 */
	function admin_page() {
		$this->check_plugin_update();
		require_once( dirname( __FILE__ ) . '/thePlatform-options.php' );
	}

	/**
	 * Calls the Media Manager template
	 */
	function media_page() {
		$this->check_plugin_update();
		require_once( dirname( __FILE__ ) . '/thePlatform-browser.php' );
	}

	/**
	 * Calls the Upload form template
	 */
	function upload_page() {
		$this->check_plugin_update();
		require_once( dirname( __FILE__ ) . '/thePlatform-edit-upload.php' );
	}

	/**
	 * Calls the About page template
	 */
	function about_page() {
		require_once( dirname( __FILE__ ) . '/thePlatform-about.php' );
	}

	/**
	 * Calls the Upload Window template
	 */
	function upload_window() {
		require_once( dirname( __FILE__ ) . '/thePlatform-upload-window.php' );
	}

	/**
	 * Plugin update Region
	 */

	/**
	 * Checks the current version against the last version stored in preferences to determine whether an update happened
	 * @return boolean
	 */
	function plugin_version_changed() {
		$preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );

		if ( ! $preferences ) {
			return false; //New installation
		}

		if ( ! isset( $preferences['plugin_version'] ) ) {
			return TP_PLUGIN_VERSION( '1.0.0' ); //Old versions didn't have plugin_version stored
		}

		$version        = TP_PLUGIN_VERSION( $preferences['plugin_version'] );
		$currentVersion = TP_PLUGIN_VERSION();
		if ( $version['major'] != $currentVersion['major'] ) {
			return $version;
		}

		if ( $version['minor'] != $currentVersion['minor'] ) {
			return $version;
		}

		if ( $version['patch'] != $currentVersion['patch'] ) {
			return $version;
		}

		return false;
	}

	/**
	 * Checks if the plugin has been updated and performs any necessary updates.
	 */
	function check_plugin_update() {
		$oldVersion = $this->plugin_version_changed();
		if ( false === $oldVersion ) {
			return;
		}

		$newVersion = TP_PLUGIN_VERSION();

		// On any version, update defaults that didn't previously exist
		$newPreferences                   = array_merge( TP_PREFERENCES_OPTIONS_DEFAULTS(), get_option( TP_PREFERENCES_OPTIONS_KEY, array() ) );
		$newPreferences['plugin_version'] = TP_PLUGIN_VERSION;

		update_option( TP_PREFERENCES_OPTIONS_KEY, $newPreferences );
		update_option( TP_ACCOUNT_OPTIONS_KEY, array_merge( TP_ACCOUNT_OPTIONS_DEFAULTS(), get_option( TP_ACCOUNT_OPTIONS_KEY, array() ) ) );
		update_option( TP_BASIC_METADATA_OPTIONS_KEY, array_merge( TP_BASIC_METADATA_OPTIONS_DEFAULTS(), get_option( TP_BASIC_METADATA_OPTIONS_KEY, array() ) ) );

		// We had a messy update with 1.2.2/1.3.0, let's clean up
		if ( ( $oldVersion['major'] == '1' && $oldVersion['minor'] == '2' && $oldVersion['patch'] == '2' ) ||
		     ( $oldVersion['major'] == '1' && $oldVersion['minor'] == '3' && $oldVersion['patch'] == '0' )
		) {
			$basicMetadataFields  = get_option( TP_BASIC_METADATA_OPTIONS_KEY, array() );
			$customMetadataFields = get_option( TP_CUSTOM_METADATA_OPTIONS_KEY, array() );
			update_option( TP_BASIC_METADATA_OPTIONS_KEY, array_diff_assoc( $basicMetadataFields, $customMetadataFields ) );
		}

		// Move account settings from preferences (1.2.0)
		if ( ( $oldVersion['major'] == '1' && $oldVersion['minor'] < '2' ) &&
		     ( $newVersion['major'] > '1' || ( $newVersion['major'] >= '1' && $newVersion['minor'] >= '2' ) )
		) {
			$preferences = get_option( TP_PREFERENCES_OPTIONS_KEY, array() );
			if ( array_key_exists( 'mpx_account_id', $preferences ) ) {
				$accountSettings = TP_ACCOUNT_OPTIONS_DEFAULTS();
				foreach ( $preferences as $key => $value ) {
					if ( array_key_exists( $key, $accountSettings ) ) {
						$accountSettings[ $key ] = $preferences[ $key ];
					}
				}
				update_option( TP_ACCOUNT_OPTIONS_KEY, $accountSettings );
			}
		}
	}

	/**
	 * Settings & Validation Region
	 */

	/**
	 * Registers initial plugin settings during initialization
	 */
	function theplatform_register_plugin_settings() {
		register_setting( TP_ACCOUNT_OPTIONS_KEY, TP_ACCOUNT_OPTIONS_KEY, array( $this, 'theplatform_account_options_validate' ) );
		register_setting( TP_PREFERENCES_OPTIONS_KEY, TP_PREFERENCES_OPTIONS_KEY, array( $this, 'theplatform_preferences_options_validate' ) );
		register_setting( TP_CUSTOM_METADATA_OPTIONS_KEY, TP_CUSTOM_METADATA_OPTIONS_KEY, array( $this, 'theplatform_dropdown_options_validate' ) );
		register_setting( TP_BASIC_METADATA_OPTIONS_KEY, TP_BASIC_METADATA_OPTIONS_KEY, array( $this, 'theplatform_dropdown_options_validate' ) );
		register_setting( TP_TOKEN_OPTIONS_KEY, TP_TOKEN_OPTIONS_KEY, 'strval' );
	}

	/**
	 * Compare a key between the old settings array and current settings array
	 *
	 * @param string $key The key of the setting to compare
	 * @param array $oldArray Current option array
	 * @param array $newArray New option array
	 *
	 * @return boolean False if the value is not set or unchanged, True if changed
	 */
	function theplatform_setting_changed( $key, $oldArray, $newArray ) {
		if ( ! isset( $oldArray[ $key ] ) && ! isset( $newArray[ $key ] ) ) {
			return false;
		}

		if ( empty( $oldArray[ $key ] ) && empty( $newArray[ $key ] ) ) {
			return false;
		}

		if ( $oldArray[ $key ] !== $newArray[ $key ] ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate the allow/omit dropdown options
	 *
	 * @param array $input Passed by Wordpress, an Array of upload/metadata options
	 *
	 * @return array A clean copy of the array, invalid values will be returned as "omit"
	 */
	function theplatform_dropdown_options_validate( $input ) {
		foreach ( $input as $key => $value ) {
			if ( ! in_array( $value, array( 'read', 'write', 'hide' ) ) ) {
				$input[ $key ] = "hide";
			}
		}

		return $input;
	}

	/**
	 * Validate mpx Account Settings for invalid input
	 *
	 * @param array $input Passed by Wordpress, an Array of mpx options
	 *
	 * @return array A cleaned up copy of the array, invalid values will be cleared.
	 */
	function theplatform_account_options_validate( $input ) {
		require_once( dirname( __FILE__ ) . '/thePlatform-API.php' );
		$tp_api   = new ThePlatform_API;
		$defaults = TP_ACCOUNT_OPTIONS_DEFAULTS();

		if ( ! is_array( $input ) || $input['mpx_username'] === 'mpx/' ) {
			return $defaults;
		}

		$account_is_verified = $tp_api->verify_account_settings();
		if ( $account_is_verified ) {

			if ( strpos( $input['mpx_account_id'], '|' ) !== false ) {
				$ids                      = explode( '|', $input['mpx_account_id'] );
				$input['mpx_account_id']  = $ids[0];
				$input['mpx_account_pid'] = $ids[1];
			}

			if ( strpos( $input['mpx_region'], '|' ) !== false ) {
				$ids                 = explode( '|', $input['mpx_region'] );
				$input['mpx_region'] = $ids[0];
			}
		}

		foreach ( $input as $key => $value ) {
			$input[ $key ] = sanitize_text_field( $value );
		}

		// If username, account id, or region have changed, reset settings to default
		$old_preferences = get_option( TP_ACCOUNT_OPTIONS_KEY );
		if ( $old_preferences ) {
			$updates = false;
			// If the username changes, reset all preferences except user/pass
			if ( $this->theplatform_setting_changed( 'mpx_username', $old_preferences, $input ) ) {
				$input['mpx_region']      = $defaults['mpx_region'];
				$input['mpx_account_pid'] = $defaults['mpx_account_pid'];
				$input['mpx_account_id']  = $defaults['mpx_account_id'];
				$updates                  = true;
			}

			// If the region changed, reset all preferences, but keep the new account settings
			if ( $this->theplatform_setting_changed( 'mpx_region', $old_preferences, $input ) ) {
				$updates = true;
			}

			// If the account changed, reset all preferences, but keep the new account settings
			if ( $this->theplatform_setting_changed( 'mpx_account_id', $old_preferences, $input ) ) {
				$updates = true;
			}
			// Clear old options
			if ( $updates ) {
				delete_option( TP_PREFERENCES_OPTIONS_KEY );
				delete_option( TP_CUSTOM_METADATA_OPTIONS_KEY );
				delete_option( TP_BASIC_METADATA_OPTIONS_KEY );
				delete_option( TP_TOKEN_OPTIONS_KEY );
			}
		}

		return $input;
	}

	/**
	 * Validate mpx Settings for invalid input
	 *
	 * @param array $input Passed by Wordpress, an Array of mpx options
	 *
	 * @return array A cleaned up copy of the array, invalid values will be cleared.
	 */
	function theplatform_preferences_options_validate( $input ) {
		require_once( dirname( __FILE__ ) . '/thePlatform-API.php' );
		$tp_api = new ThePlatform_API;

		$account_is_verified = $tp_api->verify_account_settings();
		if ( $account_is_verified ) {
			$region_is_verified = $tp_api->verify_account_region();

			if ( isset( $input['default_player_name'] ) && strpos( $input['default_player_name'], '|' ) !== false ) {
				$ids                          = explode( '|', $input['default_player_name'] );
				$input['default_player_name'] = $ids[0];
				$input['default_player_pid']  = $ids[1];
			}

			// If the account is selected, but no player has been set, use the first
			// returned as the default.
			if ( ! isset( $input['default_player_name'] ) || empty( $input['default_player_name'] ) ) {
				if ( $region_is_verified ) {
					$players                      = $tp_api->get_players();
					$player                       = $players[0];
					$input['default_player_name'] = $player['title'];
					$input['default_player_pid']  = $player['pid'];
				} else {
					$input['default_player_name'] = '';
					$input['default_player_pid']  = '';
				}
			}

			// If the account is selected, but no upload server has been set, use the first
			// returned as the default.
			if ( ! isset( $input['mpx_server_id'] ) || empty ( $input['mpx_server_id'] ) ) {
				$input['mpx_server_id'] = 'DEFAULT_SERVER';
			}

			foreach ( $input as $key => $value ) {
				if ( $key == 'videos_per_page' || $key === 'default_width' || $key === 'default_height' ) {
					$input[ $key ] = intval( $value );
				} else {
					$input[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		return $input;
	}

	/**
	 * Embed Dialog Region
	 */
	/**
	 * TinyMCE filter hooks to add a new button
	 */
	function theplatform_tinymce_button() {
		if ( ! isset( $this->preferences ) ) {
			$this->preferences = get_option( TP_PREFERENCES_OPTIONS_KEY, array() );
		}

		if ( ! array_key_exists( 'embed_hook', $this->preferences ) ) {
			return;
		}

		if ( in_array( $this->preferences['embed_hook'], array( 'both', 'mediabutton' ) ) ) {
			add_filter( "mce_external_plugins", array( $this, "theplatform_register_tinymce_javascript" ) );
			add_filter( 'mce_buttons', array( $this, 'theplatform_register_buttons' ) );
			add_filter( 'tiny_mce_before_init', array( $this, 'theplatform_tinymce_settings' ) );
		}
	}

	/**
	 * Register a new button in TinyMCE
	 *
	 * @param array $buttons A list of TinyMCE buttons
	 *
	 * @return array Updated array of buttons with out button
	 */
	function theplatform_register_buttons( $buttons ) {
		array_push( $buttons, "|", "theplatform" );

		return $buttons;
	}

	/**
	 * Load the TinyMCE plugin
	 *
	 * @param  array $plugin_array Array of TinyMCE Plugins
	 *
	 * @return array The array of TinyMCE plugins with our plugin added
	 */
	function theplatform_register_tinymce_javascript( $plugin_array ) {
		$plugin_array['theplatform'] = plugins_url( '/js/theplatform.tinymce.plugin.js', __file__ );

		return $plugin_array;
	}

	/**
	 * Add our nonce to tinymce so we can call our templates
	 *
	 * @param  array $settings tinyMCE settings
	 *
	 * @return array The array of tinyMCE settings with our value added
	 */
	function theplatform_tinymce_settings( $settings ) {
		$settings['theplatform_media_nonce'] = wp_create_nonce( 'theplatform-ajax-nonce-theplatform_media' );

		return $settings;
	}

	/**
	 * Outputs thePlatform's Media Button
	 */
	function theplatform_media_button() {
		if ( ! isset( $this->preferences ) ) {
			$this->preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );
		}

		if ( ! array_key_exists( 'embed_hook', $this->preferences ) ) {
			return;
		}

		if ( in_array( $this->preferences['embed_hook'], array( 'tinymce', 'both' ) ) ) {
			$image_url = plugins_url( '/images/embed_button.png', __FILE__ );
			echo '<a href="#" class="button" id="theplatform-media-button"><img src="' . esc_url( $image_url ) . '" alt="thePlatform" style="vertical-align: text-top; height: 18px; width: 18px;">thePlatform</a>';
		}
	}

	/**
	 * Enqueue thePlatform's media button callback
	 */
	function theplatform_enqueue_media_button_scripts() {
		wp_enqueue_script( 'tp_media_button_js' );
	}

	/**
	 * Shortcode Region
	 */

	/**
	 * Shortcode Callback
	 *
	 * @param array $atts Shortcode attributes
	 *
	 * @return string thePlatform video embed shortcode
	 */
	function shortcode( $atts ) {
		if ( ! class_exists( 'ThePlatform_API' ) ) {
			require_once( dirname( __FILE__ ) . '/thePlatform-API.php' );
		}

		if ( ! isset( $this->preferences ) ) {
			$this->preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );
		}

		if ( ! isset( $this->account ) ) {
			$this->account = get_option( TP_ACCOUNT_OPTIONS_KEY );
		}

		list( $account, $width, $height, $media, $player, $mute, $autoplay, $loop, $tag, $embedded, $params ) = array_values( shortcode_atts( array(
				'account'  => '',
				'width'    => '',
				'height'   => '',
				'media'    => '',
				'player'   => '',
				'mute'     => '',
				'autoplay' => '',
				'loop'     => '',
				'tag'      => '',
				'embedded' => '',
				'params'   => ''
			), $atts
			)
		);

		if ( empty( $width ) ) {
			$width = (int) $this->preferences['default_width'];
		}
		if ( strval( $width ) === '0' ) {
			$width = 500;
		}

		if ( empty( $height ) ) {
			$height = $this->preferences['default_height'];
		}
		if ( strval( $height ) === '0' ) {
			$height = floor( $width * 9 / 16 );
		}

		$mute     = $this->check_shortcode_parameter( $mute, 'false', array( 'true', 'false' ) );
		$loop     = $this->check_shortcode_parameter( $loop, 'false', array( 'true', 'false' ) );
		$autoplay = $this->check_shortcode_parameter( $autoplay, $this->preferences['autoplay'], array( 'false', 'true' ) );
		$embedded = $this->check_shortcode_parameter( $embedded, $this->preferences['player_embed_type'], array( 'true', 'false' ) );
		$tag      = $this->check_shortcode_parameter( $tag, $this->preferences['embed_tag_type'], array( 'iframe', 'script' ) );

		if ( empty( $media ) ) {
			return '<!--Syntax Error: Required Media parameter missing. -->';
		}

		if ( empty( $player ) ) {
			return '<!--Syntax Error: Required Player parameter missing. -->';
		}

		if ( empty ( $account ) ) {
			$account = $this->account['mpx_account_pid'];
		}


		if ( ! is_feed() ) {
			$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, $tag, $embedded, $loop, $mute, $params );
			$output = apply_filters( 'tp_embed_code', $output );
		} else {
			switch ( $this->preferences['rss_embed_type'] ) {
				case 'article':
					$output = '[Sorry. This video cannot be displayed in this feed. <a href="' . esc_url( get_permalink() ) . '">View your video here.]</a>';
					break;
				case 'iframe':
					$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, 'iframe', $embedded, $loop, $mute, $params );
					break;
				case 'script':
					$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, 'script', $embedded, $loop, $mute, $params );
					break;
				default:
					$output = '[Sorry. This video cannot be displayed in this feed. <a href="' . esc_url( get_permalink() ) . '">View your video here.]</a>';
					break;
			}
			$output = apply_filters( 'tp_rss_embed_code', $output );
		}

		return $output;
	}

	/**
	 * Checks a shortcode value is valid and if not returns a default value
	 *
	 * @param string $value The shortcode parameter value
	 * @param string $defaultValue The default value to return if a user entered an invalid entry.
	 * @param array $allowedValues An array of valid values for the shortcode parameter
	 *
	 * @return string The final value
	 */
	function check_shortcode_parameter( $value, $defaultValue, $allowedValues ) {

		$value = strtolower( $value );

		if ( empty ( $value ) ) {
			return $defaultValue;
		} else if ( in_array( $value, $allowedValues ) ) {
			return $value;
		}

		if ( ! empty ( $defaultValue ) ) {
			return $defaultValue;
		}

		return $allowedValues[0];
	}

	/**
	 * Called by the plugin shortcode callback function to construct a media embed iframe.
	 *
	 * @param string $accountPID Account of the user embedding the media asset
	 * @param string $releasePID Identifier of the media object to embed
	 * @param string $playerPID Identifier of the player to display the embedded media asset in
	 * @param string $player_width The width of the embedded player
	 * @param string $player_height The height of the embedded player
	 * @param boolean $autoplay Whether or not to loop the embedded media automatically
	 * @param boolean $tag script or iframe embed tag style
	 * @param boolean $embedded Whether the embed code will have /embed/ in the URI
	 * @param boolean $loop Set the embedded media to loop, false by default
	 * @param boolean $mute Whether or not to mute the audio channel of the embedded media asset, false by default
	 * @param string $params Any additional parameters to add to the embed code
	 *
	 * @return string An iframe tag sourced from the selected media embed URL
	 */
	function get_embed_shortcode( $accountPID, $releasePID, $playerPID, $player_width, $player_height, $autoplay, $tag, $embedded, $loop = false, $mute = false, $params = '' ) {

		$url = TP_API_PLAYER_EMBED_BASE_URL . urlencode( $accountPID ) . '/' . urlencode( $playerPID );

		if ( $embedded === 'true' ) {
			$url .= '/embed';
		}

		$url .= '/select/' . $releasePID;

		$url = apply_filters( 'tp_base_embed_url', $url );

		if ( $tag == 'script' ) {
			$url = add_query_arg( 'form', 'javascript', $url );
		} else {
			$url = add_query_arg( 'form', 'html', $url );
		}

		if ( $loop !== "false" ) {
			$url = add_query_arg( 'loop', 'true', $url );
		}

		if ( $autoplay !== "false" ) {
			$url = add_query_arg( 'autoPlay', 'true', $url );
		}

		if ( $mute !== "false" ) {
			$url = add_query_arg( 'mute', 'true', $url );
		}

		if ( $params !== '' ) {
			$url .= '&' . $params;
		}

		if ( $embedded == 'false' && $tag == 'script' ) {
			$url = add_query_arg( array( 'videoHeight' => $player_height, 'videoWidth' => $player_width ), $url );
		}

		$url = apply_filters( 'tp_full_embed_url', $url );

		if ( $tag == "script" ) {
			return '<div class="tpEmbed" style="width:' . esc_attr( $player_width ) . 'px; height:' . esc_attr( $player_height ) . 'px;"><script type="text/javascript" src="' . esc_url( $url ) . '"></script></div>';
		} else { //Assume iframe
			return '<iframe class="tpEmbed" src="' . esc_url( $url ) . '" height="' . esc_attr( $player_height ) . '" width="' . esc_attr( $player_width ) . '" frameBorder="0" seamless="seamless" allowFullScreen></iframe>';
		}
	}
}

// Instantiate thePlatform plugin on WordPress init
add_action( 'init', array( 'ThePlatform_Plugin', 'init' ) );
