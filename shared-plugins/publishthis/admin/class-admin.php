<?php

/**
 * defines our Publishing Actions Post Types.
 * -- These are used to poll our API and take curated content
 * -- and turn those documents into posts or pages
 * defines our PublishThis Settings
 * -- this defines our API key, debug settings, etc.
 */

class Publishthis_Admin {

	private $_option_group = 'publishthis_options';
	private $_settings_menu_slug = 'publishthis_settings';
	private $_settings_section = 'publishthis_settings_section';

	private $_screens;

	/**
	 *
	 *
	 * @desc Publishthis_Admin constructor.
	 */
	function __construct() {
		global $publishthis;

		// Init screens
		$this->_screens = array ( $publishthis->post_type, "edit-{$publishthis->post_type}" );

		// Actions
		add_action( 'admin_enqueue_scripts', array ( $this, 'add_help_tabs' ), 20 );
		add_action( 'admin_enqueue_scripts', array ( $this, 'enqueue_assets' ) );
		add_action( 'admin_menu', array ( $this, 'setup_menu' ), 11 );
		add_action( 'admin_notices', array ( $this, 'display_alerts' ), 0 );
		add_action( 'admin_init', array ( $this, 'update_cron' ), 0 );

		// Edit screen
		add_action( 'add_meta_boxes', array ( $this, 'add_meta_box' ) );
		add_action( 'pre_post_update', array ( $this, 'validate_publish_action' ), 999 );
		add_action( 'save_post', array ( $this, 'save_publish_action' ), 999, 2 );

		add_filter( 'post_updated_messages', array ( $this, 'add_post_updated_messages' ) );

		// List screen
		add_filter( "manage_{$publishthis->post_type}_posts_columns", array ( $this, 'edit_columns' ) );
		add_filter( 'post_row_actions', array ( $this, 'remove_quick_edit_link' ), 10, 2 );

		// Options
		add_action( 'admin_init', array ( $this, 'init_options' ) );
	}

