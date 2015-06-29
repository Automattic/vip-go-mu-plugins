<?php
/**
 * Handles the wordpress options of the plugin
 * and creates the menus.
 *
 * @package Livepress
 */

/**
 * This file has all the code that does the
 * communication with the livepress service.
 */
require_once ( LP_PLUGIN_PATH . 'php/livepress-communication.php' );

class LivePress_Administration {

	/**
	 * @static
	 * @access public
	 * @var string $options_name Options name.
	 */
	public static $options_name = 'livepress';

	/**
	 * @access public
	 * @var array $bool_options Array of boolean options.
	 */
	var $bool_options = array(
		'timestamp'                    => true,
		'timestamp_24'                 => true,
		'update_author'                => true,
		'include_avatar'               => false,
		'post_to_twitter'              => false,
		'error_api_key'                => true,
		'comment_live_updates_default' => true,
		'sounds_default'               => true,
		'disable_comments'             => false,
	);

	/**
	 * @access public
	 * @var array $string_options Array of string options.
	 */
	var $string_options = array(
		'feed_order'                 => 'top', // bottom - top
		'author_display'             => 'default', // default - custom
		'author_display_custom_name' => '',
		'api_key'                    => '',
		'oauth_authorized_user'      => '',
		'blog_shortname'             => '',
		'enabled_to'                 => 'all', // all - registered - administrators - none,
	);

	/**
	 * Default empty array value for notification options, otherwise initial
	 * checks throws PHP errors.
	 *
	 * @access public
	 * @var array $array_options Array of array options.
	 */
	var $array_options = array(
		'notifications' => array(),
	);

	/**
	 * @access public
	 * @var array $user_bool_options Array of user boolean options.
	 */
	var $user_bool_options   = array();

	/**
	 * @access public
	 * @var array $user_string_options Array of user string options.
	 */
	var $user_string_options = array(
		'post_from_twitter_username' => '',
		'twitter_avatar_username'    => '',
		'twitter_avatar_url'         => '',
		'avatar_display'             => 'wp', // wp - twitter
	);

	/**
	 * Internal copy of options.
	 *
	 * All variables that don't go to the form needs to be here to hold
	 * their value between saves.
	 *
	 * @access public
	 * @var array $internal_options Array of internal options.
	 */
	var $internal_options = array( 'error_api_key', 'oauth_authorized_user' );

	/**
	 * @access public
	 * @var array $messages Associative array of 'updated' and 'error' messages.
	 */
	public $messages = array(
		'updated' => array(),
		'error'   => array(),
	);

	/**
	 * @access public
	 * @var null $options
	 */
	var $options;

	/**
	 * @access public
	 * @var null $user_options
	 */
	var $user_options;

	/**
	 * @access public
	 * @var null $old_options
	 */
	var $old_options;

	/**
	 * @access public
	 * @var null $old_user_options
	 */
	var $old_user_options;

	/**
	 * @static
	 * @access private
	 * @var string $admin_link_name Admin link name.
	 */
	private static $admin_link_name = 'livepress';

	/**
	 * Constructor.
	 */
	function __construct() {
		// Add admin message on plugins page when API key not present
		$this->add_admin_messages();

		add_action( 'load-post-new.php', array( $this, 'load_post' ) );
		add_action( 'load-post.php',     array( $this, 'load_post' ) );

	}

	/**
	 * Load nonces in the admin footer for the post.
	 */
	function load_post() {
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
	}

