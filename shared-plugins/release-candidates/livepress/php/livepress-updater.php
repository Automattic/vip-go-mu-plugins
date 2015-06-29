<?php
/**
 * LivePress Updater
 *
 * Core file of the livepress plugin.
 *
 * @package Livepress
 */

// @todo Add a comment for each require
require_once( LP_PLUGIN_PATH . 'php/livepress-config.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-communication.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-live-update.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-post.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-javascript-config.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-wp-utils.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-comment.php' );

class LivePress_Updater {
	const LIVEPRESS_ONCE = 'admin-options';
	private $options     = array();
	private $livepress_config;
	private $livepress_communication;
	private $lp_comment;

	private $old_post;

	private $title_css_selectors;
	private $background_colors;

	/**
	 * Contructor that assigns the wordpress hooks, initialize the
	 * configurable options and gets the wordpress options set.
	 */
	function __construct() {
		global $current_user;

		$this->blogging_tools = new LivePress_Blogging_Tools();

		$this->options = get_option( LivePress_Administration::$options_name );
		$this->options['ajax_nonce'] = wp_create_nonce( self::LIVEPRESS_ONCE );
		$this->options['ajax_comment_nonce'] = wp_create_nonce( 'post_comment' );
		$this->options['ajax_status_nonce'] = wp_create_nonce( 'lp_status' );
		$this->options['lp_add_live_update_tags_nonce'] = wp_create_nonce( 'lp_add_live_update_tags_nonce' );
		$this->options['ajax_twitter_search_nonce'] = wp_create_nonce( 'twitter_search_term' );
		$this->options['ajax_twitter_follow_nonce'] = wp_create_nonce( 'twitter_follow' );
		$this->options['ajax_lp_post_to_twitter'] = wp_create_nonce( 'lp_post_to_twitter_nonce' );

		$this->options['ajax_api_validate_nonce'] = wp_create_nonce( 'livepress_api_validate_nonce' );
		$this->options['lp_update_shortlink_nonce'] = wp_create_nonce( 'lp_update_shortlink' );
		$this->options['ajax_check_oauth'] = wp_create_nonce( 'lp_check_oauth_nonce' );
		$this->options['ajax_lp_collaboration_comments'] = wp_create_nonce( 'lp_collaboration_comments_nonce' );
		$this->options['ajax_get_live_edition_data'] = wp_create_nonce( 'get_live_edition_data_nonce' );
		$this->options['ajax_lp_im_integration'] = wp_create_nonce( 'lp_im_integration_nonce' );
		$this->options['ajax_render_tabs'] = wp_create_nonce( 'render_tabs_nonce' );
		$this->options['ajax_update_live_comments'] = wp_create_nonce( 'update_live_comments_nonce' );
		$this->options['ajax_new_im_follower'] = wp_create_nonce( 'new_im_follower_nonce' );
		$this->options['ajax_start_editor'] = wp_create_nonce( 'start_editor_nonce' );

		$this->user_options = get_user_option( LivePress_Administration::$options_name, $current_user->ID, false );
		$this->lp_comment = new LivePress_Comment( $this->options );

		add_action( 'before_delete_post',              array( $this, 'remove_related_post_data' ) );
		add_action( 'transition_post_status',          array( $this, 'send_to_livepress_new_post' ), 10, 3 );
		add_action( 'private_to_publish',              array( $this, 'livepress_publish_post' ) );
		add_action( 'wp_ajax_lp_twitter_search_term',  array( $this, 'twitter_search_callback' ) );
		add_action( 'wp_ajax_lp_twitter_follow',       array( $this, 'twitter_follow_callback' ) );
		add_action( 'wp_ajax_lp_status',               array( $this, 'lp_status' ) );
		add_action( 'wp_ajax_lp_add_live_update_tags', array( $this, 'lp_add_live_update_tags' ) );

		add_filter( 'update_post_metadata', array( $this, 'livepress_filter_post_locks' ), 10, 5 );
		add_filter( 'add_post_metadata',    array( $this, 'livepress_filter_post_locks' ), 10, 5 );

		$this->lp_comment->do_wp_binds( isset($_POST['livepress_update']) );


		if ( ! isset( $_POST['livepress_update'] ) ) {
			$livepress_enabled = $this->has_livepress_enabled();
			if ( $livepress_enabled ) {
				add_action( 'admin_head', array( &$this, 'embed_constants' ) );

				add_action( 'admin_enqueue_scripts', array( &$this, 'add_js_config' ), 11 );

				add_action( 'admin_enqueue_scripts', array( &$this, 'add_css_and_js_on_header' ), 10 );

				add_action( 'admin_head', array( $this, 'remove_auto_save' ), 10 );

				// Adds the modified tinyMCE
				if ( get_user_option( 'rich_editing' ) == 'true' ) {
					add_filter( 'mce_external_plugins', array( &$this, 'add_modified_tinymce' ) );
				}
			}
			// Ensuring that the post is divided into micro-post livepress chunks
			$live_update = $this->init_live_update();

				add_action( 'wp_enqueue_scripts', array( &$this, 'add_js_config' ), 11 );
				add_action( 'wp_enqueue_scripts', array( &$this, 'add_css_and_js_on_header' ), 10 );
			add_filter( 'edit_post', array( &$this, 'maybe_merge_post' ), 10 );

			global $post;
			$is_live = isset( $post ) ? LivePress_Updater::instance()->blogging_tools->get_post_live_status( $post->ID ) : false;
			if( $is_live ){

				add_action( 'pre_post_update',  array( &$this, 'save_old_post' ),  999 );
				add_filter( 'save_post', array( &$this, 'save_lp_post_options' ), 10 );
				add_filter( 'content_save_pre', array( $live_update, 'fill_livepress_shortcodes' ), 5 );
			}


		}

		$api_key = isset( $this->options['api_key'] ) ? $this->options['api_key'] : false;
		$this->livepress_config        = LivePress_Config::get_instance();
		$this->livepress_communication = new LivePress_Communication( $api_key );

		// Moved these values from config file for increased VIP compatibility
		$this->title_css_selectors = array(
				'Pixel'      => 'h2.topTitle, .sidebarbox ul',
				'Monochrome' => '.post_content_wrapper h2 span',
			);
		$this->background_colors = array(
				'wordpress default' => '#FFFFFF',
			);

		// Add the Livepress Tags custom taxonomy
		register_taxonomy(
			'livetags',
			apply_filters( 'livepress_post_types', array( 'post' ) ),
			array(
				'label'   => __( 'Live Update Tags' ),
				'public'  => false,
				'show_ui' => true,
			)
		);
		// We only want the taxonomy to show in the menu, not on the post edit page
		add_action( 'admin_menu' , array( &$this, 'remove_livetag_metabox' ) );

	}

