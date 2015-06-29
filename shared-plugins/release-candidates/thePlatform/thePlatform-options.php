<?php
/* thePlatform Video Manager Wordpress Plugin
  Copyright (C) 2013-2015 thePlatform, LLC

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


?>
	<script id="column-template" type="text/template">
		<div class="colContainer">
			<h3><%= _.template.formatColName(colName) %></h3>
			<ul data-col="<%= colName %>" class="sortable"></ul>
		</div>
	</script> <?php

$tp_admin_cap = apply_filters( TP_ADMIN_CAP, TP_ADMIN_DEFAULT_CAP );
if ( ! current_user_can( $tp_admin_cap ) ) {
	wp_die( '<div class="error"><p>You do not have sufficient permissions to manage this plugin</p></div>' );
}

/**
 * Handle WordPress Settings API
 */
class ThePlatform_Options {

	private $account_is_verified;
	private $region_is_verified;
	private $regions = array( 'us', 'eu' );
	/*
	 * WP Option key
	 */
	private $plugin_options_key = 'theplatform-settings';

	/*
	 * An array of tabs representing the admin settings interface.
	 */
	private $plugin_settings_tabs = array();
	private $tp_api;

	function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		$this->tp_api = new ThePlatform_API;

		$this->load_options();
		$this->enqueue_scripts();
		$this->register_account_options();
		$this->register_preferences_options();
		$this->register_basic_metadata_options();
		$this->register_custom_metadata_options();
		// $this->register_advanced_options();

