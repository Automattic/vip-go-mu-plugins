<?php

/**
 * @package Janrain Capture
 *
 * Admin interface for plugin options
 *
 */
class JanrainCaptureAdmin {

	private $postMessage;
	private $fields;

	/**
	 * Initializes plugin name, builds array of fields to render.
	 *
	 * @param string $name
	 *	 The plugin name to use as a namespace
	 */
	public function __construct() {
		$this->postMessage = array( 'class' => '', 'message' => '' );
		$this->fields = array();
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Method bound to the init action.
	 */
	public function init() {
		$site_url = site_url();
		$this->fields = array(
			array(
				'name' => JanrainCapture::$name . '_address',
				'title' => 'Application Url',
				'description' => 'Your Capture application url <br/>(example: https://demo.janraincapture.com)',
				'required' => true,
				'default' => '',
				'type' => 'long-text',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_app_id',
				'title' => 'Application ID',
				'description' => 'Your Capture Application ID',
				'required' => true,
				'default' => '',
				'type' => 'text',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_client_id',
				'title' => 'API Client ID',
				'description' => 'Your Capture Client ID',
				'required' => true,
				'default' => '',
				'type' => 'long-text',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_client_secret',
				'title' => 'API Client Secret',
				'description' => 'Your Capture Client Secret',
				'required' => true,
				'default' => '',
				'type' => 'long-text',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_packages',
				'title' => 'Packages',
				'description' => 'Change this only when instructed to do so (default: capture & login)',
				'required' => true,
				'type' => 'multiselect',
				'default' => array( 0 => 'capture', 1 => 'login' ),
				'options' => array( 0 => 'capture', 1 => 'login', 3 => 'share' ),
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_engage_url',
				'title' => 'Engage Application Url',
				'description' => 'Your Janrain Engage Applicaiton url <br/>(example: https://capturewidget.rpxnow.com)',
				'default' => '',
				'required' => false,
				'type' => 'long-text',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_federate',
				'title' => 'Federate Settings',
				'type' => 'title',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_sso_enabled',
				'title' => 'Enable SSO',
				'description' => 'Enable/Disable SSO for Capture',
				'default' => '0',
				'type' => 'checkbox',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_sso_address',
				'title' => 'Application Domain',
				'description' => 'Your Janrain Federate SSO domain <br/>(example: demo.janrainsso.com)',
				'default' => '',
				'type' => 'text',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_backplane_settings',
				'title' => 'Backplane Settings',
				'type' => 'title',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_backplane_enabled',
				'title' => 'Enable Backplane',
				'description' => 'Enable/Disable Backplane for Capture',
				'default' => '0',
				'type' => 'checkbox',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_bp_server_base_url',
				'title' => 'Server Base URL',
				'description' => 'Your Backplane Server Base URL',
				'prefix' => 'https://',
				'default' => '',
				'type' => 'text',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_bp_bus_name',
				'title' => 'Bus Name',
				'description' => 'Your Backplane Bus Name',
				'default' => '',
				'type' => 'text',
				'screen' => 'main',
			),
			array(
				'name' => JanrainCapture::$name . '_bp_version',
				'title' => 'Backplane Version',
				'description' => 'Choose from Backplane Version 1.2 or 2.0',
				'type' => 'select',
				'default' => 1.2,
				'options' => array( 1.2, 2 ),
				'screen' => 'main',
			),

			// widget UI settings
			array(
				'name' => JanrainCapture::$name . '_load_js',
				'title' => 'Url for load.js file',
				'description' => 'The absolute url (minus protocol) of the Widget load file',
				'default' => '',
				'required' => true,
				'type' => 'text',
				'screen' => 'ui',
			),
			array(
				'name' => JanrainCapture::$name . '_widget_edit_page',
				'title' => 'Edit Profile Page',
				'description' => 'Create a page with the shortcode: [janrain_capture action="edit_profile"] and remove it from the menu.<br/>(example: '.site_url().'/edit-profile)',
				'required' => true,
				'default' => site_url() . '/edit-profile/',
				'type' => 'long-text',
				'screen' => 'ui',
			),
			array(
				'name' => JanrainCapture::$name . '_ui_share_enabled',
				'title' => 'Enable Social Sharing',
				'description' => 'Load the JS and CSS required for the Engage Share Widget',
				'default' => '0',
				'type' => 'checkbox',
				'screen' => 'ui',
			),
			array(
				'name' => JanrainCapture::$name . '_rpx_share_providers',
				'title' => 'Share Providers to Display',
				'description' => 'Choose share providers to display. Note: You must configure all providers on your Enagage Dashboard: https://rpxnow.com',
				'type' => 'multiselect',
				'default' => array( 0 => 'email', 1 => 'facebook', 2 => 'linkedin', 3 => 'twitter' ),
				'options' => array( 0 => 'email', 1 => 'facebook', 2 => 'linkedin', 3 => 'mixi', 4 => 'myspace', 5 => 'twitter', 6 => 'yahoo' ),
				'screen' => 'ui',
			),
			array(
				'name' => JanrainCapture::$name . '_screens',
				'title' => locate_template( 'janrain-capture-screens/signin.html' ) ? 'Screens located! <br />' . get_stylesheet_directory_uri() . '/<i>janrain-capture-screens</i>/' : '<b>Screens NOT found. <br/> Please copy: '. plugin_dir_url( __FILE__ ) .'<i>janrain-capture-screens</i><br/> folder to: ' . get_stylesheet_directory_uri(),
				'type' => 'title',
				'screen' => 'ui',
			),
		);

		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			$this->on_post();
		}
	}