	/**
	 * Add a notification on plugins page if the API key is blank.
	 *
	 * Informs users they need to sign up for a LivePress API key
	 */
	function add_admin_messages() {
		$this->options = get_option( self::$options_name );

		if ( empty( $this->options['api_key'] ) && ! isset( $_POST[ 'submit' ] ) && ! function_exists( 'livepress_api_key_missing_warning' ) ) {

			/**
			 * Warning message for missing API key.
			 */
			function livepress_api_key_missing_warning() {
				global $hook_suffix, $current_user;

				if ( $hook_suffix == 'plugins.php' ) {
					echo '
					<div class="updated" style="padding: 0; margin: 0; border: none; background: none;">
						<form name="livepress_admin_warning" action="' . esc_url( add_query_arg( array( 'page' => 'livepress-settings' ), admin_url( 'options-general.php' ) ) ) . '" method="POST" >
							<input type="hidden" name="return" value="1"/>
							<input type="hidden" name="user" value="' . esc_attr( $current_user->user_login ).'"/>
							<div class="livepress_admin_warning">
								<div class="aa_button_container" onclick="document.livepress_admin_warning.submit();">
									<div class="aa_button_border">
										<div class="aa_button">'. esc_html__( 'Activate your LivePress account' ).'</div>
									</div>
								</div>
								<div class="aa_description">'. esc_html__( 'Almost done - activate your account to go live with LivePress', 'livepress' ) . '</div>
							</div>
						</form>
					</div>
					';
				}
			}

			// Hook into the admin_notices section to display the notice
			add_action( 'admin_notices', 'livepress_api_key_missing_warning' );
			return;
		}
	}

	/**
	 * Post admin footer nonces. Added on post edit screen.
	 */
	function admin_footer() {
		global $post;
		$change        = wp_create_nonce( 'livepress-change_post_update-'   . $post->ID );
		$append        = wp_create_nonce( 'livepress-append_post_update-'   . $post->ID );
		$delete        = wp_create_nonce( 'livepress-delete_post_update-'   . $post->ID );
		$merge_noonce  = wp_create_nonce( 'livepress-merge_post-'           . $post->ID );
		$live_notes    = wp_create_nonce( 'livepress-update_live-notes-'    . $post->ID );
		$live_comments = wp_create_nonce( 'livepress-update_live-comments-' . $post->ID );
		$live_toggle   = wp_create_nonce( 'livepress-update_live-status-'   . $post->ID );
		$lp_status     = wp_create_nonce( 'lp_status' );

		printf( '<span id="livepress-nonces" style="display:none" data-change-nonce="%s" data-append-nonce="%s" data-delete-nonce="%s"></span>', $change, $append, $delete );
		printf( '<span id="blogging-tool-nonces" style="display:none" data-live-notes="%s" data-live-comments="%s" data-live-status="%s" data-merge-post="%s" data-lp-status="%s" ></span>', $live_notes, $live_comments, $live_toggle, $merge_noonce, $lp_status );
	}

	/**
	 * Remote post toggle.
	 *
	 * @param int    $user_id          The user ID.
	 * @param string $lp_user_password The user's password, for VIP only
	 * @return bool                    Whether to enable remote posting.
	 */
	public function enable_remote_post($user_id, $lp_user_password = '' ) {
		$user          = get_userdata( $user_id );
		$this->options = get_option( self::$options_name );
		$livepress_com = new LivePress_Communication( $this->options['api_key'] );
		// Use token for non VIP
		if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || false === WPCOM_IS_VIP_ENV ) {
			$user_pass     = wp_generate_password( 20, false );
			$lp_key        = wp_hash_password( $user_pass );
			update_user_meta( $user_id, 'livepress-access-key-new', $lp_key );
			/* Avoid race condition by enabling two passwords at that point */
			$return_code = $livepress_com->create_blog_user( $user->user_login, $user_pass );
			if ( $return_code != 200 ) {
				$this->add_error( esc_html__( "Can't enable remote post feature, so the remote post updates for this user will not work.", 'livepress' ) );
				$res = false;
			} else {
				update_user_meta( $user_id, 'livepress-access-key', $lp_key );
				$res = true;
			}
			delete_user_meta( $user_id, 'livepress-access-key-new' );
		} else {
			// In VIP use actual password, don't store locally at all
			$return_code = $livepress_com->create_blog_user( $user->user_login, $lp_user_password );
			$blogging_tools = new LivePress_Blogging_Tools();
			if ( $return_code != 200 ) {
				$this->add_error( esc_html__( "Can't enable remote post feature, so the remote post updates for this user will not work.", 'livepress' ) );
				$res = false;
			} else {
				$res = true;
			}
			$blogging_tools->set_have_user_pass( $user_id, $res );
		}