	/**
	 * for the current post remove its auto save
	 * we don't use the default drafts / autosaves in livepress mode so remove any to stop messages etc.
	 */
	function remove_auto_save(){
		global $post;
		if ( null !== $post ){
			$autosave = wp_get_post_autosave( $post->ID );
			if ( false !== $autosave ){
				wp_delete_post_revision( $autosave->ID );
			}
		}

	}

	function remove_livetag_metabox() {
		// Remove livetag meta box for all live enabled CPTS
		foreach ( apply_filters( 'livepress_post_types', array( 'post' ) ) as $posttype ) {
			remove_meta_box( 'tagsdiv-livetags', esc_attr( $posttype ), 'side' );
		}
	}

	/**
	 * Ajax callback to add new live update tags to the livetags taxonomy
	 */
	public function lp_add_live_update_tags() {
		// Nonce check
		check_ajax_referer( 'lp_add_live_update_tags_nonce' );
		// Capabilities check
		$post_id = isset( $_POST['post_id'] ) ? $_POST['post_id'] : null;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			die;
		}

		// Grab the new tags
		$new_tags = json_decode( stripslashes( $_POST['new_tags'] ) );

		// Add them to the taxonomy
		foreach ( $new_tags as $a_new_tag ) {
			wp_insert_term( $a_new_tag, 'livetags' );
		}
		wp_send_json_success();
	}

	/**
	 * Save the LivePress post options
	**/
	public function save_lp_post_options( $post_ID = 0 ) {
		$post_id = absint( $post_ID );
		if ( $this->blogging_tools->get_post_live_status( $post_id ) ) {
			$enable_live_header = ( ( isset( $_POST['pinfirst'] ) && '1' === $_POST['pinfirst'] ) ? '1' : '0' );
			$this->blogging_tools->set_post_header_enabled( $post_id, $enable_live_header );
		}
	}

	/**
	 * Twitter search callback.
	 */
	public function twitter_search_callback() {
		global $current_user;
		$action = $_POST['action_type'];
		$term   = $_POST['term'];
		$postId = $_POST['post_id'];

		check_ajax_referer( 'twitter_search_term' );
		if ( Collaboration::twitter_search( $action, $postId, $current_user->ID, $term ) ) {
			echo '{"success":"OK"}';
		} else {
			echo '{"errors":"FAIL"}';
		}
		die;
	}

	/**
	 * Twitter follow callback.
	 */
	public function twitter_follow_callback() {
		global $current_user;
		$login  = $current_user->user_login;
		$action = $_POST['action_type'];
		$term   = $_POST['username'];
		$postId = $_POST['post_id'];

		check_ajax_referer( 'twitter_follow' );
		$ret = $this->livepress_communication->send_to_livepress_handle_twitter_follow( $action, $term, $postId, $login );

		if ( $ret == '{"errors":{"username":"[not_exists]"}}' ) {
			$la = new LivePress_Administration();
			if ( $la->enable_remote_post( $current_user->ID ) ) {
				$ret = $this->livepress_communication->send_to_livepress_handle_twitter_follow( $action, $term, $postId, $login );
			}
		}
		echo wp_kses_post( $ret );
		die;
	}

	/**
	 * LivePress status.
	 */
	public function lp_status() {
		check_ajax_referer( 'lp_status' );
		$post_id = isset( $_GET['post_id'] ) ? $_GET['post_id'] : null;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			die;
		}
		$uuid    = LivePress_WP_Utils::get_from_post( $post_id, 'status_uuid', true );
		if ( $uuid ) {
			try {
				$status = $this->livepress_communication->get_job_status( $uuid );
				if ( $status == 'completed' || $status == 'failed' ) {
					$this->delete_from_post( $post_id, 'status_uuid' );
				}
				wp_die( $status );
			} catch (LivePress_Communication_Exception $e) {
				$this->delete_from_post( $post_id, 'status_uuid' );
				wp_die( 'lp_failed' );
			}
		} else {
			wp_die( 'empty' );
		}
		die;
	}

	/**
	 * Remove related post data.
	 *
	 * Called on before_delete_post hook.
	 *
	 * @param int $postId Post ID.
	 */
	public function remove_related_post_data( $postId ) {
		global $current_user;
		$login = $current_user->user_login;
		$this->livepress_communication->send_to_livepress_handle_twitter_follow( 'clear', '', $postId, $login ); // TODO: Handle error
		Collaboration::clear_searches( $postId, $login ); //clear all term searches for this post/user

	}

	/**
	 * Get comments enabled status.
	 *
	 * @return bool
	 */
	public function is_comments_enabled() {
		return ! isset($this->options['disable_comments'])|| ! $this->options['disable_comments'];
	}

	/**
	 * Checks if the current user is allowed to use the livepress features.
	 *
	 * @return bool
	 */
	public function has_livepress_enabled() {
		$enabled_to = isset( $this->options['enabled_to'] ) ? $this->options['enabled_to'] : false;

		return  current_user_can( 'manage_options' )      // the user is admin
				&&  $enabled_to !== 'none'    // and not blocked for everyone
				||  $enabled_to === 'all'   // everybody is allowed
				||  $enabled_to === 'registered'
				&&  current_user_can( 'edit_posts' ) // the user is registered
		;
	}

	/**
	 * Instance.
	 *
	 * @static
	 * @access private
	 * @var LivePress_Updater $updater
	 */
	static private $updater = null;

	/**
	 * Instance.
	 *
	 * @static
	 *
	 * @return LivePress_Updater
	 */
	 public static function instance() {
		if ( self::$updater == null ) {
			self::$updater = new LivePress_Updater();
		}
		return self::$updater;
	}

	/**
	 * Embed constants in the page output.
	 */
	function embed_constants() {
		$api_key = isset( $this->options['api_key'] ) ? $this->options['api_key'] : false;

		echo "<script>\n";
		echo "var LIVEPRESS_API_KEY    = '" . esc_attr( $api_key ) . "';\n";
		echo "var WP_PLUGIN_URL        = '" . esc_url( WP_PLUGIN_URL ) . "';\n";
		echo "var OORTLE_STATIC_SERVER = '" . esc_url( $this->livepress_config->static_host() ) . "';\n";
		echo "</script>\n";
	}

	/**
	 * Check whether this is a LivePress page.
	 *
	 * @return bool
	 */
	function is_livepress_page() {
		if ( isset($_GET) && isset($_GET['page']) && ($_GET['page'] == 'livepress' || $_GET['page'] == 'livepress_author' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook Hook suffix for the current screen.
	 */
	function add_css_and_js_on_header( $hook = null ) {
		if ( is_home() ) {
			return;
		}

		if ( $hook != null && $hook != 'post-new.php' && $hook != 'post.php' && $hook != 'settings_page_livepress-settings' ) {
			return; }

		wp_register_style( 'livepress_main_sheets', LP_PLUGIN_URL . 'css/livepress.css' );
		wp_enqueue_style( 'livepress_main_sheets' );

		global $post;
		if ( isset( $post ) && ! is_admin() ) {
			$blogging_tools = new LivePress_Blogging_Tools();
			$is_live = $blogging_tools->get_post_live_status( $post->ID );
			if ( ! $is_live ) {
				return;
			}
		}

		// On the profile page in VIP, load the profile script - handles showing password field
		if ( 'profile.php' == $hook && defined( 'WPCOM_IS_VIP_ENV' ) && false !== WPCOM_IS_VIP_ENV ) {
			if ( $this->livepress_config->script_debug() ) {
				wp_enqueue_script( 'livepress-profile', LP_PLUGIN_URL . 'js/livepress-profile.full.js', array( 'jquery' ) );
			} else {
				wp_enqueue_script( 'livepress-profile', LP_PLUGIN_URL . 'js/livepress-profile.min.js', array( 'jquery' ) );
			}
		}

		wp_enqueue_style( 'wp-mediaelement' );
		wp_enqueue_script( 'wp-mediaelement' );

		wp_register_style( 'wordpress_override', LP_PLUGIN_URL . 'css/livepress_wordpress_override.css' );
		wp_enqueue_style( 'wordpress_override' );

		wp_register_style( 'livepress_ui', LP_PLUGIN_URL . 'css/ui.css', array(), false, 'screen' );

		// FIXME: temporary import of this script. It must be sent by Oortle
		// on the next versions
		$static_host = $this->livepress_config->static_host();

		if ( $this->livepress_config->script_debug() ) {
			$mode = 'full';
		} else {
			$mode = 'min';
		}

		if ( is_page() || is_single() || is_home() ) {
			wp_enqueue_script( 'livepress-plugin-loader', LP_PLUGIN_URL . 'js/plugin_loader_release.' . $mode . '.js', array( 'jquery' ), LP_PLUGIN_VERSION );

			$lp_client_settings = array(
				'no_google_acct'       => esc_html__( 'Error: [USERNAME] isn\'t a valid Google Talk account', 'livepress' ),
				'no_acct_in_jabber'    => esc_html__( 'Error: [USERNAME] wasn\'t found in jabber client bot roster', 'livepress' ),
				'username_not_auth'    => esc_html__( '[USERNAME] has been authorized', 'livepress' ),
				'username_authorized'  => esc_html__( '[USERNAME] has been authorized', 'livepress' ),
				'livepress_err_retry'  => esc_html__( 'LivePress! error. Try again in a few minutes', 'livepress' ),
				'auth_request_sent'    => esc_html__( 'Authorization request sent to [USERNAME]', 'livepress' ),
				'new_updates'          => esc_html__( 'new updates', 'livepress' ),
				'refresh'              => esc_html__( 'Refresh', 'livepress' ),
				'to_see'               => esc_html__( 'to see', 'livepress' ),
				'them'                 => esc_html__( 'them', 'livepress' ),
				'refresh_page'         => esc_html__( 'Refresh page', 'livepress' ),
				'updated_just_now'     => esc_html__( 'updated just now', 'livepress' ),
				'uptated_amin_ago'     => esc_html__( 'updated 1 minute ago', 'livepress' ),
				'updated'              => esc_html__( 'updated', 'livepress' ),
				'minutes_ago'          => esc_html__( 'minutes ago', 'livepress' ),
				'no_recent_updates'    => esc_html__( 'no recent updates', 'livepress' ),
				'sending_error'        => esc_html__( 'Sending error.', 'livepress' ),
				'sending'              => esc_html__( 'Sending', 'livepress' ),
				'unknown_error'        => esc_html__( 'Unknown error.', 'livepress' ),
				'comment_status'       => esc_html__( 'Comment Status', 'livepress' ),
				'copy_permalink'       => esc_html__( 'Ctrl / Cmd C to copy', 'livepress' ),
				'filter_by_tag'        => esc_html__( 'Filter by Tag:', 'livepress' ),
				'by'                   => esc_html__( 'by', 'livepress' ),
			);

			$options  = $this->options;
			if ( array_key_exists( 'show', $options ) && null !== $options['show'] ){
				$lp_client_settings['show'] = $options['show'];
			}

			wp_localize_script( 'livepress-plugin-loader', 'lp_client_strings', $lp_client_settings );

			wp_enqueue_style( 'livepress_ui' );

			wp_enqueue_script( 'jquery-effects-core' );
			wp_enqueue_script( 'jquery-effects-bounce' );

		} elseif ( is_admin() ) {

			// Add select2 for live tags
			wp_enqueue_style( 'select2', LP_PLUGIN_URL . 'css/select2.css', array(), LP_PLUGIN_VERSION );

			wp_register_style( 'lp_admin', LP_PLUGIN_URL . 'css/post_edit.css' );
			wp_enqueue_style( 'lp_admin' );

			wp_enqueue_style( 'lp_admin_font', LP_PLUGIN_URL . 'fonts/livepress-admin/style.css' );

			wp_enqueue_script( 'lp-admin', LP_PLUGIN_URL . 'js/admin/livepress-admin.' . $mode . '.js', array( 'jquery' ), LP_PLUGIN_VERSION );

			$livepress_authors = LivePress_Administration::lp_get_authors();

			$lp_strings = array(
				'unexpected'                   => esc_html__( 'Unexpected result from the LivePress server.  Please contact LivePress support at support@livepress.com for help.', 'livepress' ),
				'connection_problem'           => esc_html__( 'Connection problem... Try Again...', 'livepress' ),
				'authenticated'                => esc_html__( 'Authenticated', 'livepress' ),
				'sending'                      => esc_html__( 'Sending', 'livepress' ),
				'check'                        => esc_html__( 'Check', 'livepress' ),
				'remove'                       => esc_html__( 'Remove', 'livepress' ),
				'key_not_valid'                => esc_html__( 'Key not valid', 'livepress' ),
				'sending_twitter'              => esc_html__( 'Sending out alerts on Twitter account', 'livepress' ),
				'failed_to_check'              => esc_html__( 'Failed to check status.', 'livepress' ),
				'checking_auth'                => esc_html__( 'Checking authorization status', 'livepress' ),
				'status_not_avail'             => esc_html__( 'Status isn\'t available yet.', 'livepress' ),
				'internal_error'               => esc_html__( 'Internal server error.', 'livepress' ),
				'noconnect_twitter'            => esc_html__( 'Can\'t connect to Twitter.', 'livepress' ),
				'noconnect_livepress'          => esc_html__( 'Can\'t connect to livepress service.', 'livepress' ),
				'failed_unknown'               => esc_html__( 'Failed for an unknown reason.', 'livepress' ),
				'return_code'                  => esc_html__( 'return code', 'livepress' ),
				'new_twitter_window'           => esc_html__( 'A new window should be open to Twitter.com awaiting your login.', 'livepress' ),
				'twitter_unauthorized'         => esc_html__( 'Twitter access unauthorized.', 'livepress' ),
				'start_live'                   => esc_html__( 'Start live blogging', 'livepress' ),
				'stop_live'                    => esc_html__( 'Stop live blogging', 'livepress' ),
				'readers_online'               => esc_html__( 'readers online', 'livepress' ),
				'lp_updates_posted'            => esc_html__( 'live updates posted', 'livepress' ),
				'comments'                     => esc_html__( 'comments', 'livepress' ),
				'click_to_toggle'              => esc_html__( 'Click to toggle', 'livepress' ),
				'real_time_editor'             => esc_html__( 'Live Blogging Real-Time Editor', 'livepress' ),
				'off'                          => esc_html__( 'off', 'livepress' ),
				'on'                           => esc_html__( 'on', 'livepress' ),
				'private_chat'                 => esc_html__( 'Private chat', 'livepress' ),
				'persons_online'               => esc_html__( 'Person Online', 'livepress' ),
				'people_online'                => esc_html__( 'People Online', 'livepress' ),
				'comment'                      => esc_html__( 'Comment', 'livepress' ),
				'include_timestamp'            => esc_html__( 'include timestamp', 'livepress' ),
				'live_update_tags'             => esc_html__( 'Live tags:', 'livepress' ),
				'lp_authors'                   => $livepress_authors['names'],
				'lp_gravatars'                 => $livepress_authors['gravatars'],
				'lp_avatar_links'              => $livepress_authors['links'],
				'live_tags_select_placeholder' => esc_html__( 'Live update tag(s)', 'livepress' ),
				'live_update_header'           => esc_html__( 'Live update header', 'livepress' ),
				'live_update_byline'           => esc_html__( 'Author(s):', 'livepress' ),
				'delete_perm'                  => esc_html__( 'Delete Permanently', 'livepress' ),
				'ctrl_enter'                   => esc_html__( 'Ctrl+Enter', 'livepress' ),
				'cancel'                       => esc_html__( 'Cancel', 'livepress' ),
				'save'                         => esc_html__( 'Save', 'livepress' ),
				'draft'                        => esc_html__( 'Save as draft', 'livepress' ),
				'push_update'                  => esc_html__( 'Push Update', 'livepress' ),
				'add_update'                   => esc_html__( 'Publish Draft', 'livepress' ),
				'confirm_delete'               => esc_html__( 'Are you sure you want to delete this update? This action cannot be undone.', 'livepress' ),
				'updates'                      => esc_html__( 'Updates', 'livepress' ),
				'discard_unsaved'              => esc_html__( 'You have unsaved editors open. Discard them?', 'livepress' ),
				'loading_content'              => esc_html__( 'Loading content', 'livepress' ),
				'double_added'                 => esc_html__( 'Double added updates. Not supported now.', 'livepress' ),
				'confirm_switch'               => esc_html__( 'Are you sure you want to switch to Not Live?', 'livepress' ),
				'scroll'                       => esc_html__( 'Scroll to Comment', 'livepress' ),
				'update_published'             => esc_html__( 'Update was published live to users.', 'livepress' ),
				'published_not_live'           => esc_html__( 'Update was published NOT live.', 'livepress' ),
				'cant_get_update'              => esc_html__( 'Can\'t get update status from LivePress.', 'livepress' ),
				'wrong_ajax_nonce'             => esc_html__( 'Wrong AJAX nonce.', 'livepress' ),
				'cant_get_blog_update'         => esc_html__( 'Can\'t get upate status from blog server.', 'livepress' ),
				'sending_alerts'               => esc_html__( 'Sending out alerts on Twitter account:', 'livepress' ),
				'tools_link_text'              => esc_html__( 'Live Blogging Tools', 'livepress' ),
				'submit'                       => esc_html__( 'submit', 'livepress' ),
				'live_press'                   => esc_html__( 'Live Press', 'livepress' ),
				'live_chat'                    => esc_html__( 'Live Chat', 'livepress' ),
				'warning'                      => esc_html__( 'Warning', 'livepress' ),
				'connection_lost'              => esc_html__( 'Will try to reconnect in some time.', 'livepress' ),
				'connect_again'                => esc_html__( 'Please try to reconnect later.', 'livepress' ),
				'connection_just_lost'         => esc_html__( 'The connection to the server has been lost.', 'livepress' ),
				'sync_lost'                    => esc_html__( 'Syncronization of live editor seems to be lost. Try to enable/disable live editor or reload page.', 'livepress' ),
				'collabst_sync_lost'           => esc_html__( 'Collaboration state may be out of sync. Try to reload page.', 'livepress' ),
				'collab_sync_lost'             => esc_html__( 'Collaboration may be out of sync. Try to reload page.', 'livepress' ),
				'post_link'                    => esc_html__( 'Copy the commenter name and full text into the post text box', 'livepress' ),
				'send_to_editor'               => esc_html__( 'Send to editor', 'livepress' ),
				'approve_comment'              => esc_html__( 'Approve this comment', 'livepress' ),
				'approve'                      => esc_html__( 'Approve', 'livepress' ),
				'unapprove_comment'            => esc_html__( 'Unapprove this comment', 'livepress' ),
				'unapprove'                    => esc_html__( 'Unapprove', 'livepress' ),
				'mark_as_spam'                 => esc_html__( 'Mark this comment as spam', 'livepress' ),
				'spam'                         => esc_html__( 'Spam', 'livepress' ),
				'move_comment_trash'           => esc_html__( 'Move this comment to the trash', 'livepress' ),
				'trash'                        => esc_html__( 'Trash', 'livepress' ),
				'save_and_refresh'             => esc_html__( 'Save and Refresh', 'livepress' ),
				'publish_and_refresh'          => esc_html__( 'Publish and Refresh', 'livepress' ),
				'click_pause_tweets'           => esc_html__( 'Click to pause the tweets so you can decide when to display them', 'livepress' ),
				'click_copy_tweets'            => esc_html__( 'Click to copy tweets into the post editor.', 'livepress' ),
				'copy_tweets'                  => esc_html__( 'Copy the tweet into the post editing area', 'livepress' ),
				'remove_term'                  => esc_html__( 'Remove this term', 'livepress' ),
				'remove_account'               => esc_html__( 'Remove this account', 'livepress' ),
				'remove_lower'                 => esc_html__( 'remove', 'livepress' ),
				'remove_author'                => esc_html__( 'Remote Author', 'livepress' ),
				'remove_authors'               => esc_html__( 'Remote Authors', 'livepress' ),
				'account_not_found'            => esc_html__( 'Account not found', 'livepress' ),
				'connecting'                   => esc_html__( 'Connecting', 'livepress' ),
				'offline'                      => esc_html__( 'offline', 'livepress' ),
				'connected'                    => esc_html__( 'connected', 'livepress' ),
				'user_pass_invalid'            => esc_html__( 'username/password invalid', 'livepress' ),
				'wrong_account_name'           => esc_html__( 'Wrong account name supplied', 'livepress' ),
				'problem_connecting'           => esc_html__( 'Problem connecting to the blog server.', 'livepress' ),
				'test_msg_sent'                => esc_html__( 'Test message sent', 'livepress' ),
				'test_msg_failure'             => esc_html__( 'Failure sending test message', 'livepress' ),
				'send_again'                   => esc_html__( 'Send test message again', 'livepress' ),
				'live_post_header'             => esc_html__( 'Pinned Live Post Header', 'livepress' ),
				'visual_text_editor'           => esc_html__( 'Visual', 'livepress' ),
				'text_editor'                  => esc_html__( 'Text', 'livepress' ),
			);

			wp_localize_script( 'lp-admin', 'lp_strings', $lp_strings );

			wp_enqueue_script( 'dashboard-dyn', LP_PLUGIN_URL . 'js/dashboard-dyn.' . $mode . '.js', array( 'jquery', 'lp-admin' ), LP_PLUGIN_VERSION );
		}

		$lan = explode( '_', get_locale() );
		if ( 'en' !== $lan[0] ){
			$timeago_lan = $lan[0];
			switch( strtolower( get_locale() ) ){
				case 'pt_br':
					$timeago_lan = 'pt-br';
					break;
				case 'zh_cn':
					$timeago_lan = 'zh-CN';
					break;
				case 'zh_tw':
					$timeago_lan = 'zh-TW';
					break;
			}

			wp_enqueue_script( 'jquery.timeago.'.$lan[0], LP_PLUGIN_URL . "js/locales/jquery.timeago.$timeago_lan.js", array('jquery.timeago') );
			$select2_lan = $lan[0];
			switch( strtolower( get_locale() ) ){
				case 'pt_br':
					$select2_lan = 'pt-BR';
					break;
				case 'pt_pt':
					$select2_lan = 'pt-PT';
					break;
				case 'ug_cn':
					$select2_lan = 'ug-CN';
					break;
				case 'zh_cn':
					$select2_lan = 'zh-CN';
					break;
				case 'zh_tw':
					$select2_lan = 'zh-TW';
					break;
			}
			wp_enqueue_script( 'select2_locale.'.$lan[0], LP_PLUGIN_URL . "js/locales/select2_locale_$select2_lan.js", array('lp-admin') );
		}

		$current_theme = str_replace( ' ', '-', strtolower( wp_get_theme()->Name ) );
		if ( file_exists( LP_PLUGIN_PATH . 'css/themes/' . $current_theme . '.css' ) ) {
			wp_register_style( 'livepress_theme_hacks', LP_PLUGIN_URL . 'css/themes/' . $current_theme . '.css' );
			wp_enqueue_style( 'livepress_theme_hacks' );
		}
	}

	/**
	 * Filter update_post_meta, discard post locks for live posts.
	 *
	 * This allows two silmoutaneous editors and overrides the post
	 * locking introduced in WordPress 3.6.0.
	 *
	 * @return false if post is live and meta key is _edit_lock, otherwise null (lets meta save).
	 */
	function livepress_filter_post_locks() {
		$args = func_get_args();

		if ( is_admin() && isset( $args[1] ) && isset( $args[2] ) && $args[2] == '_edit_lock' ) {
			$is_live = $this->blogging_tools->get_post_live_status( $args[1] );
			return ( $is_live ? false : null );
		} else {
			return null;
		}
	}

	/**
	 * Merge child posts when merge action initiated.
	 *
	 * @param int $post_id Post ID.
	 */
	function maybe_merge_post( $post_id ) {

		if ( ! isset( $_GET['merge_noonce'] ) || ! isset( $_GET['merge_action'] ) ){
			return false;
		}

		if ( 'merge_children' !== $_GET['merge_action'] ) {}

		$nonce = $_GET['merge_noonce'];

		if ( ! wp_verify_nonce( $nonce, 'livepress-merge_post-' . $post_id ) ) {
			return false;
		}

		// Temporarily remove the edit post filter, so we can update without firing it again.
		remove_filter( 'edit_post', array( &$this, 'maybe_merge_post' ), 10 );

		// Merge the child posts
		$post = LivePress_PF_Updates::get_instance()->merge_children( $post_id );

		// Update the post
		wp_update_post( $post );

		// Clear the post term cache
		Collaboration::clear_terms_cache( $post_id );

		// Re-add the edit_post filter.
		add_filter( 'edit_post', array( &$this, 'maybe_merge_post' ), 10 );

		return true;
	}

	/**
	 * Set up JS config for output.
	 *
	 * @param string $hook Hook suffix for the current screen.
	 */
	function add_js_config( $hook = null ) {
		global $post;

		// If we are in the admin, only load LivePress JS on the
		// post page and the livepress settings page
		if ( is_admin() && $hook != null && $hook != 'post-new.php' && $hook != 'post.php' && $hook != 'settings_page_livepress-settings' ) {
			return;
		}
		$is_live = isset( $post ) ? LivePress_Updater::instance()->blogging_tools->get_post_live_status( $post->ID ) : false;
		// On the front end, only load LivePress JS on pages that are live,
		// or when the WordPress admin bar is showing
		if ( ! is_admin() && ! is_admin_bar_showing() && ! $is_live ) {
			return;
		}

		$ljsc = new LivePress_JavaScript_Config();

		if ( $this->livepress_config->debug() || $this->livepress_config->script_debug() ) {
			$ljsc->new_value( 'debug', true, Livepress_Configuration_Item::$BOOLEAN );
		}
		$ljsc->new_value( 'ajax_nonce', $this->options['ajax_nonce'] );
		$ljsc->new_value( 'ajax_comment_nonce', $this->options['ajax_comment_nonce'] );
		$ljsc->new_value( 'ajax_status_nonce', $this->options['ajax_status_nonce'] );
		$ljsc->new_value( 'lp_add_live_update_tags_nonce', $this->options['lp_add_live_update_tags_nonce'] );
		$ljsc->new_value( 'ajax_twitter_search_nonce', $this->options['ajax_twitter_search_nonce'] );
		$ljsc->new_value( 'ajax_twitter_follow_nonce', $this->options['ajax_twitter_follow_nonce'] );
		$ljsc->new_value( 'ajax_api_validate_nonce', $this->options['ajax_api_validate_nonce'] );
		$ljsc->new_value( 'ajax_lp_post_to_twitter', $this->options['ajax_lp_post_to_twitter'] );
		$ljsc->new_value( 'ajax_check_oauth', $this->options['ajax_check_oauth'] );
		$ljsc->new_value( 'lp_update_shortlink_nonce', $this->options['lp_update_shortlink_nonce'] );
		$ljsc->new_value( 'ajax_lp_collaboration_comments', $this->options['ajax_lp_collaboration_comments'] );
		$ljsc->new_value( 'ajax_get_live_edition_data', $this->options['ajax_get_live_edition_data'] );
		$ljsc->new_value( 'ajax_lp_im_integration', $this->options['ajax_lp_im_integration'] );
		$ljsc->new_value( 'ajax_render_tabs', $this->options['ajax_render_tabs'] );
		$ljsc->new_value( 'ajax_update_live_comments', $this->options['ajax_update_live_comments'] );
		$ljsc->new_value( 'ajax_new_im_follower', $this->options['ajax_new_im_follower'] );
		$ljsc->new_value( 'ajax_start_editor', $this->options['ajax_start_editor'] );

		$ljsc->new_value( 'ver', LP_PLUGIN_VERSION );
		$ljsc->new_value( 'oover', $this->livepress_config->lp_ver(), Livepress_Configuration_Item::$ARRAY );
		{
			global $wp_scripts;
		if ( $wp_scripts === null ) {
			$wp_scripts = new WP_Scripts();
			wp_default_scripts( $wp_scripts );
		}
		if ( is_a( $wp_scripts, 'WP_Scripts' ) ) {
			$src = $wp_scripts->query( 'jquery' );
			$src = $src->src;
			if ( ! preg_match( '|^https?://|', $src ) && ! ( $wp_scripts->content_url && 0 === strpos( $src, $wp_scripts->content_url ) ) ) {
				$src = $wp_scripts->base_url . $src;
			}
			$ljsc->new_value( 'jquery_url', $src.'?' );
		}
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_a( $screen, 'WP_Screen' ) ) {
				$ljsc->new_value( 'current_screen', array(
					'base' => $screen->base,
					'id'   => $screen->id
				), Livepress_Configuration_Item::$ARRAY );
			}
		}

		$ljsc->new_value( 'wpstatic_url', LP_PLUGIN_URL );
		$ljsc->new_value( 'static_url', $this->livepress_config->static_host() );
		$ljsc->new_value( 'lp_plugin_url', LP_PLUGIN_URL );

		$ljsc->new_value( 'blog_gmt_offset', get_option( 'gmt_offset' ),
		Livepress_Configuration_Item::$LITERAL);

		$theme_name = strtolower( wp_get_theme()->Name );
		$ljsc->new_value( 'theme_name', $theme_name );
		try {
			if ( isset($this->title_css_selectors[ $theme_name ] ) ){
				$title_css_selector = $this->title_css_selectors[$theme_name];
				$title_css_selector = apply_filters( 'livepress_title_css_selector', $title_css_selector, $theme_name );
				$ljsc->new_value( 'custom_title_css_selector', $title_css_selector );
			}
		} catch (livepress_invalid_option_exception $e) {}
		try {
			if ( isset($this->background_colors[ $theme_name ] ) ){
				$background_color = $this->background_colors[ $theme_name ];
				$background_color = apply_filters( 'livepress_background_color', $background_color, $theme_name );
				$ljsc->new_value( 'custom_background_color', $background_color );
			}
		} catch (livepress_invalid_option_exception $e) {}

		if ( is_home() ) {
			$page_type = 'home';
		} elseif ( is_page() ) {
			$page_type = 'page';
		} elseif ( is_single() ) {
			$page_type = 'single';
		} elseif ( is_admin() ) {
			$page_type = 'admin';
		} else {
			$page_type = 'undefined';
		}
		$ljsc->new_value( 'page_type', $page_type );

		// Comments
		if ( isset( $post->ID ) ) {
			$args = array( 'post_id' => ( isset( $post->ID ) ? $post->ID : null ) );
			$post_comments = get_comments( $args );
			$old_comments = isset( $GLOBALS['wp_query']->comments ) ? $GLOBALS['wp_query']->comments : null;
			$GLOBALS['wp_query']->comments = $post_comments;
			$comments_per_page = get_comment_pages_count( $post_comments, get_option( 'comments_per_page' ) );
			$GLOBALS['wp_query']->comments = $old_comments;
			$this->lp_comment->js_config( $ljsc, $post, intval( get_query_var( 'cpage' ) ), $comments_per_page );

			// Fetch rexisting shortlinks
			$sl = array();
			foreach ( get_post_meta( $post->ID ) as $k => $m ) {
				if ( preg_match( '/^_livepress_shortlink_([0-9]+)$/', $k, $r ) ) {
					$sl[ $r[1] ] = $m[0];
				}
			}
			$ljsc->new_value( 'shortlink', $sl, Livepress_Configuration_Item::$ARRAY );
			$ljsc->new_value( 'post_url', get_permalink( $post->ID ) );
			$ljsc->new_value( 'post_title', $post->post_title );
		}
		$ljsc->new_value( 'new_post_msg_id', get_option( LP_PLUGIN_NAME.'_new_post' ) );

		$notifications = isset( $this->options['notifications'] ) ? $this->options['notifications'] : array();
		$ljsc->new_value( 'sounds_default', in_array( 'audio', $notifications ), Livepress_Configuration_Item::$BOOLEAN );
		$ljsc->new_value( 'autoscroll', in_array( 'scroll', $notifications ), Livepress_Configuration_Item::$BOOLEAN );
		$ljsc->new_value( 'effects', in_array( 'effects', $notifications ), Livepress_Configuration_Item::$BOOLEAN );

		// colors used to highlight changes
		$ljsc->new_value( 'oortle_diff_inserted',       apply_filters( 'livepress_effects_inserted', '#55C64D' ) );
		$ljsc->new_value( 'oortle_diff_changed',        apply_filters( 'livepress_effects_changed', '#55C64D' ) );
		$ljsc->new_value( 'oortle_diff_inserted_block', apply_filters( 'livepress_effects_inserted_block', '#ffff66' ) );
		$ljsc->new_value( 'oortle_diff_removed_block',  apply_filters( 'livepress_effects_removed_block', '#C63F32' ) );
		$ljsc->new_value( 'oortle_diff_removed',        apply_filters( 'livepress_effects_removed', '#C63F32' ) );
		if ( is_admin() || $is_live ) {
			if ( isset($post->ID)&&$post->ID ) {
				$args = array( 'post_id' => $post->ID );
				$post_comments = get_comments( $args );
				if ( ! empty($post_comments) ) {
					$ljsc->new_value( 'comment_pages_count',
						get_comment_pages_count( $post_comments, get_option( 'comments_per_page' ) ),
					Livepress_Configuration_Item::$LITERAL);
				}

				$feed_link = $this->get_current_post_feed_link();
				if ( sizeof( $feed_link ) ) {
					$ljsc->new_value( 'feed_sub_link', $feed_link[0] );
					$ljsc->new_value( 'feed_title', LivePress_Feed::feed_title() );
				}

				$ljsc->new_value( 'post_id', $post->ID );

				$ljsc->new_value( 'post_id', $post->ID );
				$ljsc->new_value( 'post_live_status', $is_live );
				$pf = LivePress_PF_Updates::get_instance();
				if ( ! $pf->near_uuid ) {
					$pf->cache_pieces( $post );
				}
				$ljsc->new_value( 'post_update_msg_id', $pf->near_uuid );
			}

			$author = '';
			$user   = wp_get_current_user();
			if ( $user->ID ) {
				if ( empty( $user->display_name ) ) {
					$author = $user->user_login;
				} else {
					$author = $user->display_name;
				}
			}

			$ljsc->new_value( 'current_user', $author );

			if ( is_admin() ) {
				// Is post_from turned on
				if ( $this->user_options['remote_post'] ) {
					$ljsc->new_value( 'remote_post', true, Livepress_Configuration_Item::$BOOLEAN );
				} else {
					$ljsc->new_value( 'remote_post', false, Livepress_Configuration_Item::$BOOLEAN );
				}

				$ljsc->new_value( 'PostMetainfo', '', Livepress_Configuration_Item::$BLOCK );
				// Set if the live updates should display the timestamp
				$timestamp = isset( $this->options['timestamp'] ) ? $this->options['timestamp'] : false;
				if ( $timestamp ) {
					$template = LivePress_Live_Update::timestamp_template();
					$ljsc->new_value( 'timestamp_template', $template );
				}

				// Set url for the avatar
				$include_avatar = isset( $this->options['include_avatar'] ) ? $this->options['include_avatar'] : false;
				$ljsc->new_value( 'has_avatar', $include_avatar, Livepress_Configuration_Item::$BOOLEAN );

				// Set the author name
				$update_author = isset( $this->options['update_author'] ) ? $this->options['update_author'] : false;
				if ( $update_author ) {
					$use_default_author = apply_filters( 'livepress_use_default_author', true );
					$ljsc->new_value( 'use_default_author', $use_default_author );
					$author_display_name = $use_default_author ? LivePress_Live_Update::get_author_display_name( $this->options ) : '';
					$ljsc->new_value( 'author_display_name', $author_display_name );
					$user = wp_get_current_user();
					$author_id = ( isset( $user->ID ) ) ? $user->ID : 0;
					$ljsc->new_value( 'author_id', ( $use_default_author ) ? $author_id : '' );
				}
				// The last attribute shouldn't have a comma
				// Set where the live updates should be inserted (top|bottom)
				$feed_order = isset( $this->options['feed_order'] ) ? $this->options['feed_order'] : false;
				$ljsc->new_value( 'placement_of_updates', $feed_order );
				$ljsc->new_value( "PostMetainfo", "", Livepress_Configuration_Item::$ENDBLOCK );

				$ljsc->new_value( 'allowed_chars_on_post_update_id',
				implode( LivePress_Post::$ALLOWED_CHARS_ON_ID ) );
				$ljsc->new_value( 'num_chars_on_post_update_id',
					LivePress_Post::$NUM_CHARS_ID,
				Livepress_Configuration_Item::$LITERAL );
			}
		}

		$ljsc->new_value( 'site_url', site_url() );
		$ljsc->new_value( 'ajax_url', site_url().'/wp-admin/admin-ajax.php' );
		$ljsc->new_value( 'locale', get_locale() );

		if ( function_exists( 'wpcom_vip_noncdn_uri' ) ){
			$ljsc->new_value( 'noncdn_url', wpcom_vip_noncdn_uri( dirname( dirname( __FILE__ ) ) ) );
		}else {
			$ljsc->new_value( 'noncdn_url', LP_PLUGIN_URL );
		}

		// Check if we have the Facebook embed plugin so we have to load
		// the FB js on livepress.admin.js:
		if ( class_exists( 'Facebook_Loader' ) ){
			$ljsc->new_value( 'facebook', 'yes' );
		}

		$settings = get_option( 'livepress' );
		if ( isset( $settings['update_format'] ) ){
			$ljsc->new_value( 'timestamp_format', $settings['timestamp_format'] );
		}

		// Check for Facebook App ID for sharing UI:
		if ( isset( $settings['facebook_app_id'] ) ) {
			$ljsc->new_value( 'facebook_app_id', $settings['facebook_app_id'] );
		}

		if ( isset( $settings['sharing_ui']) ) {
			$ljsc->new_value( 'sharing_ui', $settings['sharing_ui'] );
		}
		if ( isset( $post->ID ) ) {
			$ljsc->new_value( 'post_url', get_permalink( $post->ID ) );
		}

		// Localize `LivepressConfig` in admin and on front end live posts
		if ( is_admin() ) {
			// Add the update_formatting
			if ( isset( $settings['update_format'] ) ){
				$ljsc->new_value( 'update_format', $settings['update_format'] );
			}
			if ( isset( $settings['show'] ) ){
				$ljsc->new_value( 'show', $settings['show'] );
			}

			// Add existing livetags
			$live_update_tags = get_terms(
				array( 'livetags' ),
				array(
					'hide_empty' => false,
				)
			);
			if ( ! empty( $live_update_tags ) && ! is_wp_error( $live_update_tags ) ) {
				$update_tags = wp_list_pluck( $live_update_tags, 'name' );
			} else {
				$update_tags = '';
			}
			$ljsc->new_value( 'live_update_tags', $update_tags );
			$ljsc->new_value( 'current_screen', get_current_screen() );
			wp_localize_script( 'lp-admin', 'LivepressConfig', $ljsc->get_values() );
		} else {
			wp_localize_script( 'livepress-plugin-loader', 'LivepressConfig', $ljsc->get_values() );
		}

		$ljsc->flush();
	}

	/**
	 * Adds the modified tinyMCE for collaborative editing and livepress.
	 *
	 * @param array $plugin_array Contains the enabled plugins.
	 * @return array The plugin_array with the modified tinyMCE added.
	 */
	public function add_modified_tinymce( $plugin_array ) {
		if ( $this->livepress_config->debug() ) {
			$plugin_array['livepress'] = LP_PLUGIN_URL . 'js/editor_plugin.js?rnd='.rand();
		} else {
			if ( $this->livepress_config->script_debug() ) {
				$plugin_array['livepress'] = LP_PLUGIN_URL . 'js/editor_plugin_release.full.js?v='.LP_PLUGIN_VERSION;
			} else {
				$plugin_array['livepress'] = LP_PLUGIN_URL . 'js/editor_plugin_release.min.js?v='.LP_PLUGIN_VERSION;
			}
		}
		return $plugin_array;
	}

	/**
	 * Initialize live updates.
	 *
	 * @return LivePress_Live_Update
	 */
	function init_live_update() {
		$live_update = LivePress_Live_Update::instance();
		if ( isset( $this->custom_author_name ) ) {
			$live_update->use_custom_author_name( $this->custom_author_name );
		}
		if ( isset($this->custom_timestamp) ) {
			$live_update->use_custom_timestamp( $this->custom_timestamp );
		}
		if ( isset($this->custom_avatar_url) ) {
			$live_update->use_custom_avatar_url( $this->custom_avatar_url );
		}
		return $live_update;
	}

	var $inject = false;

	/**
	 * Inject widget.
	 *
	 * @param $do_inject
	 */
	function inject_widget( $do_inject ) {
		$this->inject = $do_inject;
	}

	/**
	 * Add a div tag surrounding the post text so oortle dom manipulator
	 * can find where it should do the changes.
	 *
	 * @param string $content           Content.
	 * @param mixed  $post_modified_gmt Post modified GMT.
	 */
	function add_global_post_content_tag( $content, $post_modified_gmt = null, $livetags = array() ) {
		global $post;
		$div_id = null;
		if ( is_page() || is_single() ) {
			$div_id = 'post_content_livepress';
		}

		if ( is_home() ) {
			$div_id = 'post_content_livepress_' . $post->ID;
		}

		// Add the live tag data to the div
		$live_tag_data = '';
		if ( ! empty( $livetags ) ) {
			foreach ( $livetags as $a_tag ) {
				$live_tag_data .= $a_tag . ',';
			}
		}

		if ( $div_id ) {
			$content = '<div id="' . esc_attr( $div_id ) . '" class="livepress_content"  data-livetags="' . trim( $live_tag_data, ',' ) . '">'.$content.'</div>';
		}

		if ( $this->inject ) {
			if ( $post_modified_gmt === null ) {
				$post_modified_gmt = $post->post_modified_gmt;
			}

			$modified = new DateTime( $post_modified_gmt, new DateTimeZone( 'UTC' ) );

			if ( true &&  method_exists( $modified, 'diff' ) ){
				$since    = $modified->diff( new DateTime() );

				// If an update is more than an hour old, we don't care how old it is ... it's out-of-date.
				// Calculate number of seconds that have elapsed
				$last_update  = $since->days * 24 * 60 * 60; // Days in seconds
				$last_update += $since->h * 60 * 60;   // Hours in seconds
				$last_update += $since->i * 60;        // Minutes in seconds
				$last_update += $since->s;             // Seconds

			} else {

				$modified = new DateTime( $post_modified_gmt, new DateTimeZone( 'UTC' ) );;
				$now      = new DateTime();
				$since    = abs( $modified->format( 'U' ) - $now->format( 'U' ) );

				// If an update is more than an hour old, we don't care how old it is ... it's out-of-date.
				$last_update = $since;
			}

			$content = LivePress_Themes_Helper::instance()->inject_widget( $content, $last_update );
		}

		return $content;
	}

	/**
	 * Set a custom author name to be used instead of the current author name.
	 *
	 * @param string $name The custom author name
	 */
	public function set_custom_author_name( $name ) {
		$this->custom_author_name = $name;
		$this->init_live_update();
	}

	/**
	 * Set a custom timestamp to be used instead of the current time.
	 *
	 * @param string $time The custom time.
	 */
	public function set_custom_timestamp( $time ) {
		$this->custom_timestamp = $time;
		$this->init_live_update();
	}

	/**
	 * Set a custom avatar url to be used instead of saved avatar image
	 * that's currently used in the autotweet feature.
	 *
	 * @param string $avatar_url The avatar url.
	 */
	public function set_custom_avatar_url( $avatar_url ) {
		$this->custom_avatar_url = $avatar_url;
		$this->init_live_update();
	}

	/**
	 * Save old post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_old_post( $post_id ) {
		$this->old_post = get_post( $post_id );
	}

	/**
	 * Push out updates to all subscribers.
	 *
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @param WP_Post $post WP_Post object.
	 */
	public function send_to_livepress_new_post( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || ! in_array( $post->post_type, apply_filters( 'livepress_post_types', array( 'post' ) ) ) || 0 !== $post->post_parent ) {
			return;
		}

		$alert_sent = get_post_meta( $post->ID, 'livepress_alert_sent', true );
		if ( 'yes' === $alert_sent ) {
			return;
		}

		$then = strtotime( $post->post_date_gmt . ' +0000' );

		// check if post has been posted less than 10 seconds ago. If so, let's send the warn
		if ( time() - $then < 10 ) {
			$this->livepress_publish_post( $post );

			update_post_meta( $post->ID, 'livepress_alert_sent', 'yes' );
		}
	}

	/**
	 * Get current post feed link.
	 *
	 * @return array|string
	 */
	public function get_current_post_feed_link() {
		global $post;
		return LivePress_WP_Utils::get_from_post( $post->ID, 'feed_link' );
	}

	/**
	 * Get current post updates count.
	 *
	 * @return int
	 */
	public function current_post_updates_count() {
		global $post;
		$lp_post = new LivePress_Post( $post->post_content, $post->ID );
		return $lp_post->get_updates_count();
	}

	/**
	 * Gets the comments count of the current post.
	 *
	 * @return integer
	 */
	public function current_post_comments_count() {
		global $post;
		return $post->comment_count;
	}

	/**
	 * Publish a LivePress update.
	 *
	 * @param WP_Post $post WP_Post object.
	 */
	public function livepress_publish_post( $post ) {
		$permalink = get_permalink( $post->ID );
		$data_to_js = array(
			'title'       => $post->post_title,
			'author'      => get_the_author_meta( 'login', $post->post_author ),
			'updated_at_gmt' => $post->post_modified_gmt . 'Z', //Z stands for utc/gmt
			'link'        => $permalink,
		);
		$params = array(
			'post_id'     => $post->ID,
			'post_title'  => $post->post_title,
			'post_link'   => $permalink,
			'data'        => JSON_encode( $data_to_js ),
			'blog_public' => get_option( 'blog_public' ),
		);

		try {
			$return = $this->livepress_communication->send_to_livepress_new_post( $params );
			update_option( LP_PLUGIN_NAME.'_new_post', $return['oortle_msg'] );
			LivePress_WP_Utils::save_on_post( $post->ID, 'feed_link', $return['feed_link'] );
		} catch (LivePress_Communication_Exception $e) {
			$e->log( 'new post' );
		}
	}

	/**
	 * Delete from the WP DB the matching value.
	 *
	 * @access private
	 *
	 * @param int    $post_id The ID of the post from which you will delete a field.
	 * @param string $key     The key of the field you will delete.
	 * @param string $value   Optional. The value of the field you will delete.
	 *                        This is used to differentiate between several fields
	 *                        with the same key. If left blank, all fields with
	 *                        the given key will be deleted.
	 * @return string The value.
	 */
	private function delete_from_post($post_id, $key, $value = null) {
		return delete_post_meta( $post_id, '_'.LP_PLUGIN_NAME.'_'. $key, $value );
	}

}