	/**
	 * Method bound to the admin_menu action.
	 */
	public function admin_menu() {
		$optPage = add_menu_page(
			__( 'Janrain Capture' ),
			__( 'Janrain Capture' ),
			'manage_options', JanrainCapture::$name, array( $this, 'main' )
		);
		$uiPage  = add_submenu_page(
			JanrainCapture::$name,
			__( 'Janrain Capture' ),
			__( 'UI Settings' ),
			'manage_options', JanrainCapture::$name . '_ui', array( $this, 'ui' )
		);
	}

	/**
	 * Method bound to the Janrain Capture main menu.
	 */
	public function main() {
		$args = (object) array( 'title' => 'Janrain Capture Settings', 'action' => 'main');
		$this->print_admin( $args );
	}

	/**
	 * Method bound to the ui menu.
	 */
	public function ui() {
		$args = (object) array( 'title' => 'UI Settings', 'action' => 'ui' );
		$this->print_admin( $args );
	}

	/**
	 * Method to print the admin page markup.
	 *
	 * @param stdClass $args
	 *	 Object with page title and action variables
	 */
	public function print_admin( $args ) {
		$name = JanrainCapture::$name;
		$nonce = wp_nonce_field( $name . '_action' );
		echo <<<HEADER
<div id="message" class="{$this->postMessage['class']} fade">
	<p><strong>
	{$this->postMessage['message']}
	</strong></p>
</div>
<div class="wrap">
	<h2>{$args->title}</h2>
	<form method="post" id="{$name}_{$args->action}">
	<table class="form-table">
		<tbody>
HEADER;

		foreach ( $this->fields as $field ) {
			if ( $field['screen'] == $args->action )
				$this->print_field( $field );
		}

		echo <<<FOOTER
		</tbody>
	</table>
	$nonce
	<p class="submit">
		<input type="hidden" name="{$name}_action" value="{$args->action}" />
		<input type="submit" class="button-primary" value="Save Changes" />
	</p>
	</form>
</div>
FOOTER;
	}