	/**
	 *
	 *
	 * @desc Add help tabs
	 */
	function add_help_tabs() {
		global $publishthis, $current_screen;

		if ( !method_exists( $current_screen, 'set_help_sidebar' ) ) {
			return;
		}

		// All pages get the sidebar
		if ( in_array( $current_screen->id, $this->_screens ) ) {
			try {
				$current_screen->set_help_sidebar( '<p><strong>' . __ ( 'For more information:', 'publishthis' ) . '</strong></p>' .
					'<p><a href="http://docs.publishthis.com/" target="_blank">' . __ ( 'PublishThis Education Center', 'publishthis' ) . '</a></p>' );
			} catch ( Exception $ex ) {
				$publishthis->log->add( $ex->getMessage () );
			}
		}

		// Specific page help
		switch ( $current_screen->id ) {
			/*
			 * Settings page help section. Contains detailed info for each setting.
			 */
		case 'publishthis_page_publishthis_settings' :
			try {
				$current_screen->add_help_tab( array (
						'id'      => 'overview',
						'title'   => __ ( 'Overview', 'publishthis' ),
						'content' => '
										<p>The fields on this screen determine the basics of your PublishThis Curation setup.</p>
										<p>Pause Polling toggles whether or not the plugin will poll the PublishThis API for new content. Previously imported content will not be affected.</p>
										<p>API Tokens are what you use to access and use the PublishThis API. They can be found on the "API" tab of your PublishThis dashboard.</p>
										<p>Styling will include the PublishThis CSS file to provide a consistent format.</p>
										<p>Debug enables logging to <code>publishthis/logs/debug.log</code>.</p>' ) );
			} catch ( Exception $ex ) {
				$publishthis->log->add( $ex->getMessage () );
			}
			break;
			/*
			 * Publish Action edit page help section. Contains info about most important fields for this page.
			 */
		case $publishthis->post_type :
			try {
				$current_screen->add_help_tab( array(
						'id'      => 'overview',
						'title'   => __ ( 'Overview', 'publishthis' ),
						'content' => '
										<p>Poll Interval sets how often the plugin will poll for new content.</p>
										<p>Feed Template allows you to choose which feed template the Publish Action will use. Once a Feed Template is chosen, you will be able to select a Template Section.</p>
										<p>*All fields are required.</p>' ) );
			} catch ( Exception $ex ) {
				$publishthis->log->add( $ex->getMessage () );
			}
			break;
			/*
			 * Publish Action list page help section. 3 tabs with general info, available actions for single items and bulk actions.
			 */
		case "edit-{$publishthis->post_type}" :
			try {
				$current_screen->add_help_tab( array(
						'id'      => 'overview',
						'title'   => __ ( 'Overview', 'publishthis' ),
						'content' => '<p>This screen provides access to all of your Publish Actions.</p>' ) );
			} catch ( Exception $ex ) {
				$publishthis->log->add( $ex->getMessage () );
			}

			try {
				$current_screen->add_help_tab( array(
						'id'      => 'available_actions',
						'title'   => __ ( 'Available Actions', 'publishthis' ),
						'content' => '
										<p>Hovering over a row in the Publish Actions list will display action links that allow you to manage your Publish Action. You can perform the following actions:</p>
										<ul>
											<li><strong>Edit</strong> takes you to the editing screen for that Publish Action. You can also reach that screen by clicking on the Publish Action title.</li>
											<li><strong>Trash</strong> removes your Publish Action from this list and places it in the trash, from which you can permanently delete it.</li>
										</ul>' ) );
			} catch ( Exception $ex ) {
				$publishthis->log->add( $ex->getMessage () );
			}

			try {
				$current_screen->add_help_tab( array(
						'id'      => 'bulk_actions',
						'title'   => __ ( 'Bulk Actions', 'publishthis' ),
						'content' => '
										<p>You can also edit or move multiple posts to the trash at once. Select the posts you want to act on using the checkboxes, then select the action you want to take from the Bulk Actions menu and click Apply.</p>' ) );
			} catch ( Exception $ex ) {
				$publishthis->log->add( $ex->getMessage () );
			}
			break;
		default :
			break;
		}
	}


	/**
	 *
	 *
	 * @param string  $messages
	 * @desc Generates custom message when Publishing Action saved or updated.
	 * @return string
	 */
	function add_post_updated_messages( $messages ) {
		global $publishthis;

		return $messages + array( $publishthis->post_type => array( 1 => __ ( 'Publishing Action updated.', 'publishthis' ) ) );
	}

	/**
	 *
	 *
	 * @desc Display alerts for Settings page - validation errors and updated message
	 */
	function display_alerts() {
		//check if there are some errors
		if ( isset ( $_GET['publishthis_validation_message'] ) ) {
			$messages = array ( '1' => __ ( 'All fields are required', 'publishthis' ),
				'2' => __ ( 'There is already a Publishing Action using that feed template / template section.', 'publishthis' ) );
			echo '<div class="error"><p><strong>' . $messages [$_GET['publishthis_validation_message']] . '</strong></p></div>';
		}
		//check if settings were updated
		if ( isset ( $_GET['page'], $_GET['settings-updated'] ) && $_GET['page'] == 'publishthis_settings' && $_GET['settings-updated'] == 'true' ) {
			echo '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
		}
	}

	/**
	 *
	 *
	 * @desc Enqueue assets and get extra data. Creates unique token (nonce) for secure ajax call
	 */
	function enqueue_assets() {
		global $publishthis, $current_screen;

		// Load assets for all screens
		if ( in_array( $current_screen->id, $this->_screens ) ) {
			wp_enqueue_style ( 'publishthis-admin', $publishthis->plugin_url () . '/assets/css/admin.css', array (), $publishthis->version );
			wp_enqueue_script ( 'publishthis-admin', $publishthis->plugin_url () . '/assets/js/admin.js', array ( 'jquery' ), $publishthis->version );

			// Publishing Actions edit page needs some extra info
			if ( $current_screen->id == $publishthis->post_type ) {
				$feed_templates = $publishthis->api->get_feed_templates ();

				$fields = $sections = array ();
				if ( is_array( $feed_templates ) ) {
					foreach ( $feed_templates as $template ) {
						$sections[$template->templateId] = $template->templateSections;
						$fields[$template->templateId] = $template->templateFields;
					}
				}

				wp_localize_script( 'publishthis-admin', 'Publishthis', array ( 'templateFields' => $fields, 'templateSections' => $sections ) );
			}
		}

		if ( $current_screen->id == 'widgets' ) {
			wp_enqueue_script ( 'publishthis-admin-widgets', $publishthis->plugin_url () . '/assets/js/admin-widgets.js', array ( 'jquery' ), $publishthis->version );

			//generate ajax nonce
			wp_localize_script(
				'publishthis-admin-widgets',
				'publishthis_widgets_ajax',
				array(
					'nonce' => wp_create_nonce( 'publishthis_admin_widgets_nonce' )
				)
			);
		}
	}

	/**
	 *
	 *
	 * @desc Add Publishthis Plugin menus: set display name, actions, icons.
	 * @return Pass data to global variable
	 */
	function setup_menu() {
		global $publishthis, $submenu;

		$parent_slug = "edit.php?post_type={$publishthis->post_type}";

		//Add top level menu page
		add_menu_page( __ ( 'PublishThis', 'publishthis' ), __ ( 'PublishThis', 'publishthis' ), 'manage_options', $parent_slug, null, $publishthis->plugin_url () . '/assets/img/ico-16x16.png' );

		//Specify submenus
		$this->_screens[] = add_submenu_page( $parent_slug, __ ( 'Settings', 'publishthis' ), __ ( 'Settings', 'publishthis' ), 'manage_options', $this->_settings_menu_slug, array ( $this, 'options_page' ) );

		//Set custom name for the first submenu item
		if ( isset ( $submenu[$parent_slug][0][0] ) ) {
			$submenu[$parent_slug][0][0] = 'Publishing Actions';
		}
	}

	/**
	 *
	 *
	 * @desc Update cron settings with new updated values.
	 */
	function update_cron() {
		//check if settings were updated
		if ( isset ( $_GET['page'], $_GET['settings-updated'] ) && $_GET['page'] == $this->_settings_menu_slug && $GET['settings-updated'] = 'true' ) {
			global $publishthis;
			$publishthis->cron->update();
		}
	}

	/**
	 *
	 *
	 * @desc Bind options fields for Publishing Actions edit page. Specify template with options fields
	 */
	function add_meta_box() {
		global $publishthis;
		add_meta_box( 'publishthis-options-box', 'Options', array( $this, 'display_meta_box' ), $publishthis->post_type, 'normal', 'default', null );
	}

	/**
	 *
	 *
	 * @param unknown $post Publishing Action object
	 * @desc Render options fields
	 */
	function display_meta_box( $post ) {
		global $publishthis;
		include 'meta-box-options.php';
	}

	/**
	 *
	 *
	 * @param unknown $post_id Publishing Action ID
	 * @param unknown $post    Publishing Action data object
	 * @desc Validate and save Publishing Action
	 */
	function save_publish_action( $post_id, $post ) {
		global $publishthis;

		//check if action can be saved
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_type != $publishthis->post_type || ! current_user_can( 'manage_options', $post_id ) || empty ( $_POST['publishthis_publish_action'] ) ) {
			return;
		}

		//validate form. Title should be mandatory
		$errors = array();

		if ( empty ( $_POST['post_title'] ) ) {
			$errors['post_title'] = 'Title is required.';
		}

		if ( $errors ) {
			return;
		}

		//allowed fields
		$field_keys = array ( 'poll_interval', 'feed_template', 'template_section', 'content_type', 'content_type_format', 'content_status', 'featured_image', 'category', 'synchronize', 'max_image_width', 'ok_resize_preview', 'publish_author', 'read_more', 'image_alignment', 'annotation_placement' );

		//update allowed fields with value
		foreach ( $field_keys as $field_key ) {
			$meta_key = "_publishthis_{$field_key}";
			update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST['publishthis_publish_action'][$field_key] ) );
		}

		//update cron with new values
		$publishthis->cron->update ();
	}

	/**
	 *
	 *
	 * @param unknown $post_id Publishing Action ID
	 * @desc Validate Publishing Action before saving
	 */
	function validate_publish_action( $post_id ) {
		global $publishthis;

		//do actions only for edit
		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'delete', 'trash' ) ) ) {
			return;
		}

		$post = get_post( $post_id );

		//check for correspondent post type
		if ( ! $post || $post->post_type != $publishthis->post_type ) {
			return;
		}

		//validate title
		if ( empty ( $_POST ['post_title'] ) ) {
			$this->_validation_redirect ( $post_id, 1 );
		}

		//validate for unique template/section values pair
		$publish_actions = get_posts( array(
				'numberposts'  => 100,
				'post_type'    => $publishthis->post_type,
				'post__not_in' => array( $post_id )
			) );

		foreach ( $publish_actions as $action ) {
			$_feed_template = get_post_meta( $action->ID, '_publishthis_feed_template', true );
			$_template_section = get_post_meta( $action->ID, '_publishthis_template_section', true );

			if ( $_feed_template == $_POST['publishthis_publish_action']['feed_template'] && $_template_section == $_POST['publishthis_publish_action']['template_section'] ) {
				$this->_validation_redirect ( $post_id, 2 );
			}
		}
	}

	/**
	 *
	 *
	 * @param unknown $post_id    Publishing Action ID
	 * @param unknown $message_id Message index from display_alerts()
	 * @desc Redirect if validation doesn't passed
	 */
	private function _validation_redirect( $post_id, $message_id ) {
		//get location
		$location = add_query_arg( 'publishthis_validation_message', $message_id, get_edit_post_link( $post_id, 'url' ) );
		wp_safe_redirect( $location );
		exit();
	}

	/**
	 *
	 *
	 * @desc Remove quick edit links for Publishing Actions list
	 * @param unknown $actions Array of possible actions
	 * @param unknown $post    Current list item
	 * @return Modified actions array
	 */
	function remove_quick_edit_link( $actions, $post ) {
		global $publishthis;
		if ( $post->post_type != $publishthis->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );
		return $actions;
	}

	/**
	 *
	 *
	 * @desc Prepare Options for saving - remove data value as it will be set automaticallly
	 * @param unknown $columns Options array
	 * @return Columns array
	 */
	function edit_columns( $columns ) {
		unset( $columns['date'] );
		return $columns;
	}

	/**
	 *
	 *
	 * @desc Render API token field with value
	 */
	function display_api_token_field() {
		global $publishthis;

		$value = $publishthis->get_option( 'api_token' );
?>
			<input type="text" id="publishthis_api_token"
				   name="<?php echo $publishthis->option_name ?>[api_token]"
				   value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php
	}

	/**
	 *
	 *
	 * @desc Render API version select with selected value
	 */
	function display_api_version_field() {
		global $publishthis;

		$selected = $publishthis->get_option( 'api_version' );
?>
			<select name="<?php echo $publishthis->option_name ?>[api_version]" id="publishthis_api_version">
				<option <?php selected( $selected, '3.0' ); ?>>3.0</option>
			</select>
		<?php
	}

	/**
	 *
	 *
	 * @desc Render debug select with selected value
	 */
	function display_debug_field() {
		global $publishthis;

		$selected = $publishthis->get_option( 'debug' );
?>
			<select name="<?php echo $publishthis->option_name ?>[debug]" id="publishthis_debug">
				<option <?php selected( $selected, '0' ); ?> value="0">None</option>
				<option <?php selected( $selected, '1' ); ?> value="1">Errors Only</option>
				<option <?php selected( $selected, '2' ); ?> value="2">Debug</option>
			</select>
		<?php

	}

	/**
	 *
	 *
	 * @desc Render Pause polling checkbox. If checked - stop polling the API for new content
	 */
	function display_pause_polling_field() {
		global $publishthis;

		$checked = ( $publishthis->get_option( 'pause_polling' ) ) ? '1' : '0';
?>
			<input type="hidden" name="<?php echo $publishthis->option_name ?>[pause_polling]" value="0" />
			<label>
				<input type="checkbox"
					name="<?php echo $publishthis->option_name ?>[pause_polling]"
					id="publishthis_pause_polling" value="1"
				<?php checked( $checked, '1' ); ?> /> Stop polling the API for new
				content
			</label>
		<?php
	}

	/**
	 *
	 *
	 * @desc Render Enable PublishThis CSS styles checkbox
	 */
	function display_styling_field() {
		global $publishthis;

		$checked = ( $publishthis->get_option( 'styling' ) ) ? '1' : '0';
?>
			<input type="hidden" name="<?php echo $publishthis->option_name ?>[styling]" value="0" />
			<label>
				<input type="checkbox"
					name="<?php echo $publishthis->option_name ?>[styling]"
					id="publishthis_styling" value="1" <?php checked( $checked, '1' ); ?> />
				Enable PublishThis CSS styles
			</label>
		<?php
	}

	/**
	 *
	 *
	 * @desc Render option to show/hide Publishthis logo
	 */
	function display_curatedby_field() {
		global $publishthis;

		$checked = ( $publishthis->get_option( 'curatedby' ) ) ? '1' : '0';
?>
			<input type="hidden" name="<?php echo $publishthis->option_name ?>[curatedby]" value="0" />
			<label>
				<input type="checkbox"
					name="<?php echo $publishthis->option_name ?>[curatedby]"
					id="publishthis_curatedby" value="1" <?php checked( $checked, '1' ); ?> />
				Display the PublishThis Curated By Logo
			</label>
		<?php
	}

	/**
	 *
	 *
	 * @desc Bind options names, render functions and values
	 */
	function init_options() {
		global $publishthis;

		register_setting( $publishthis->option_name, $this->_option_group, array ( $this, 'validate_options' ) );

		add_settings_section( $this->_settings_section, '', '__return_false', $this->_settings_section );

		add_settings_field( 'publishthis-pause-polling', 'Pause Polling', array ( $this, 'display_pause_polling_field' ), $this->_settings_section, $this->_settings_section );
		add_settings_field( 'publishthis-api-token', 'API Token', array ( $this, 'display_api_token_field' ), $this->_settings_section, $this->_settings_section );
		add_settings_field( 'publishthis-api-version', 'API Version', array ( $this, 'display_api_version_field' ), $this->_settings_section, $this->_settings_section );
		add_settings_field( 'publishthis-styling', 'Styling', array ( $this, 'display_styling_field' ), $this->_settings_section, $this->_settings_section );
		add_settings_field( 'publishthis-debug', 'Logging Level', array ( $this, 'display_debug_field' ), $this->_settings_section, $this->_settings_section );
		add_settings_field( 'publishthis-curatedby', 'Display the Curated By Logo', array ( $this, 'display_curatedby_field' ), $this->_settings_section, $this->_settings_section );
	}

	/**
	 *
	 *
	 * @desc Render Publishthis Settings page
	 */
	function options_page() {
?>
		<div class="wrap">

			<div class="icon32" id="icon-publishthis">
				<br>
			</div>
			<h2 style="margin: 4px 0 15px;"><?php _e( 'Settings', 'publishthis' ); ?></h2>

			<form action="options.php" method="post">
				<?php settings_fields( $this->_option_group ); ?>
				<?php do_settings_sections( $this->_settings_section ); ?>
				<?php submit_button( __( 'Save Changes', 'publishthis' ) ); ?>
			</form>

		</div>
		<?php
	}

	/**
	 *
	 *
	 * @param unknown $fields All settings data
	 * @return $options Validated settings data object
	 * @desc Validate Settings before saving
	 */
	function validate_options( $fields ) {
		global $publishthis;

		$options = $publishthis->get_options();

		//validate API token
		$api_token = sanitize_text_field( $fields['api_token'] );
		if ( isset( $api_token ) && is_string( $api_token ) ) {
			$options['api_token'] = $api_token;
		}

		//validate API version
		if ( isset( $fields['api_version'] ) && in_array( $fields['api_version'], array( '3.0' ) ) ) {
			$options['api_version'] = $fields['api_version'];
		}

		//validate Debug
		if ( isset( $fields['debug'] ) && in_array( $fields['debug'], array( '0', '1', '2' ) ) ) {
			$options['debug'] = $fields['debug'];
		}

		//validate Pause polling
		if ( isset( $fields['pause_polling'] ) && in_array( $fields['pause_polling'], array( '0', '1' ) ) ) {
			$options['pause_polling'] = $fields['pause_polling'];
		}

		//validate Styling
		if ( isset( $fields['styling'] ) && in_array( $fields['styling'], array( '0', '1' ) ) ) {
			$options['styling'] = $fields['styling'];
		}

		//validate curated by logo
		if ( isset( $fields['curatedby'] ) && in_array( $fields['curatedby'], array( '0', '1' ) ) ) {
			$options['curatedby'] = $fields['curatedby'];
		}

		return $options;
	}
}
