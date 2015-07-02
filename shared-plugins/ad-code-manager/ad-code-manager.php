<?php
/*
Plugin Name: Ad Code Manager
Plugin URI: http://automattic.com
Description: Easy ad code management
Author: Rinat Khaziev, Jeremy Felt, Daniel Bachhuber, Automattic, doejo
Version: 0.5-alpha
Author URI: http://automattic.com

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/
define( 'AD_CODE_MANAGER_VERSION', '0.5-alpha' );
define( 'AD_CODE_MANAGER_ROOT' , dirname( __FILE__ ) );
define( 'AD_CODE_MANAGER_FILE_PATH' , AD_CODE_MANAGER_ROOT . '/' . basename( __FILE__ ) );
define( 'AD_CODE_MANAGER_URL' , plugins_url( '/', __FILE__ ) );

// Bootsrap
require_once AD_CODE_MANAGER_ROOT .'/common/lib/acm-provider.php';
require_once AD_CODE_MANAGER_ROOT .'/common/lib/acm-wp-list-table.php';
require_once AD_CODE_MANAGER_ROOT .'/common/lib/acm-widget.php';
require_once AD_CODE_MANAGER_ROOT .'/common/lib/markdown.php';

class Ad_Code_Manager {

	public $ad_codes = array();
	public $whitelisted_conditionals = array();
	public $title = 'Ad Code Manager';
	public $post_type = 'acm-code';
	public $plugin_slug = 'ad-code-manager';
	public $manage_ads_cap = 'manage_options';
	public $post_type_labels;
	public $logical_operator;
	public $ad_tag_ids;
	public $providers;
	public $current_provider_slug;
	public $current_provider;
	public $wp_list_table;

	/**
	 * Instantiate the plugin
	 *
	 * @since 0.1
	 */
	function __construct() {
		add_action( 'init', array( $this, 'action_load_providers' ) );
		add_action( 'init', array( $this, 'action_init' ) );

		// Incorporate the link to our admin menu
		add_action( 'admin_menu' , array( $this, 'action_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_and_styles' ) );
		add_action( 'wp_ajax_acm_admin_action', array( $this, 'handle_admin_action' ) );

		add_action( 'current_screen', array( $this, 'contextual_help' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_shortcode( 'acm-tag' , array( $this, 'shortcode' ) );
		// Workaround for PHP 5.4 warning: Creating default object from empty value in
		$this->providers = new stdClass();
	}

	/**
	 * Load all available ad providers
	 * and set selected as ACM_Provider $current_provider
	 * which holds all necessary configuration properties
	 */
	function action_load_providers() {
		$module_dirs = array_diff( scandir( AD_CODE_MANAGER_ROOT . '/providers/' ), array( '..', '.' ) );
		foreach ( $module_dirs as $module_dir ) {
			$module_dir = str_replace( '.php', '', $module_dir );
			if ( file_exists( AD_CODE_MANAGER_ROOT . "/providers/$module_dir.php" ) ) {
				include_once AD_CODE_MANAGER_ROOT . "/providers/$module_dir.php";
			}

			$tmp = explode( '-', $module_dir );
			$class_name = '';
			$slug_name = '';
			$table_class_name = '';
			foreach ( $tmp as $word ) {
				$class_name .= ucfirst( $word ) . '_';
				$slug_name .= $word . '_';
			}
			$table_class_name = $class_name . 'ACM_WP_List_Table';
			$class_name .= 'ACM_Provider';
			$slug_name = rtrim( $slug_name, '_' );

			// Store class names, but don't instantiate
			// We don't need them all at once
			if ( class_exists( $class_name ) ) {
				$this->providers->$slug_name = array(
					'provider' => $class_name,
					'table' => $table_class_name,
				);
			}

		}

		/**
		 * Configuration filter: acm_register_provider_slug
		 *
		 * We've already gathered a list of default providers by scanning the ACM plugin
		 * directory for classes that we can use. To add a provider already included via
		 * a different directory, the following filter is provided.
		 */
		$this->providers = apply_filters( 'acm_register_provider_slug', $this->providers );

		/**
		 * Configuration filter: acm_provider_slug
		 *
		 * By default we use doubleclick-for-publishers provider
		 * To switch to a different ad provider use this filter
		 */

		$this->current_provider_slug = apply_filters( 'acm_provider_slug', $this->get_option( 'provider' ) );

		// Instantiate one that we need
		if ( isset( $this->providers->{$this->current_provider_slug} ) )
			$this->current_provider = new $this->providers->{$this->current_provider_slug}['provider'];

		// Nothing to do without a provider
		if ( !is_object( $this->current_provider ) )
			return ;
		/**
		 * Configuration filter: acm_whitelisted_script_urls
		 * A security filter to whitelist which ad code script URLs can be added in the admin
		 */
		$this->current_provider->whitelisted_script_urls = apply_filters( 'acm_whitelisted_script_urls', $this->current_provider->whitelisted_script_urls );

	}

	/**
	 * Code to run on WordPress' 'init' hook
	 *
	 * @since 0.1
	 */
	function action_init() {

		load_plugin_textdomain( 'ad-code-manager', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );

		$this->post_type_labels = array(
			'name' => __( 'Ad Codes', 'ad-code-manager' ),
			'singular_name' => __( 'Ad Code', 'ad-code-manager' ),
		);

		// Allow other conditionals to be used
		$this->whitelisted_conditionals = array(
			'is_home',
			'is_front_page',
			'is_category',
			'has_category',
			'is_single',
			'is_page',
			'is_tag',
			'has_tag',
		);
		/**
		 * Configuration filter: acm_whitelisted_conditionals
		 * Extend the list of usable conditional functions with your own awesome ones.
		 */
		$this->whitelisted_conditionals = apply_filters( 'acm_whitelisted_conditionals', $this->whitelisted_conditionals );
		// Allow users to filter default logical operator
		$this->logical_operator = apply_filters( 'acm_logical_operator', 'OR' );

		// Allow the ad management cap to be filtered if need be
		$this->manage_ads_cap = apply_filters( 'acm_manage_ads_cap', $this->manage_ads_cap );

		// Load default ad tags for provider
		$this->ad_tag_ids = $this->current_provider->ad_tag_ids;
		/**
		 * Configuration filter: acm_ad_tag_ids
		 * Extend set of default tag ids. Ad tag ids are used as a parameter
		 * for your template tag (e.g. do_action( 'acm_tag', 'my_top_leaderboard' ))
		 */
		$this->ad_tag_ids = apply_filters( 'acm_ad_tag_ids', $this->ad_tag_ids );

		/**
		 * Configuration filter: acm_ad_code_args
		 * Allow the ad code arguments to be filtered
		 * Useful if we need to dynamically change these arguments based on the above
		 */
		$this->current_provider->ad_code_args = apply_filters( 'acm_ad_code_args', $this->current_provider->ad_code_args );

		$this->register_acm_post_type();

		// Ad tags are only run on the frontend
		if ( !is_admin() ) {
			add_action( 'acm_tag', array( $this, 'action_acm_tag' ) );
			add_filter( 'acm_output_tokens', array( $this, 'filter_output_tokens' ), 5, 3 );
		}

		// Load all of our registered ad codes
		$this->register_ad_codes( $this->get_ad_codes() );
	}

	/**
	 * Register our custom post type to store ad codes
	 *
	 * @since 0.1
	 */
	function register_acm_post_type() {
		register_post_type( $this->post_type, array( 'labels' => $this->post_type_labels, 'public' => false, 'rewrite' => false ) );
	}

	/**
	 * Get all ACM options
	 *
	 * @since 0.4
	 */
	function get_options() {

		$default_provider = 'doubleclick_for_publishers';
		// Make sure our default provider exists. Otherwise, the sky will fall on our head
		if ( ! isset( $this->providers->$default_provider ) ) {
			foreach ( $this->providers as $slug => $provider ) {
				$default_provider = $slug;
				break;
			}
		}

		$defaults = array(
			'provider'          => $default_provider,
		);
		$options = get_option( 'acm_options', array() );
		return array_merge( $defaults, $options );
	}

	/**
	 * Get an ACM option
	 *
	 * @since 0.4
	 */
	function get_option( $key ) {

		$options = $this->get_options();

		if ( isset( $options[$key] ) )
			return $options[$key];
		else
			return false;
	}

	/**
	 * Update an ACM option
	 *
	 * @since 0.4
	 */
	function update_options( $new_options ) {

		$options = $this->get_options();
		$options = array_merge( $options, $new_options );
		update_option( 'acm_options', $options );
	}

	/**
	 * Handle any Add, Edit, or Delete actions from the admin interface
	 * Hooks into admin ajax because it's the proper context for these sort of actions
	 *
	 * @since 0.2
	 */
	function handle_admin_action() {

		if ( !wp_verify_nonce( $_REQUEST['nonce'], 'acm-admin-action' ) )
			wp_die( __( 'Doing something fishy, eh?', 'ad-code-manager' ) );

		if ( !current_user_can( $this->manage_ads_cap ) )
			wp_die( __( 'You do not have the necessary permissions to perform this action', 'ad-code-manager' ) );

		// Depending on the method we're performing, sanitize the requisite data and do it
		switch ( $_REQUEST['method'] ) {
		case 'add':
		case 'edit':
			$id = ( isset( $_REQUEST['id'] ) ) ? (int)$_REQUEST['id'] : 0;
			$priority = ( isset( $_REQUEST['priority'] ) ) ? (int)$_REQUEST['priority'] : 10;
			$operator = ( isset( $_REQUEST['operator'] ) && in_array( $_REQUEST['operator'], array( 'AND', 'OR' ) ) ) ? $_REQUEST['operator'] : $this->logical_operator;
			$ad_code_vals = array(
				'priority' => $priority,
				'operator' => $operator,
			);
			foreach ( $this->current_provider->ad_code_args as $arg ) {
				$ad_code_vals[$arg['key']] = sanitize_text_field( $_REQUEST['acm-column'][$arg['key']] );
			}
			if ( $_REQUEST['method'] == 'add' )
				$id = $this->create_ad_code( $ad_code_vals );
			else
				$id = $this->edit_ad_code( $id, $ad_code_vals );
			if ( is_wp_error( $id ) ) {
				// We can die with an error if this is an edit/ajax request
				if ( isset( $id->errors['edit-error'][0] ) )
					die( '<div class="error">' . $id->errors['edit-error'][0] . '</div>' );
				else
					$message = 'error-adding-editing-ad-code';
				break;
			}
			$new_conditionals = array();
			$unsafe_conditionals = ( isset( $_REQUEST['acm-conditionals'] ) ) ? $_REQUEST['acm-conditionals'] : array();
			foreach ( $unsafe_conditionals as $index => $unsafe_conditional ) {
				$index = (int)$index;
				$arguments = ( isset( $_REQUEST['acm-arguments'][$index] ) ) ? sanitize_text_field( $_REQUEST['acm-arguments'][$index] ) : '';
				$conditional = array(
					'function' => sanitize_key( $unsafe_conditional ),
					'arguments' => $arguments,
				);
				if ( !empty( $conditional['function'] ) ) {
					$new_conditionals[] = $conditional;
				}
			}
			if ( $_REQUEST['method'] == 'add' ) {
				foreach ( $new_conditionals as $new_conditional ) {
					$this->create_conditional( $id, $new_conditional );
				}
				$message = 'ad-code-added';
			} else {
				$this->edit_conditionals( $id, $new_conditionals );
				$message = 'ad-code-updated';
			}
			$this->flush_cache();
			break;
		case 'delete':
			$id = (int)$_REQUEST['id'];
			$this->delete_ad_code( $id );
			$this->flush_cache();
			$message = 'ad-code-deleted';
			break;
		case 'update_options':
			$options = $this->get_options();
			foreach ( $options as $key => $value ) {
				if ( isset( $_REQUEST[$key] ) )
					$options[$key] = sanitize_text_field( $_REQUEST[$key] );
			}
			$this->update_options( $options );
			$message = 'options-saved';
			break;
		}

		if ( isset( $_REQUEST['doing_ajax'] ) && $_REQUEST['doing_ajax'] ) {
			switch ( $_REQUEST['method'] ) {
			case 'edit':
				set_current_screen( 'ad-code-manager' );
				$this->wp_list_table = new $this->providers->{$this->current_provider_slug}['table'];
				$this->wp_list_table->prepare_items();
				$new_ad_code = $this->get_ad_code( $id );
				echo $this->wp_list_table->single_row( $new_ad_code );
				break;
			}
		} else {
			// @todo support ajax and non-ajax requests
			$redirect_url = add_query_arg( 'message', $message, remove_query_arg( 'message', wp_get_referer() ) );
			wp_safe_redirect( $redirect_url );
		}
		exit;
	}

	/**
	 * Get the ad codes stored in our custom post type
	 *
	 */
	function get_ad_codes( $query_args = array() ) {
		$allowed_query_params = apply_filters( 'acm_allowed_get_posts_args', array( 'offset' ) );


		/**
		 * Configuration filter: acm_ad_code_count
		 *
		 * By default we limit query to 50 ad codes
		 * Use this filter to change limit
		 */
		$args = array(
			'post_type' => $this->post_type,
			'numberposts' => apply_filters( 'acm_ad_code_count', 50 ),
		);

		foreach ( (array) $query_args as $query_key => $query_value ) {
			if ( ! in_array( $query_key, $allowed_query_params ) ) {
				unset( $query_args[$query_key] );
			} else {
				$args[$query_key] = $query_value;
			}
		}

		$ad_codes_formatted = wp_cache_get( 'ad_codes' , 'acm' );
		if ( false === $ad_codes_formatted ) {
			// Store an empty array when no ad codes exist so this block doesn't run on each page load
			$ad_codes_formatted = array();
			$ad_codes = get_posts( $args );
			foreach ( $ad_codes as $ad_code_cpt ) {
				$provider_url_vars = array();

				foreach ( $this->current_provider->ad_code_args as  $arg ) {
					$provider_url_vars[$arg['key']] = get_post_meta( $ad_code_cpt->ID, $arg['key'], true );
				}

				$priority = get_post_meta( $ad_code_cpt->ID, 'priority', true );
				$priority = ( !empty( $priority ) ) ? intval( $priority ) : 10;

				$operator = get_post_meta( $ad_code_cpt->ID, 'operator', true );
				$operator = ( !empty( $operator ) ) ? esc_html( $operator ) : $this->logical_operator;

				$ad_codes_formatted[] = array(
					'conditionals' => $this->get_conditionals( $ad_code_cpt->ID ),
					'url_vars' => $provider_url_vars,
					'priority' => $priority,
					'operator' => $operator,
					'post_id' => $ad_code_cpt->ID
				);
			}
			wp_cache_add( 'ad_codes', $ad_codes_formatted, 'acm',  3600 );
		}
		return $ad_codes_formatted;
	}

	/**
	 * Get a single ad code
	 *
	 * @param int     $post_id Post ID for the ad code that we want
	 * @return array $ad_code Ad code representation of the data
	 */
	function get_ad_code( $post_id ) {

		$post = get_post( $post_id );
		if ( !$post )
			return false;

		$provider_url_vars = array();
		foreach ( $this->current_provider->ad_code_args as $arg ) {
			$provider_url_vars[$arg['key']] = get_post_meta( $post->ID, $arg['key'], true );
		}

		$priority = get_post_meta( $post_id, 'priority', true );
		$priority = ( !empty( $priority ) ) ? intval( $priority ) : 10;

		$operator = get_post_meta( $post_id, 'operator', true );
		$operator = ( !empty( $operator ) ) ? esc_html( $operator ) : $this->logical_operator;

		$ad_code_formatted = array(
			'conditionals' => $this->get_conditionals( $post->ID ),
			'url_vars' => $provider_url_vars,
			'priority' => $priority,
			'operator' => $operator,
			'post_id' => $post->ID
		);
		return $ad_code_formatted;

	}

	/**
	 * Flush cache
	 */
	function flush_cache() {
		wp_cache_delete( 'ad_codes', 'acm' );
	}

	/**
	 * Get the conditional values for an ad code
	 */
	function get_conditionals( $ad_code_id ) {
		$conditionals = get_post_meta( $ad_code_id, 'conditionals', true );
		if ( empty( $conditionals ) )
			$conditionals = array();
		return $conditionals;
	}


	/**
	 * Create a new ad code in the database
	 *
	 * @uses register_ad_code()
	 *
	 * @param array   $ad_code
	 *
	 * @return int|false post_id or false
	 */
	function create_ad_code( $ad_code = array() ) {
		$titles = array();
		foreach ( $this->current_provider->ad_code_args as $arg ) {
			// We shouldn't create an ad code,
			// If any of required fields is not set
			if ( ! isset( $ad_code[$arg['key']] ) && $arg['required'] === true  ) {
				return new WP_Error();
			}
			$titles[] = $ad_code[$arg['key']];
		}
		$acm_post = array(
			'post_title' => implode( '-', $titles ),
			'post_status' => 'publish',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'post_type' => $this->post_type,
		);

		if ( ! is_wp_error( $acm_inserted_post_id = wp_insert_post( $acm_post, true ) ) ) {
			foreach ( $this->current_provider->ad_code_args as $arg ) {
				update_post_meta( $acm_inserted_post_id, $arg['key'], $ad_code[$arg['key']] );
			}
			update_post_meta( $acm_inserted_post_id, 'priority', $ad_code['priority'] );
			update_post_meta( $acm_inserted_post_id, 'operator', $ad_code['operator'] );
			$this->flush_cache();
			return $acm_inserted_post_id;
		}
		return false;
	}

	/**
	 * Update an existing ad code
	 */
	function edit_ad_code( $ad_code_id, $ad_code = array() ) {
		foreach ( $this->current_provider->ad_code_args as $arg ) {
			// If a required argument is not set, we return an error message with the missing parameter
			if ( ! isset( $ad_code[$arg['key']] ) && $arg['required'] === true  ) {
				return new WP_Error( 'edit-error', 'Error updating ad code, a parameter for ' . esc_html( $arg['key'] ) . ' is required.' );
			}
		}
		if ( 0 !== $ad_code_id ) {
			foreach ( $this->current_provider->ad_code_args as $arg ) {
				update_post_meta( $ad_code_id, $arg['key'], $ad_code[$arg['key']] );
			}
			update_post_meta( $ad_code_id, 'priority', $ad_code['priority'] );
			update_post_meta( $ad_code_id, 'operator', $ad_code['operator'] );
		}
		$this->flush_cache();
		return $ad_code_id;
	}

	/**
	 * Delete an existing ad code
	 */
	function delete_ad_code( $ad_code_id ) {
		if ( 0 !== $ad_code_id ) {
			wp_delete_post( $ad_code_id , true ); //force delete post
			$this->flush_cache();
			return true;
		}
		return;
	}
	/**
	 * Create conditional
	 *
	 * @param int     $ad_code_id  id of our CPT post
	 * @param array   $conditional to add
	 *
	 * @return bool
	 */
	function create_conditional( $ad_code_id, $conditional ) {
		if ( 0 !== $ad_code_id && !empty( $conditional ) ) {
			$existing_conditionals =  get_post_meta( $ad_code_id, 'conditionals', true );
			if ( ! is_array( $existing_conditionals ) ) {
				$existing_conditionals = array();
			}
			$existing_conditionals[] = array(
				'function' => $conditional['function'],
				'arguments' => explode( ';', $conditional['arguments'] ),
			);
			return update_post_meta( $ad_code_id, 'conditionals', $existing_conditionals );
		}
		return false;
	}

	/**
	 * Update all conditionals for ad code
	 *
	 * @param int     $ad_code_id id of our CPT post
	 * @param array   of $conditionals
	 *
	 * @since v0.2
	 * @return bool
	 */
	function edit_conditionals( $ad_code_id, $conditionals = array() ) {
		if ( 0 !== $ad_code_id && !empty( $conditionals ) ) {
			$new_conditionals = array();
			foreach ( $conditionals as $conditional ) {
				if ( '' == $conditional['function'] )
					continue;
				$new_conditionals[] = array(
					'function' => $conditional['function'],
					'arguments' => (array) $conditional['arguments'],
				);
			}
			return update_post_meta( $ad_code_id, 'conditionals', $new_conditionals );
		} elseif ( 0 !== $ad_code_id ) {
			return update_post_meta( $ad_code_id, 'conditionals', array() );
		}
	}

	/**
	 * Hook in our submenu page to the navigation
	 */
	function action_admin_menu() {
		$hook = add_submenu_page( 'tools.php', $this->title, $this->title, $this->manage_ads_cap, $this->plugin_slug, array( $this, 'admin_view_controller' ) );
		add_action( 'load-' . $hook, array( $this, 'action_load_ad_code_manager' ) );
	}

	/**
	 * Instantiate the List Table and handle our bulk actions on the load of the page
	 *
	 * @since 0.2.2
	 */
	function action_load_ad_code_manager() {

		// Instantiate this list table
		$this->wp_list_table = new $this->providers->{$this->current_provider_slug}['table'];
		// Handle any bulk action requests
		switch ( $this->wp_list_table->current_action() ) {
		case 'delete':
			check_admin_referer( 'acm-bulk-action', 'bulk-action-nonce' );
			$ad_code_ids = array_map( 'intval', $_REQUEST['ad-codes'] );
			foreach ( $ad_code_ids as $ad_code_id ) {
				$this->delete_ad_code( $ad_code_id );
			}
			$redirect_url = add_query_arg( 'message', 'ad-codes-deleted', remove_query_arg( 'message', wp_get_referer() ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Print the admin interface for managing the ad codes
	 *
	 */
	function admin_view_controller() {
		require_once AD_CODE_MANAGER_ROOT . '/common/views/ad-code-manager.tpl.php';
	}

	function parse_readme_into_contextual_help() {
		ob_start();
		include_once AD_CODE_MANAGER_ROOT . '/readme.txt';
		$readme = ob_get_clean();
		$sections = preg_split( "/==(.*)==/", $readme );
		// Something's wrong with readme, fail silently
		if ( 5 > count( $sections ) )
			return;

		$useful = array(  $sections[2], $sections[4], $sections[7] );
		foreach ( $useful as $i => $tab ) {
			// Because WP.ORG Markdown has a different flavor
			$useful[$i] = Markdown( str_replace( array( '= ', ' =' ), '**', $tab ) );
		}
		return $useful;
	}

	function contextual_help() {
		global $pagenow;
		if ( 'tools.php' != $pagenow || !isset( $_GET['page'] ) || $_GET['page'] != $this->plugin_slug )
			return;
		list( $description, $configuration, $filters ) = $this->parse_readme_into_contextual_help();
		ob_start();
?>
			<div id="conditionals-help">
		<p><strong>Note:</strong> this is not full list of conditional tags, you can always check out <a href="http://codex.wordpress.org/Conditional_Tags" class="external text">Codex page</a>.</p>

		<dl><dt> <tt><a href="http://codex.wordpress.org/Function_Reference/is_home" class="external text" title="http://codex.wordpress.org/Function_Reference/is_home">is_home()</a></tt>&nbsp;</dt><dd> When the main blog page is being displayed. This is the page which shows the time based blog content of your site, so if you've set a static Page for the Front Page (see below), then this will only be true on the Page which you set as the "Posts page" in <a href="http://codex.wordpress.org/Administration_Panels" title="Administration Panels" class="mw-redirect">Administration</a> &gt; <a href="http://codex.wordpress.org/Administration_Panels#Reading" title="Administration Panels" class="mw-redirect">Settings</a> &gt; <a href="http://codex.wordpress.org/Settings_Reading_SubPanel" title="Settings Reading SubPanel" class="mw-redirect">Reading</a>.
</dd></dl>
		<dl><dt> <tt><a href="http://codex.wordpress.org/Function_Reference/is_front_page" class="external text" title="http://codex.wordpress.org/Function_Reference/is_front_page">is_front_page()</a></tt>&nbsp;</dt><dd> When the front of the site is displayed, whether it is posts or a <a href="http://codex.wordpress.org/Pages" title="Pages">Page</a>.  Returns true when the main blog page is being displayed and the '<a href="http://codex.wordpress.org/Administration_Panels#Reading" title="Administration Panels" class="mw-redirect">Settings</a> &gt; <a href="http://codex.wordpress.org/Settings_Reading_SubPanel" title="Settings Reading SubPanel" class="mw-redirect">Reading</a> -&gt;Front page displays' is set to "Your latest posts", <b>or</b> when '<a href="http://codex.wordpress.org/Administration_Panels#Reading" title="Administration Panels" class="mw-redirect">Settings</a> &gt; <a href="http://codex.wordpress.org/Settings_Reading_SubPanel" title="Settings Reading SubPanel" class="mw-redirect">Reading</a> -&gt;Front page displays' is set to "A static page" and the "Front Page" value is the current <a href="/Pages" title="Pages">Page</a> being displayed.
</dd></dl>
<dl><dt> <tt><a href="http://codex.wordpress.org/Function_Reference/is_category" class="external text" title="http://codex.wordpress.org/Function_Reference/is_category">is_category()</a></tt>&nbsp;</dt><dd> When any Category archive page is being displayed.
</dd><dt> <tt>is_category( '9' )</tt>&nbsp;</dt><dd> When the archive page for Category 9 is being displayed.
</dd><dt> <tt>is_category( 'Stinky Cheeses' )</tt>&nbsp;</dt><dd> When the archive page for the Category with Name "Stinky Cheeses" is being displayed.
</dd><dt> <tt>is_category( 'blue-cheese' )</tt>&nbsp;</dt><dd> When the archive page for the Category with Category Slug "blue-cheese" is being displayed.
</dd><dt> <tt>is_category( array( 9, 'blue-cheese', 'Stinky Cheeses' ) )</tt>&nbsp;</dt><dd> Returns true when the category of posts being displayed is either term_ID 9, or <i>slug</i> "blue-cheese", or <i>name</i> "Stinky Cheeses".
</dd><dt> <tt>in_category( '5' )</tt>&nbsp;</dt><dd> Returns true if the current post is <b>in</b> the specified category id. <a href="http://codex.wordpress.org/Template_Tags/in_category" class="external text" title="http://codex.wordpress.org/Template_Tags/in_category">read more</a>
</dd></dl>
<dl><dt> <tt><a href="http://codex.wordpress.org/Function_Reference/is_tag" class="external text" title="http://codex.wordpress.org/Function_Reference/is_tag">is_tag()</a></tt>&nbsp;</dt><dd> When any Tag archive page is being displayed.
</dd><dt> <tt>is_tag( 'mild' )</tt>&nbsp;</dt><dd> When the archive page for tag with the slug of 'mild' is being displayed.
</dd><dt> <tt>is_tag( array( 'sharp', 'mild', 'extreme' ) )</tt>&nbsp;</dt><dd> Returns true when the tag archive being displayed has a slug of either "sharp", "mild", or "extreme".
</dd><dt> <tt>has_tag()</tt>&nbsp;</dt><dd> When the current post has a tag. Must be used inside The Loop.
</dd><dt> <tt>has_tag( 'mild' )</tt>&nbsp;</dt><dd> When the current post has the tag 'mild'.
</dd><dt> <tt>has_tag( array( 'sharp', 'mild', 'extreme' ) )</tt>&nbsp;</dt><dd> When the current post has any of the tags in the array.
</dd></dl>
	</div>
<?php
		$contextual_help = ob_get_clean();



		get_current_screen()->add_help_tab(
			array(
				'id' => 'acm-overview',
				'title' => 'Overview',
				'content' => $description,
			)
		);
		get_current_screen()->add_help_tab(
			array(
				'id' => 'acm-config',
				'title' => 'Configuration',
				'content' => $configuration,
			)
		);
		get_current_screen()->add_help_tab(
			array(
				'id' => 'acm-filters',
				'title' => 'Configuration Filters',
				'content' => $filters,
			)
		);
		get_current_screen()->add_help_tab(
			array(
				'id' => 'acm-conditionals',
				'title' => 'Conditionals',
				'content' => $contextual_help,
			)
		);
	}

	/**
	 * Register a custom widget to display ad zones
	 *
	 */
	function register_widget() {
		register_widget( 'ACM_Ad_Zones' );
	}

	/**
	 * Register scripts and styles
	 *
	 */
	function register_scripts_and_styles() {
		global $pagenow;

		// Only load this on the proper page
		if ( 'tools.php' != $pagenow || !isset( $_GET['page'] ) || $_GET['page'] != $this->plugin_slug )
			return;

		wp_enqueue_style( 'acm-style', AD_CODE_MANAGER_URL . '/common/css/acm.css' );
		wp_enqueue_script( 'acm', AD_CODE_MANAGER_URL . '/common/js/acm.js', array( 'jquery' ), false, true );
	}

	/**
	 * Register an ad tag with the plugin so it can be used
	 * on the frontend of the site
	 *
	 * @since 0.1
	 *
	 * @param string  $tag          Ad tag for this instance of code
	 * @param string  $url          Script URL for ad code
	 * @param array   $conditionals WordPress-style conditionals for where this code should be displayed
	 * @param int     $priority     What priority this registration runs at
	 * @param array   $url_vars     Replace tokens in $script with these values
	 * @param int     $priority     Priority of the ad code in comparison to others
	 * @return bool|WP_Error $success Whether we were successful in registering the ad tag
	 */
	function register_ad_code( $tag, $url, $conditionals = array(), $url_vars = array(), $priority = 10, $operator = false ) {

		// Run $url aganist a whitelist to make sure it's a safe URL
		if ( !$this->validate_script_url( $url ) )
			return;

		// @todo Sanitize the conditionals against our possible set of conditionals so that users
		// can't just run arbitrary functions. These are whitelisted on execution of the ad code so we're fine for now

		// @todo Sanitize all of the other input

		// Make sure our priority is an integer
		if ( !is_int( $priority ) )
			$priority = 10;

		// Make sure our operator is 'OR' or 'AND'
		if ( ! $operator || ! in_array( $operator, array( 'AND', 'OR' ) ) )
			$operator = $this->logical_operator;

		// Save the ad code to our set of ad codes
		$this->ad_codes[$tag][] = array(
			'url' => $url,
			'priority' => $priority,
			'operator' => $operator,
			'conditionals' => $conditionals,
			'url_vars' => $url_vars,
		);
	}

	/**
	 * Register an array of ad tags with the plugin
	 *
	 * @since 0.1
	 *
	 * @param array   $ad_codes An array of ad tags
	 */
	function register_ad_codes( $ad_codes = array() ) {
		if ( empty( $ad_codes ) )
			return;

		foreach ( (array)$ad_codes as $key => $ad_code ) {

			$default = array(
				'tag' => '',
				'url' => '',
				'conditionals' => array(),
				'url_vars' => array(),
				'priority' => 10,
				'operator' => $this->logical_operator,
			);
			$ad_code = array_merge( $default, $ad_code );

			foreach ( (array)$this->ad_tag_ids as $default_tag ) {

				/**
				 * 'enable_ui_mapping' is a special argument which means this ad tag can be
				 * mapped with ad codes through the admin interface. If that's the case, we
				 * want to make sure those ad codes are only registered with the tag.
				 */
				if ( isset( $default_tag['enable_ui_mapping'] ) && $default_tag['tag'] != $ad_code['url_vars']['tag'] )
					continue;

				/**
				 * Configuration filter: acm_default_url
				 * If you don't specify a URL for your ad code when registering it in
				 * the WordPress admin or at a code level, you can simply apply it with
				 * a custom filter defined.
				 */
				$ad_code['priority'] = strlen( $ad_code['priority'] ) == 0 ? 10 : intval( $ad_code['priority'] ); //make sure priority is int, if it's unset, we set it to 10

				// Make sure our operator is 'OR' or 'AND'
				if ( ! $ad_code['operator'] || ! in_array( $ad_code['operator'], array( 'AND', 'OR' ) ) )
					$operator = $this->logical_operator;

				$this->register_ad_code( $default_tag['tag'], apply_filters( 'acm_default_url', $ad_code['url'] ), $ad_code['conditionals'], array_merge( $default_tag['url_vars'], $ad_code['url_vars'] ), $ad_code['priority'], $ad_code['operator'] );
			}
		}
	}

	/**
	 * Display the ad code based on what's registered
	 * and complicated sorting logic
	 *
	 * @uses do_action( 'acm_tag, 'your_tag_id' )
	 *
	 * @param string  $tag_id Unique ID for the ad tag
	 * @param bool $echo whether to echo or return ( defaults to echo )
	 */
	function action_acm_tag( $tag_id, $echo = true ) {
		/**
		 * See http://adcodemanager.wordpress.com/2013/04/10/hi-all-on-a-dotcom-site-that-uses/
		 *
		 * Configuration filter: acm_disable_ad_rendering
		 * Should be boolean, defaulting to disabling ads on previews
		 */
		if ( apply_filters(  'acm_disable_ad_rendering',  is_preview() ) )
			return;

		$code_to_display = $this->get_matching_ad_code( $tag_id );

		// Run $url aganist a whitelist to make sure it's a safe URL
		if ( !$this->validate_script_url( $code_to_display['url'] ) )
			return;

		/**
		 * Configuration filter: acm_output_html
		 * Support multiple ad formats ( e.g. Javascript ad tags, or simple HTML tags )
		 * by adjusting the HTML rendered for a given ad tag.
		 */
		$output_html = apply_filters( 'acm_output_html', $this->current_provider->output_html, $tag_id );

		// Parse the output and replace any tokens we have left. But first, load the script URL
		$output_html = str_replace( '%url%', $code_to_display['url'], $output_html );
		/**
		 * Configuration filter: acm_output_tokens
		 * Register output tokens depending on the needs of your setup. Tokens are the
		 * keys to be replaced in your script URL.
		 */
		$output_tokens = apply_filters( 'acm_output_tokens', $this->current_provider->output_tokens, $tag_id, $code_to_display );
		foreach ( (array)$output_tokens as $token => $val ) {
			$output_html = str_replace( $token, esc_attr( $val ), $output_html );
		}

		/**
		 * Configuration filter: acm_output_html_after_tokens_processed
		 * In some rare cases you might want to filter html after the tokens are processed
		 */
		$output_html = apply_filters( 'acm_output_html_after_tokens_processed', $output_html, $tag_id );

		if ( $echo )
			// Print the ad code
			echo $output_html;
		else
			return $output_html;
	}

	/**
	 * Of all the ad codes registered, get the one that matches our current context
	 *
	 * @since 0.4
	 */
	public function get_matching_ad_code( $tag_id ) {
		global $wp_query;
		// If there aren't any ad codes, it's not worth it for us to do anything.
		if ( !isset( $this->ad_codes[$tag_id] ) )
			return;

		// This method might be expensive when there's a lot of ad codes
		// So instead of executing over and over again, return cached matching ad code
		$cache_key = "acm:{$tag_id}:" . md5( serialize( $wp_query->query_vars ) );

		if ( false !== $ad_code = wp_cache_get( $cache_key, 'acm' ) )
			return $ad_code;

		// Run our ad codes through all of the conditionals to make sure we should
		// be displaying it
		$display_codes = array();
		foreach ( (array)$this->ad_codes[$tag_id] as $ad_code ) {

			// If the ad code doesn't have any conditionals
			// we should add it to the display list
			if ( empty( $ad_code['conditionals'] ) && apply_filters( 'acm_display_ad_codes_without_conditionals', false ) ) {
				$display_codes[] = $ad_code;
				continue;
			}

			// If the ad code doesn't have any conditionals
			// and configuration filter acm_display_ad_codes_without_conditionals returns false
			// We should should skip it

			if ( empty( $ad_code['conditionals'] ) && ! apply_filters( 'acm_display_ad_codes_without_conditionals', false ) ) {
				continue;
			}

			$include = true;
			foreach ( $ad_code['conditionals'] as $conditional ) {
				// If the conditional was passed as an array, then we have a complex rule
				// Otherwise, we have a function name and expect rue
				if ( is_array( $conditional ) ) {
					$cond_func = $conditional['function'];
					if ( !empty( $conditional['arguments'] ) )
						$cond_args = $conditional['arguments'];
					else
						$cond_args = array();
					if ( isset( $conditional['result'] ) )
						$cond_result = $conditional['result'];
					else
						$cond_result = true;
				} else {
					$cond_func = $conditional;
					$cond_args = array();
					$cond_result = true;
				}

				// Special trick: include '!' in front of the function name to reverse the result
				if ( 0 === strpos( $cond_func, '!' ) ) {
					$cond_func = ltrim( $cond_func, '!' );
					$cond_result = false;
				}

				// Don't run the conditional if the conditional function doesn't exist or
				// isn't in our whitelist
				if ( !function_exists( $cond_func ) || !in_array( $cond_func, $this->whitelisted_conditionals ) )
					continue;

				// Run our conditional and use any arguments that were passed
				if ( !empty( $cond_args ) ) {
					/**
					 * Configuration filter: acm_conditional_args
					 * For certain conditionals (has_tag, has_category), you might need to
					 * pass additional arguments.
					 */
					$result = call_user_func_array( $cond_func, apply_filters( 'acm_conditional_args', $cond_args, $cond_func  ) );
				} else {
					$result = call_user_func( $cond_func );
				}

				// If our results don't match what we need, don't include this ad code
				if ( $cond_result !== $result )
					$include = false;
				else
					$include = true;

				// If we have matching conditional and $ad_code['operator'] equals OR just break from the loop and do not try to evaluate others
				if ( $include && $ad_code['operator'] == 'OR' )
					break;

				// If $ad_code['operator'] equals AND and one conditional evaluates false, skip this ad code
				if ( !$include && $ad_code['operator'] == 'AND' )
					break;

			}

			// If we're supposed to include the ad code even after we've run the conditionals,
			// let's do it
			if ( $include )
				$display_codes[] = $ad_code;

		}

		// Don't do anything if we've ended up with no ad codes
		if ( empty( $display_codes ) )
			return;

		// Prioritize the display of the ad codes based on
		// the priority argument for the ad code
		$prioritized_display_codes = array();
		foreach ( $display_codes as $display_code ) {
			$priority = $display_code['priority'];
			$prioritized_display_codes[$priority][] = $display_code;
		}
		ksort( $prioritized_display_codes, SORT_NUMERIC );

		$shifted_prioritized_display_codes = array_shift( $prioritized_display_codes );
		
		$code_to_display = array_shift( $shifted_prioritized_display_codes );

		wp_cache_add( $cache_key, $code_to_display, 'acm', 600 );

		return $code_to_display;
	}

	/**
	 * Filter the output tokens used in $this->action_acm_tag to include our URL vars
	 *
	 * @since 0.1
	 *
	 * @return array $output Placeholder tokens to be replaced with their values
	 */
	function filter_output_tokens( $output_tokens, $tag_id, $code_to_display ) {
		if ( !isset( $code_to_display['url_vars'] ) || !is_array( $code_to_display['url_vars'] ) )
			return $output_tokens;

		foreach ( $code_to_display['url_vars'] as $url_var => $val ) {
			$new_key = '%' . $url_var . '%';
			$output_tokens[$new_key] = $val;
		}

		return $output_tokens;
	}

	/**
	 * Ensure the URL being used passes our whitelist check
	 *
	 * @since 0.1
	 * @see https://gist.github.com/1623788
	 */
	function validate_script_url( $url ) {
		// If url is empty, there's nothing to validate
		// Fixes issue with DFP JS
		if ( empty( $url ) )
			return true;

		$domain = parse_url( $url, PHP_URL_HOST );

		// Check if we match the domain exactly
		if ( in_array( $domain, $this->current_provider->whitelisted_script_urls ) )
			return true;

		$valid = false;

		foreach ( $this->current_provider->whitelisted_script_urls as $whitelisted_domain ) {
			$whitelisted_domain = '.' . $whitelisted_domain; // Prevent things like 'evilsitetime.com'
			if ( strpos( $domain, $whitelisted_domain ) === ( strlen( $domain ) - strlen( $whitelisted_domain ) ) ) {
				$valid = true;
				break;
			}
		}
		return $valid;
	}

	/**
	 * Shortcode function
	 *
	 * @since 0.2
	 */
	function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => '',
			), $atts );

		$id = sanitize_text_field( $atts['id'] );
		if ( empty( $id ) )
			return;

		return $this->action_acm_tag( $id, false );
	}

}

global $ad_code_manager;
$ad_code_manager = new Ad_Code_Manager();