		//Render the page
		$this->plugin_options_page();
	}

	/**
	 * Enqueue our javascript file
	 */
	function enqueue_scripts() {
		wp_enqueue_script( 'tp_options_js' );
		wp_enqueue_style( 'tp_options_css' );
	}

	/**
	 * Loads thePlatform plugin options from
	 * the database into their respective arrays. Uses
	 * array_merge to merge with default values if they're
	 * missing.
	 */
	function load_options() {
		// Get existing options, or empty arrays if no options exist
		$this->account_options     = get_option( TP_ACCOUNT_OPTIONS_KEY, array() );
		$this->preferences_options = get_option( TP_PREFERENCES_OPTIONS_KEY, array() );
		$this->metadata_options    = get_option( TP_CUSTOM_METADATA_OPTIONS_KEY, array() );
		$this->upload_options      = get_option( TP_BASIC_METADATA_OPTIONS_KEY, array() );
		$this->advanced_options    = get_option( TP_ADVANCED_OPTIONS_KEY, array() );

		// Initialize option defaults
		$this->account_options = array_merge( TP_ACCOUNT_OPTIONS_DEFAULTS(), $this->account_options );

		if ( empty( $this->upload_options ) ) {
			update_option( TP_BASIC_METADATA_OPTIONS_KEY, TP_BASIC_METADATA_OPTIONS_DEFAULTS() );
		}

		if ( empty( $this->metadata_options ) ) {
			update_option( TP_CUSTOM_METADATA_OPTIONS_KEY, array() );
		}

		if ( empty( $this->preferences_options ) ) {
			update_option( TP_PREFERENCES_OPTIONS_KEY, TP_PREFERENCES_OPTIONS_DEFAULTS() );
		}

		if ( empty( $this->advanced_options ) ) {
			update_option( TP_ADVANCED_OPTIONS_KEY, TP_ADVANCED_OPTIONS_DEFAULTS() );
		}

		$this->account_is_verified = $this->tp_api->verify_account_settings();

		if ( $this->account_is_verified ) {
			$this->region_is_verified = $this->tp_api->verify_account_region();
		} else {
			$this->region_is_verified = false;

			if ( $this->account_options['mpx_username'] != 'mpx/' ) {
				echo '<div id="message" class="error">';
				echo '<p><strong>Sign in to thePlatform failed, please check your account settings.</strong></p>';
				echo '</div>';
			}
		}
	}

	/*
	 * Registers the account options via the Settings API,
	 * appends the setting to the tabs array of the object.
	 */
	function register_account_options() {
		$this->plugin_settings_tabs[ TP_ACCOUNT_OPTIONS_KEY ] = 'Account Settings';
		$this->parse_options_fields( TP_ACCOUNT_OPTIONS_FIELDS(), $this->account_options, TP_ACCOUNT_OPTIONS_KEY );
	}

	/*
	 * Registers the advanced options via the Settings API,
	 * appends the setting to the tabs array of the object.
	 */
	function register_advanced_options() {
		$this->plugin_settings_tabs[ TP_ADVANCED_OPTIONS_KEY ] = 'Advanced Settings';
		$this->parse_options_fields( TP_ADVANCED_OPTIONS_FIELDS(), $this->advanced_options, TP_ADVANCED_OPTIONS_KEY );
	}

	/*
	 * Registers the preference options via the Settings API,
	 * appends the setting to the tabs array of the object.
	 */
	function register_preferences_options() {
		if ( ! $this->account_is_verified || ! $this->region_is_verified ) {
			return;
		}

		if ( empty ( $this->account_options['mpx_account_id'] ) ) {
			return;
		}

		$this->plugin_settings_tabs[ TP_PREFERENCES_OPTIONS_KEY ] = 'Plugin Settings';
		$this->parse_options_fields( TP_PREFERENCES_OPTIONS_FIELDS(), $this->preferences_options, TP_PREFERENCES_OPTIONS_KEY );
	}

	function parse_options_fields( $settings, $options, $options_key ) {
		foreach ( $settings as $section ) {
			add_settings_section( $section['id'], esc_html( $section['title'] ), array( $this, $section['callback'] ), $options_key );
			foreach ( $section['fields'] as $field ) {
				if ( $field['type'] === 'callback' ) {
					$callback = 'field_' . $field['id'] . '_option';
				} else {
					$callback = 'field_' . $field['type'] . '_option';
				}
				add_settings_field( $field['id'], esc_html( $field['title'] ), array( $this, $callback ), $options_key, $section['id'], array( 'field' => $field, 'options' => $options, 'key' => $options_key ) );
			}
		}
	}

	/*
	 * Registers the custom metadata options and appends the
	 * key to the plugin settings tabs array.
	 */
	function register_custom_metadata_options() {

		//Check for uninitialized options
		if ( ! $this->account_is_verified || ! $this->region_is_verified ) {
			return;
		}

		$this->plugin_settings_tabs[ TP_CUSTOM_METADATA_OPTIONS_KEY ] = 'Custom Metadata';
		$this->metadata_fields                                        = $this->tp_api->get_custom_metadata_fields( true );
		add_settings_section( 'section_metadata_options', 'Custom Metadata Settings', array( $this, 'section_custom_metadata_desc' ), TP_CUSTOM_METADATA_OPTIONS_KEY );

		foreach ( $this->metadata_fields as $field ) {
			if ( ! array_key_exists( $field['id'], $this->metadata_options ) ) {
				$this->metadata_options[ $field['id'] ] = 'hide';
			}

			add_settings_field( $field['id'], esc_html( $field['title'] ), array( $this, 'field_custom_metadata_option' ), TP_CUSTOM_METADATA_OPTIONS_KEY, 'section_metadata_options', $field );
		}
	}

	/*
	 * Registers the basic metadata options and appends the
	 * key to the plugin settings tabs array.
	 */
	function register_basic_metadata_options() {

		if ( ! $this->account_is_verified || ! $this->region_is_verified ) {
			return;
		}

		$this->plugin_settings_tabs[ TP_BASIC_METADATA_OPTIONS_KEY ] = 'Basic Metadata';

		$basic_fields = TP_BASIC_METADATA_OPTIONS_DEFAULTS();

		add_settings_section( 'section_upload_options', 'Basic Metadata Settings', array( $this, 'section_basic_metadata_desc' ), TP_BASIC_METADATA_OPTIONS_KEY );

		foreach ( $basic_fields as $field => $value ) {
			if ( ! array_key_exists( $field, $this->upload_options ) ) {
				$this->upload_options[ $field ] = 'write';
			}

			$field_title = ( strstr( $field, '$' ) !== false ) ? substr( strstr( $field, '$' ), 1 ) : $field;

			add_settings_field( $field, esc_html( ucfirst( $field_title ) ), array( $this, 'field_basic_metadata_option' ), TP_BASIC_METADATA_OPTIONS_KEY, 'section_upload_options', array( 'field' => $field ) );
		}
	}

	/**
	 * Provide a description to the mpx Account Settings Section
	 */
	function section_mpx_account_desc() {
		echo 'Set your mpx credentials and Account. If you do not have an account, please reach out to thePlatform.';
		echo '<div id="TP_PAGE_KEY" style="display: none;">TP_PREFERENCES</div>';
	}

	/**
	 * Provide a description to the mpx Prefences Section
	 */
	function section_preferences_desc() {
		echo 'Configure general preferences below.';
		echo '<div id="TP_PAGE_KEY" style="display: none;">TP_PREFERENCES</div>';
	}

	/**
	 * Provide a description to the mpx Embed Settings Section
	 */
	function section_embed_desc() {
		echo 'Configure embedding defaults.';
	}

	/**
	 * Provide a description to the mpx Metadata Section
	 */
	function section_custom_metadata_desc() {
		echo 'Drag and drop the custom metadata fields that you would like to be readable, writable, or omitted when uploading and editing media.';
		echo '<div id="TP_PAGE_KEY" style="display: none;">TP_FIELDS</div>';
	}

	/**
	 * Provide a description to the mpx Upload Fields Section
	 */
	function section_basic_metadata_desc() {
		echo 'Drag and drop the basic metadata fields that you would like to be readable, writable, or omitted when uploading and editing media.';
		echo '<div id="TP_PAGE_KEY" style="display: none;">TP_FIELDS</div>';
	}

	/**
	 * Provide a description to the Advanced Options Section
	 */
	function section_advanced_desc() {
		echo 'Configure advanced plugin settings';
		echo '<div id="TP_PAGE_KEY" style="display: none;">TP_PREFERENCES</div>';
	}

	/**
	 * mpx Preferences Option field callbacks.
	 *
	 * @param array $args WP Options
	 */
	function field_select_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $field['values'] as $key => $value ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $options[ $field['id'] ], $value, false ) . '>' . esc_html( $key ) . '</option>';
		}
		$html .= '</select>';
		echo $html;
	}

	function field_boolean_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
		$html .= '<option value="true" ' . selected( $options[ $field['id'] ], 'true', false ) . '>True</option>';
		$html .= '<option value="false" ' . selected( $options[ $field['id'] ], 'false', false ) . '>False</option>';
		$html .= '</select>';
		echo $html;
	}

	function field_string_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<input class="tpOption" type="text" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $options[ $field['id'] ] ) . '" />';
		echo $html;
	}

	function field_hidden_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<input disabled style="background-color: lightgray" id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $options[ $field['id'] ] ) . '" />';
		echo $html;
	}

	function field_password_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<input class="tpOption" id="' . esc_attr( $field['id'] ) . '" type="password" name="' . esc_attr( $name ) . '" value="' . esc_attr( $options[ $field['id'] ] ) . '" autocomplete="off" />';
		if ( $field['id'] === 'mpx_password' ) {
			$html .= '<span id="verify-account"><button id="verify-account-button" type="button" name="verify-account-button">Verify Account Settings</button></span><div id="verify-account-dashicon" class="dashicons"></div>';
		}
		echo $html;
	}

	function field_default_publish_id_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
		$html .= '<option value="tp_wp_none">Do not publish</option>';

		if ( $this->account_options['mpx_account_id'] !== '' ) {
			$profiles = $this->tp_api->get_publish_profiles();
			foreach ( $profiles as $profile ) {
				$html .= '<option value="' . esc_attr( $profile['title'] ) . '"' . selected( $options[ $field['id'] ], $profile['title'], false ) . '>' . esc_html( $profile['title'] ) . '</option>';
			}
		}
		$html .= '</select>';
		echo $html;
	}

	function field_thumbnail_profile_id_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
		$html .= '<option value="tp_wp_none">None</option>';

		if ( $this->account_options['mpx_account_id'] !== '' ) {
			$profiles = $this->tp_api->get_thumbnail_encoding_profiles();
			foreach ( $profiles as $profile ) {
				$html .= '<option value="' . esc_attr( $profile['id'] ) . '"' . selected( $options[ $field['id'] ], $profile['id'], false ) . '>' . esc_html( $profile['title'] ) . '</option>';
			}
		}
		$html .= '</select>';
		echo $html;
	}

	function field_user_id_customfield_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '"" name="' . esc_attr( $name ) . '"/>';
		$html .= '<option value="(None)" ' . selected( $options[ $field['id'] ], '(None)', false ) . '>(None)</option>';
		foreach ( $this->metadata_fields as $metadata ) {
			$fieldName = $metadata['namespacePrefix'] . '$' . $metadata['fieldName'];
			$html .= '<option value="' . esc_attr( $fieldName ) . '" ' . selected( $options[ $field['id'] ], $fieldName, false ) . '>' . esc_html( $metadata['title'] ) . '</option>';
		}
		$html .= '</select>';
		echo $html;
	}

	function field_mpx_server_id_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
		if ( $this->account_options['mpx_account_id'] !== '' ) {
			$servers = $this->tp_api->get_servers();
			$html .= '<option value="DEFAULT_SERVER"' . selected( $options[ $field['id'] ], "DEFAULT_SERVER", false ) . '>Default Server</option>';
			foreach ( $servers as $server ) {
				$html .= '<option value="' . esc_attr( $server['id'] ) . '"' . selected( $options[ $field['id'] ], $server['id'], false ) . '>' . esc_html( $server['title'] ) . '</option>';
			}
		}
		$html .= '</select>';
		echo $html;
	}

	function field_default_player_name_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
		if ( $this->account_options['mpx_account_id'] !== '' ) {
			$players = $this->tp_api->get_players();
			foreach ( $players as $player ) {
				$html .= '<option value="' . esc_attr( $player['id'] ) . '|' . esc_attr( $player['pid'] ) . '"' . selected( $options[ $field['id'] ], $player['id'], false ) . '>' . esc_html( $player['title'] ) . '</option>';
			}
		}
		$html .= '</select>';
		echo $html;
	}

	function field_mpx_region_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
		$regions = $this->regions;
		foreach ( $regions as $region ) {
			$html .= '<option value="' . esc_attr( $region ) . '|' . esc_attr( $region ) . '"' . selected( $options[ $field['id'] ], $region, false ) . '>' . esc_html( strtoupper( $region ) ) . '</option>';
		}
		$html .= '</select>';

		if ( ! $this->region_is_verified ) {
			$html .= '<span style="color:red; font-weight:bold"> Please select the correct region the mpx account is located at</span>';
		}
		echo $html;
	}

	function field_mpx_account_id_option( $args ) {
		$field   = $args['field'];
		$options = $args['options'];
		$name    = $args['key'] . '[' . $field['id'] . ']';
		$html    = '<select class="tpOption" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $name ) . '">';
		if ( $this->account_is_verified ) {
			$subaccounts = $this->tp_api->get_subaccounts();
			foreach ( $subaccounts as $account ) {
				$html .= '<option value="' . esc_attr( $account['id'] ) . '|' . esc_attr( $account['pid'] ) . '"' . selected( $options[ $field['id'] ], $account['id'], false ) . '>' . esc_html( $account['title'] ) . '</option>';
			}
		}
		$html .= '</select>';

		if ( $this->account_options['mpx_account_id'] === '' ) {
			$html .= '<span style="color:red; font-weight:bold"> Please pick the mpx account to manage with Wordpress</span>';
		}
		echo $html;
	}

	/**
	 * Custom Metadata Option field callback.
	 */
	function field_custom_metadata_option( $args ) {
		$field_id  = $args['id'];
		$fieldName = $args['namespacePrefix'] . '$' . $args['fieldName'];

		$user_id_field = ( $fieldName === $this->preferences_options['user_id_customfield'] ) ? 'true' : 'false';
		if ( $user_id_field === 'true' && $this->metadata_options[ $field_id ] == 'write' ) {
			$this->metadata_options[ $field_id ] = 'hide';
		}
		$html = '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( TP_CUSTOM_METADATA_OPTIONS_KEY ) . '[' . esc_attr( $field_id ) . ']" class="sortableField" data-userfield="' . esc_attr( $user_id_field ) . '">';
		$html .= '<option value="read"' . selected( $this->metadata_options[ $field_id ], 'read', false ) . '>Read</option>';
		$html .= '<option value="write"' . selected( $this->metadata_options[ $field_id ], 'write', false ) . '>Write</option>';
		$html .= '<option value="hide"' . selected( $this->metadata_options[ $field_id ], 'hide', false ) . '>Hide</option>';
		$html .= '</select>';

		echo $html;
	}

	/**
	 * Basic Metadata Option field callback.
	 */
	function field_basic_metadata_option( $args ) {
		$field = $args['field'];

		$html = '<select id="' . esc_attr( $field ) . '" name="' . esc_attr( TP_BASIC_METADATA_OPTIONS_KEY ) . '[' . esc_attr( $field ) . ']" class="sortableField">';
		$html .= '<option value="read"' . selected( $this->upload_options[ $field ], 'read', false ) . '>Read</option>';
		$html .= '<option value="write"' . selected( $this->upload_options[ $field ], 'write', false ) . '>Write</option>';
		$html .= '<option value="hide"' . selected( $this->upload_options[ $field ], 'hide', false ) . '>Hide</option>';
		$html .= '</select>';

		echo $html;
	}

	/**
	 * Called during admin_menu, adds an options
	 * page under Settings called My Settings, rendered
	 * using the plugin_options_page method.
	 */
	function add_admin_menus() {
		add_options_page( 'thePlatform Plugin Settings', 'thePlatform', 'manage_options', $this->plugin_options_key, array( $this, 'plugin_options_page' ) );
	}

	/**
	 * Plugin Options page rendering goes here, checks
	 * for active tab and replaces key with the related
	 * settings key. Uses the plugin_options_tabs method
	 * to render the tabs.
	 */
	function plugin_options_page() {
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : TP_ACCOUNT_OPTIONS_KEY;

		if ( ! in_array( $tab, array( TP_ACCOUNT_OPTIONS_KEY, TP_PREFERENCES_OPTIONS_KEY, TP_CUSTOM_METADATA_OPTIONS_KEY, TP_BASIC_METADATA_OPTIONS_KEY ) ) ) {
			return;
		}
		?>
		<div class="wrap">
			<?php $this->plugin_options_tabs(); ?>
			<form method="POST" action="options.php" autocomplete="off">
				<?php
				settings_fields( $tab );
				do_settings_sections( $tab );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders our tabs in the plugin options page,
	 * walks through the object's tabs array and prints
	 * them one by one. Provides the heading for the
	 * plugin_options_page method.
	 */
	function plugin_options_tabs() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : TP_ACCOUNT_OPTIONS_KEY;
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
			$url    = '?page=' . $this->plugin_options_key . '&tab=' . $tab_key;
			echo '<a class="nav-tab ' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $tab_caption ) . '</a>';
		}
		echo '</h2>';
	}
}

if ( ! class_exists( 'ThePlatform_API' ) ) {
	require_once( dirname( __FILE__ ) . '/thePlatform-API.php' );
}

new ThePlatform_Options;