		return $res;
	}

	/**
	 * Twitter Avatar URL.
	 *
	 * @static
	 * @return mixed
	 */
	public static function twitter_avatar_url() {
		global $current_user;
		$options = get_user_option( self::$options_name, $current_user->ID, false );
		return $options['twitter_avatar_url'];
	}

	/**
	 * Merge default values.
	 */
	function merge_default_values() {
		$options = get_option( self::$options_name, array() );
		$this->options = array_merge( $this->bool_options, $this->string_options, $this->array_options, $options );
		update_option( self::$options_name, $this->options );
	}

	/**
	 * Initialize the instance.
	 *
	 * @static
	 */
	static function initialize() {
		$postUpdater = new LivePress_Administration();
	}

	/**
	 * Run on deactivation of plugin - merges children of all live posts
	 *
	 * @since 1.0.7
	 */
	public static function deactivate_livepress(){
		$lp_updater = LivePress_PF_Updates::get_instance();
		$blogging_tools = new LivePress_Blogging_Tools();
		$live_posts = $blogging_tools->get_all_live_posts();
		// Go thru each live post
		// code used in livepress_cli.php
		foreach ( $live_posts as $the_post ){
			// Merge children posts
			$lp_updater->merge_children( $the_post );
			// Turn off live
			$blogging_tools->set_post_live_status( $the_post, false );

		}

	}

	/**
	 * Upgrade live status system.
	 *
	 * @static
	 */
	static function plugin_install() {
		$blogging_tools = new LivePress_Blogging_Tools();
		$blogging_tools->upgrade_live_status_system();

		// set default value of sharingUI if not set
		$livepress = get_option( self::$options_name );
		if ( ! array_key_exists( 'sharing_ui', $livepress ) ){
			$livepress['sharing_ui'] = 'display';
			update_option( self::$options_name, $livepress );
		}
		if ( ! array_key_exists( 'show', $livepress ) ){
			$livepress['show'] = array( 'AUTHOR', 'TIME', 'HEADER' );
			update_option( self::$options_name, $livepress );
		}

	}

	/**
	 * Install/upgrade required tables
	 *
	 * @todo Remove this. The new table(s) functionality has already been removed.
	 *
	 * @static
	 */
	static function install_or_upgrade() {
		$options = get_option( self::$options_name );
		if ( get_option( self::$options_name . '_version' ) != LP_PLUGIN_VERSION ) {
			Collaboration::install();
			$postUpdater = new LivePress_Administration();
			$postUpdater->merge_default_values();
			update_option( self::$options_name . '_version', LP_PLUGIN_VERSION );
		}
	}

	/**
	 * Enqueue scripts and styles.
	 */
	function add_css_and_js() {
		if ( LivePress_Config::get_instance()->script_debug() ) {
			wp_enqueue_script( 'livepress_admin_ui_js', LP_PLUGIN_URL . 'js/admin_ui.full.js', array( 'jquery' ) );
		} else {
			wp_enqueue_script( 'livepress_admin_ui_js', LP_PLUGIN_URL . 'js/admin_ui.min.js', array( 'jquery' ) );
		}

		wp_register_style( 'livepress_facebox', LP_PLUGIN_URL . 'css/facebox.css' );
		wp_register_style( 'livepress_admin', LP_PLUGIN_URL . 'css/admin.css' );
		wp_print_styles( 'livepress_facebox' );
		wp_print_styles( 'livepress_admin' );
	}

	/**
	 * LivePress updates.
	 *
	 * @access private
	 *
	 * @param $author_view
	 * @return mixed
	 */
	private function livepress_updates( $author_view ) {
		global $current_user;
		// Call the livepress API to add/del/enable/disable twitter, IM users.
		$livepress_com = new LivePress_Communication( $this->options['api_key'] );

		if ( $this->has_changed( 'api_key' ) ) {
			$domains = array();
			// Add domain mapping primary domain
			if ( function_exists( 'domain_mapping_siteurl' ) ) {
				$domain_mapping_siteurl = domain_mapping_siteurl();
				$domains[ 'alias[]' ] = $domain_mapping_siteurl;
			}

			$home_url = get_home_url(); // Mapped domain on VIP
			$domains[ 'alias[]' ] = $home_url;

			$validation = $livepress_com->validate_on_livepress( $domains );
			$api_key = $this->options['api_key'];
			$this->options = $this->old_options;
			$this->options['api_key'] = $api_key;
			$this->options['error_api_key'] = ($validation != 1);
			if ( $validation == 1 ) {
				// We pass validation, update blog parameters from LP side
				$blog = $livepress_com->get_blog();
				$this->options['blog_shortname'] = $blog->shortname;
				$this->options['post_from_twitter_username'] = $blog->twitter_username;
			}
			return; // If only api_key changed, no more changes possible -- that are separate forms in html
		}

		if ( $this->has_changed( 'twitter_avatar_username', true ) ) {
			if ( empty($this->user_options['twitter_avatar_username']) ) {
				$this->user_options['twitter_avatar_url'] = '';
			} else {
				$url = $livepress_com->get_twitter_avatar( $this->user_options['twitter_avatar_username'] );
				if ( empty($url) ) {
					$error_message  = esc_html__( 'Failed to get avatar for ', 'livepress' );
					$error_message .= $this->user_options['twitter_avatar_username'];
					$error_message .= '.';
					$this->add_error( $error_message );
					$this->user_options['twitter_avatar_username'] = $this->old_user_options['twitter_avatar_username'];
				} else {
					$this->user_options['twitter_avatar_url'] = $url;
				}
			}
		}

		$this->update_im_bots( $livepress_com );
		if ( ! $author_view ) {
			$this->update_post_to_twitter( $livepress_com );
		}

		$return_code = 200;
	}

	/*
	 * Receives an ajax request to validate the api key
	 * on the livepress webservice
	 */

	/**
	 * Validate the user's API key both with the LivePress webservice
	 * and the plugin update service.
	 *
	 * @return string
	 */
	public static function api_key_validate() {
		self::die_if_not_allowed();
		check_ajax_referer( 'livepress_api_validate_nonce' );

		$api_key   = esc_html( stripslashes( $_GET['api_key'] ) );

		$domains = array();
		// Add domain mapping primary domain
		if ( function_exists( 'domain_mapping_siteurl' ) ) {
			$domain_mapping_siteurl = domain_mapping_siteurl();
			$domains[ 'alias[]' ] = $domain_mapping_siteurl;
		}

		$home_url = get_home_url(); // Mapped domain on VIP
		$domains[ 'alias[]' ] = $home_url;

		// Validate with the LivePress webservice
		$livepress_communication = new LivePress_Communication( $api_key );
		$status = $livepress_communication->validate_on_livepress( $domains );

		$options = get_option( self::$options_name );
		$options['api_key'] = ( $api_key );
		$options['error_api_key'] = ( 1 != $status && 2 != $status );
		if ( $status == 1 ) {
			// We pass validation, update blog parameters from LP side
			$blog = $livepress_communication->get_blog();
			$options['blog_shortname'] = $blog->shortname;
			$options['post_from_twitter_username'] = $blog->twitter_username;
		}
		update_option( self::$options_name, $options );

		if ( false == $options['error_api_key'] ) {
			// Validate with plugin update service
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $api_key,
				'item_name'  => urlencode( LP_ITEM_NAME )
			);
			if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
				$response = vip_safe_wp_remote_get(
					add_query_arg( $api_params, LP_STORE_URL ),
					'',    /* fallback value */
					5,     /* threshold */
					10,     /* timeout */
					20,    /* retry */
					array( 'reject_unsafe_urls' => false )
				);
			} else {
				$response = wp_remote_get( add_query_arg( $api_params, LP_STORE_URL ), array( 'reject_unsafe_urls' => false ) );
			}
			if ( is_wp_error( $response ) ) {
				die( 'Ouch' );
			}
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			update_option( 'livepress_license_status', $license_data->license );
		}

		if ( 2 == $status || 1 == $status || 0 == $status ) {
			header( 'Content-Type: application/json' );
			die( json_encode( $options ) );
		} else {
			die( 'Ouch' );
		}
	}

	/**
	 * Get Twitter avatar.
	 *
	 * @static
	 */
	public static function get_twitter_avatar() {
		$livepress_com = new LivePress_Communication();
		$url = $livepress_com->get_twitter_avatar( esc_html( $_GET['username'] ) );
		if ( empty($url) ) {
			header( 'HTTP/1.1 403 Forbidden' );
			die();
		} else {
			die($url);
		}
	}

	/**
	 * Receive an ajax request to enable/disable the post to twitter feature,
	 * then dies returning the url for OAuth validation.
	 *
	 * @static
	 */
	public static function post_to_twitter_ajaxed() {
		self::die_if_not_allowed();
		check_ajax_referer( 'lp_post_to_twitter_nonce' );
		$options = get_option( self::$options_name );

		if ( ! isset($_POST['change_oauth_user'])
			&& (isset($_POST['enable']) && $options['post_to_twitter']
				|| ! isset($_POST['enable']) && ! $options['post_to_twitter'])
		) {
			// Requesting to enable when it's already enabled and vice-versa
			header( 'HTTP/1.1 409 Conflict' );
			die();
		}

		$livepress_com = new LivePress_Communication( $options['api_key'] );

		if ( isset($_POST['enable']) ) {
			if ( isset($_POST['change_oauth_user']) ) {
				$livepress_com->destroy_authorized_twitter_user();
				$options['oauth_authorized_user'] = '';
			}
			try {
				$url = $livepress_com->get_oauth_authorization_url();
				$options['post_to_twitter'] = true;
			} catch ( LivePress_Communication_Exception $e ) {
				header( 'HTTP/1.1 502 Bad Gateway' );
				echo esc_html( $e->get_code() );
				die();
			}
		} else {
			$livepress_com->destroy_authorized_twitter_user();
			$options['post_to_twitter'] = false;
			$options['oauth_authorized_user'] = '';
		}

		update_option( self::$options_name, $options );
		die($url);
	}

	/**
	 * Get a paginated list of users for the byline selector
	 */
	public static function lp_get_authors() {

		$lp_author_transient_key = 'lp_author_list_plus_' . LP_PLUGIN_VERSION;
		if ( false === ( $return = get_transient( $lp_author_transient_key ) ) ) {
			$users     = get_users( array( 'number' => 100 ) );
			$names     = array();
			$gravatars = array();
			$links     = array();

			foreach ( $users as $user ) {
				array_push( $names, array(
					'id'   => $user->ID,
					'text' => $user->display_name,
				) );
				array_push( $gravatars, array(
					'id'           => $user->ID,
					'avatar'       => apply_filters( 'livepress_get_avatar', get_avatar( $user->ID ), $user )
				) );
				array_push( $links, array(
					'id'           => $user->ID,
					'link'         => apply_filters( 'livepress_get_avatar_link', get_author_posts_url( $user->ID ), $user )
				) );
			}
			$return = array(
				'names'     => $names,
				'gravatars' => $gravatars,
				'links'     => $links,
			);
			set_transient( $lp_author_transient_key, $return, 60 );
		}
		return apply_filters( 'lp_authors_return', $return );
	}

	/**
	 * Print to ajax the status of the last OAuth request.
	 *
	 * @static
	 */
	public static function check_oauth_authorization_status() {
		self::die_if_not_allowed();
		check_ajax_referer( 'lp_check_oauth_nonce' );
		$options = get_option( self::$options_name );

		$livepress_com = new LivePress_Communication( $options['api_key'] );
		$status = $livepress_com->is_authorized_oauth();

		if ( $status->status == 'authorized' ) {
			$options['post_to_twitter'] = true;
			$options['oauth_authorized_user'] = $status->username;
			update_option( self::$options_name, $options );
		} else if ( $status->status == 'unauthorized' ) {
			$options['post_to_twitter'] = false;
			$options['oauth_authorized_user'] = '';
			update_option( self::$options_name, $options );
		}

		header( 'Content-Type: application/json' );
		echo json_encode( $status );
		die();
	}

	/**
	 * Die if not allowed.
	 *
	 * @static
	 * @access private
	 */
	private static function die_if_not_allowed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			die();
		}
	}

	/**
	 * Add an error.
	 *
	 * @access private
	 *
	 * @param string $msg Error message.
	 */
	private function add_error( $msg ) {
		$this->messages['error'][] = $msg;
	}

	/**
	 * Add a warning
	 *
	 * @access private
	 *
	 * @param string $msg Warning message.
	 */
	private function add_warning( $msg ) {
		$this->messages['updated'][] = $msg;
	}

	// Functions that handle the changes
	/**
	 * Update post to Twitter.
	 *
	 * @access private
	 *
	 * @param string $livepress_com
	 */
	private function update_post_to_twitter( $livepress_com ) {
		$option = 'post_to_twitter';

		if ( $this->has_turned_on( $option ) ) {
			$this->add_warning( $this->get_oauth_authorization_url_message( $livepress_com ) );
		} elseif ( $this->has_turned_off( $option ) ) {
			$livepress_com->destroy_authorized_twitter_user();
			$this->options['oauth_authorized_user'] = '';
		}
	}

	/**
	 * Get oAuth authorization URL message.
	 *
	 * @access private
	 *
	 * @param string $livepress_com
	 * @return string
	 */
	private function get_oauth_authorization_url_message( $livepress_com ) {
		$auth_url = $livepress_com->get_oauth_authorization_url();
		$msg      = esc_html__( 'To enable the "Post to twitter" you still need to ', 'livepress' );
		$msg     .= esc_html__( 'authorize the livepress webservice to post in your ', 'livepress' );
		$msg     .= esc_html__( 'twitter account, to do it please click on the following ', 'livepress' );
		$msg     .= esc_html__( 'link and then on "Allow"', 'livepress' );
		$msg     .= '<br /><a href="' . $auth_url . '" ';
		$msg     .= 'id="lp-post-to-twitter-not-authorized" ';
		$msg     .= 'target="_blank">';
		$msg     .= $auth_url . '</a>';
		return $msg;
	}

	/**
	 * Whether an option has changed.
	 *
	 * @access private
	 *
	 * @param      $option
	 * @param bool $user
	 * @return bool
	 */
	private function has_changed( $option, $user = false ) {
		if ( $user ) {
			return $this->old_user_options[$option]
				!= $this->user_options[$option];
		} else {
			return $this->old_options[$option] != $this->options[$option];
		}
	}

	/**
	 * Whether an option is turned on.
	 *
	 * @access private
	 *
	 * @param      $option
	 * @param bool $user
	 * @return bool
	 */
	private function has_turned_on( $option, $user = false ) {
		if ( $user ) {
			return ! $this->old_user_options[$option]
				&& $this->user_options[$option];
		} else {
			return ! $this->old_options[$option] && $this->options[$option];
		}
	}

	/**
	 * Whether an option is turned off.
	 *
	 * @access private
	 *
	 * @param      $option
	 * @param bool $user
	 * @return bool
	 */
	private function has_turned_off( $option, $user = false ) {
		if ( $user ) {
			return $this->old_user_options[$option]
				&& ! $this->user_options[$option];
		} else {
			return $this->old_options[$option] && ! $this->options[$option];
		}
	}

}