	/**
	 * Method to print field-level markup.
	 *
	 * @param array $field
	 *	 A structured field definition with strings used in generating markup.
	 */
	public function print_field( $field ) {
		$default = isset( $field['default'] ) ? $field['default'] : '';
		$value = JanrainCapture::get_option( $field['name'], $default );
		$prefix = isset($field['prefix']) ? $field['prefix'] : '';
		// echo $field['name'] . ": " . $value . "<br>Def: " . $default . "<br>"; return;
		$r = ( isset( $field['required'] ) && $field['required'] == true ) ? ' <span class="description">(required)</span>' : '';
		switch ( $field['type'] ) {
			case 'text':
				echo <<<TEXT
		<tr>
			<th><label for="{$field['name']}">{$field['title']}$r</label></th>
			<td>
			$prefix<input type="text" name="{$field['name']}" value="$value" style="width:200px" />
			<span class="description">{$field['description']}</span>
			</td>
		</tr>
TEXT;
				break;
			case 'long-text':
				echo <<<LONGTEXT
		<tr>
			<th><label for="{$field['name']}">{$field['title']}$r</label></th>
			<td>
			<input type="text" name="{$field['name']}" value="$value" style="width:400px" />
			<span class="description">{$field['description']}</span>
			</td>
		</tr>
LONGTEXT;
				break;
			case 'textarea':
				echo <<<TEXTAREA
		<tr>
			<th><label for="{$field['name']}">{$field['title']}$r</label></th>
			<td>
			<span class="description">{$field['description']}</span><br/>
			<textarea name="{$field['name']}" rows="10" cols="80">$value</textarea>
			</td>
		</tr>
TEXTAREA;
				break;
			case 'password':
				echo <<<PASSWORD
		<tr>
			<th><label for="{$field['name']}">{$field['title']}$r</label></th>
			<td>
			<input type="password" name="{$field['name']}" value="$value" style="width:150px" />
			<span class="description">{$field['description']}</span>
			</td>
		</tr>
PASSWORD;
				break;
			case 'select':
				sort( $field['options'] );
				echo <<<SELECT
		<tr>
			<th><label for="{$field['name']}">{$field['title']}$r</label></th>
			<td>
				<select name="{$field['name']}" value="$value">
SELECT;
				foreach ( $field['options'] as $option ) {
					$selected = ( $value == $option ) ? ' selected="selected"' : '';
					echo "<option value=\"{$option}\"{$selected}>$option</option>";
				}
				echo <<<ENDSELECT
				</select>
				<span class="description">{$field['description']}</span>
			</td>
		</tr>
ENDSELECT;
				break;
			case 'multiselect':
				sort( $field['options'] );
				$mselect_height = ( count( $field['options'] ) * 24 ) . 'px';
				echo <<<MSELECT
		<tr>
			<th><label for="{$field['name']}[]">{$field['title']}$r</label></th>
			<td valign="top">
				<select name="{$field['name']}[]" multiple="multiple" style="height: {$mselect_height}">
MSELECT;
				foreach ( $field['options'] as $option ) {
					$selected = in_array( $option, $value ) !== false ? ' selected="selected"' : '';
					echo "<option value=\"{$option}\"{$selected}>$option</option>";
				}
				echo <<<MENDSELECT
				</select>
				{$field['description']}
			</td>
		</tr>
MENDSELECT;
				break;
			case 'checkbox':
				$checked = ($value == '1') ? ' checked="checked"' : '';
				echo <<<CHECKBOX
		<tr>
			<th><label for="{$field['name']}">{$field['title']}$r</label></th>
			<td>
			<input type="checkbox" name="{$field['name']}" value="1"$checked />
			<span class="description">{$field['description']}</span>
			</td>
		</tr>
CHECKBOX;
				break;
			case 'title':
				echo <<<TITLE
		<tr>
			<td colspan="2">
			<h3 class="title">{$field['title']}</h3>
			</td>
		</tr>
TITLE;
				break;
		}
	}

	/**
	 * Method to receive and store submitted options when posted.
	 */
	public function on_post() {
		if ( isset( $_POST[JanrainCapture::$name . '_action'] ) &&
				current_user_can( 'manage_options' ) &&
				check_admin_referer( JanrainCapture::$name . '_action' ) ) {
			foreach ( $this->fields as $field ) {
				if ( isset( $_POST[$field['name']] ) ) {
					$value = $_POST[ $field['name'] ];
					if ( is_string( $value ) ) {
						$value = sanitize_text_field( $value );
					} elseif ( is_array( $value ) ) {
						foreach ( $value as $k => $v ) {
							if ( is_string( $v ) ) {
								$value[$k] = sanitize_text_field( $v );
							}
						}
					}
					JanrainCapture::update_option( $field['name'], $value );
				} else {
					if ( $field['type'] == 'checkbox' && $field['screen'] == $_POST[JanrainCapture::$name . '_action'] ) {
						$value = '0';
						JanrainCapture::update_option( $field['name'], $value );
					} else {
						if ( JanrainCapture::get_option( $field['name'] ) === false
								&& isset($field['default'])) {
							JanrainCapture::update_option( $field['name'], $field['default'] );
						}
					}
				}
			}
		}
	}
}
