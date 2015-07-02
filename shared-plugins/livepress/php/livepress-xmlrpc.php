<?php
/*
 * Livepress extender to XMLRPC protocol.
 * This class initizalises only for XMLRPC requests
 *
 */

class LivePress_XMLRPC {

	/**
	 * Instance.
	 *
	 * @static
	 * @access private
	 *
	 * @var LivePress_XMLRPC
	 */
	static private $lp_xmlrpc = null;

	/**
	 * Instance.
	 *
	 * @static
	 */
	static function initialize() {
		if ( self::$lp_xmlrpc == null ) {
			self::$lp_xmlrpc = new LivePress_XMLRPC();
		}
	}

	/**
	 * Constructor.
	 *
	 * @access private
	 */
	private function __construct() {
		add_filter( 'xmlrpc_methods',             array( $this, 'add_livepress_functions_to_xmlrpc' ) );
		add_filter( 'pre_option_enable_xmlrpc',   array( $this, 'livepress_enable_xmlrpc' ), 0, 1 );
		add_filter( 'wp_insert_post_data',        array( $this, 'insert_post_data' ), 10, 2 );
		add_filter( 'xmlrpc_wp_insert_post_data', array( $this, 'insert_post_data' ), 10, 2 );
	}

	/**
	 * Scrape hooks.
	 *
	 * @static
	 */
	public static function scrape_hooks() {
		add_filter( 'comment_flood_filter', '__return_false' );
		add_filter( 'xmlrpc_allow_anonymous_comments', '__return_true' );
	}

	/**
	 * Add LivePress XML-RPC methods.
	 *
	 * @param array $methods XML-RPC methods.
	 * @return array Filtered array of XML-RPC methods.
	 */
	function add_livepress_functions_to_xmlrpc( $methods ) {
		// unfortunatelly, it seems passed function names must relate to global ones
		$methods['livepress.appendToPost']      = 'livepress_append_to_post';
		$methods['livepress.setPostLiveStatus'] = 'livepress_set_post_live_status';
		$methods['livepress.newLivePost']       = 'livepress_new_live_post';

		return $methods;
	}


	var $livepress_authenticated = false;
	var $livepress_auth_called   = false;

	/**
	 * When xml-rpc server active, short-circuit enable_xmlrpc option
	 * and apply original later.
	 *
	 * @param bool $v
	 *
	 * @return bool
	 */
	function livepress_enable_xmlrpc( $v ) {
		$val = ! ( $this->livepress_auth_called || $this->livepress_authenticated );

		return $val || (bool) $v;
	}

	/**
	 * Short circut the update mechanism.
	 *
	 * Logic branches
	 * - If this is a new post, do nothing.
	 * - If this is a change to an existing post, do nothing.
	 * - If this is an addition to an existing post, remove the addition and store it as
	 *   a LivePress update instead.
	 *
	 * @param array $post_data
	 * @param array $content_struct
	 */
	function insert_post_data( $post_data, $content_struct ) {
		// Only fire on XML-RPC requests
		if ( ! defined( 'XMLRPC_REQUEST' ) || false == XMLRPC_REQUEST || ( defined( 'LP_FILTERED' ) && true == LP_FILTERED ) ) {
			return $post_data;
		}

		define( 'LP_FILTERED', true );

		$defaults = array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'post_author' => 0,
			'post_password' => '',
			'post_excerpt' => '',
			'post_content' => '',
			'post_title' => ''
		);

		$unparsed_data = wp_parse_args( $content_struct, $defaults );

		// If this isn't an update, exit early so we don't walk all over ourselves.
		if ( empty( $unparsed_data['ID'] ) ) {
			return $post_data;
		}

		// Get the post we're updating so we can compare the new content with the old content.
		$post_id = $unparsed_data['ID'];
		$post    = get_post( $post_id );

		$live_posts = get_option( 'livepress_live_posts', array() );
		$is_live = in_array( $post_id, $live_posts );

		if ( ! $is_live ){
			return $post_data;
		}

		$original_posts = $this->get_existing_updates( $post );
		$new_posts = $this->parse_updates( $unparsed_data['post_content'] );

		// First, add the new update if there is one
		if ( isset( $new_posts['new'] ) ) {
			if ( 1 === count( $new_posts ) ) {
				// Todo: If the only post sent up is "new" create a diff against the existing post so we can process the update.
				$clean_content = preg_replace( '/\<\!--livepress(.+)--\>/', '', $post->post_content );
				$new_posts['new']['content'] = str_replace( $clean_content, '', $new_posts['new']['content'] );
			}

			$update_id = LivePress_PF_Updates::get_instance()->add_update( $post, $new_posts['new']['content'] );

			// Remove the new post from the array so we don't double-process by mistake.
			unset( $new_posts['new'] );
		}

		// Second, update the content of any posts that have been changed.
		// You cannot *delete* an update via XMLRPC.  For that, you need to actually use the WordPress UI.
		foreach( $original_posts as $original_id => $original_post ) {
			// Skip the parent post
			if ( $post_id === $original_id ) {
				continue;
			}

			// Skip if no changes were passed in for this post
			if ( ! isset( $new_posts[ $original_id ] ) ) {
				continue;
			}

			$updated = $new_posts[ $original_id ];

			// Do we actually need to do an update?
			$md5 = md5( $updated['content'] );
			if ( $updated['md5'] === $md5 ) {
				continue;
			}

			$original_post['post_content'] = $updated['content'];

			wp_update_post( $original_post );
		}

		// Update post data for the parent post.
		if ( isset( $new_posts[ $post_id ] ) ) {
			$post_data['post_content'] = $new_posts[ $post_id ]['content'];
		} else {
			$post_data['post_content'] = $post->post_content;
		}

		return $post_data;
	}

	/**
	 * Get an array of the existing post updates, including the parent post.
	 *
	 * @param WP_Post $parent
	 *
	 * @return array
	 */
	private function get_existing_updates( $parent ) {
		$updates = array();
		$updates[ $parent->ID ] = $parent;

		// Set up child posts
		$children = get_children(
			array(
				'post_type'        => 'post',
				'post_parent'      => $parent->ID,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'numberposts'      => 1000,
				'suppress_filters' => false,
			)
		);

		foreach( $children as $child ) {
			$updates[ $child->ID ] = $child;
		}

		return $updates;
	}

	/**
	 * Parse the posts passed in as a string.
	 *
	 * Every discrete update is prefixed with an HTML comment containing an MD5 hash of the original content (for comparison)
	 * and the ID of the post that contains that content.  The hash can be used for a quick check to see if anything has changed.
	 *
	 * Example: <!--livepress md5=1234567890 id=3-->This is an update
	 *
	 * @param string $string Entire concatenated post, or just the
	 *
	 * @return array
	 */
	private function parse_updates( $string ) {
		$parsed = array();

		$split = preg_split( '/\<\!--livepress(.+)--\>/', $string, -1, PREG_SPLIT_DELIM_CAPTURE );

		for( $i = 0; $i < count( $split ); $i++ ) {
			$part = $split[ $i ];

			if ( '' === trim( $part ) ) {
				continue;
			}

			if ( strpos( trim( $part ), 'md5=' ) === 0 ) {
				$id = preg_match( '/md5=(?P<md5>\w+) id=(?P<id>\w+)/', trim( $part ), $matches );

				$parsed[ $matches['id'] ] = array(
					'md5'     => $matches['md5'],
					'content' => $split[ $i + 1]
				);

				$i++;
			} else {
				if ( isset( $parsed['new'] ) ) {
					continue;
				}

				// This content was not prefixed, so assume it was new.
				$parsed['new'] = array(
					'content' => $split[ $i ]
				);
			}
		}

		return $parsed;
	}
}


/**
 * XMLRPC authentication, lets Livepress server post on behalf of user.
 * Use token based authentication or built in authentication if in VIP
 *
 * @param mixed  $user
 * @param string $username
 * @param string $password
 *
 * @return WP_User
 */
function _livepress_authenticate( $username, $password ) {

	if ( empty( $password ) || empty( $password ) ) {
		return false;
	}

	$userdata = get_user_by( 'login', $username );
	if ( ! $userdata ) {
		return false;
	}

	// Use built in authentication on VIP - token based authentication disallowed
	if ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV ) {
		global $wp_xmlrpc_server;

		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) ) {
			return false;
		}

		// Authentication success!
		return $user;

	} else {
		// Use token based authentication
		$lp_pass = get_user_meta( $userdata->ID, 'livepress-access-key', true );

		$auth = false;

		if ( ! empty( $lp_pass ) && wp_check_password( $password, $lp_pass, $userdata->ID ) ) {
			$auth = true;
		}

		if ( ! $auth ) {
			$lp_pass = get_user_meta( $userdata->ID, 'livepress-access-key-new', true );
			if ( ! empty( $lp_pass ) && wp_check_password( $password, $lp_pass, $userdata->ID ) ) {
				$auth = true;
			}
		}
		if ( $auth ) {
			$user = new WP_User( $userdata->ID );
			return $user;
		} else {
			return false;
		}

	}
}


/**
 * Called by livepress webservice instead of editPost as we need to save
 * display_author (if passed) before the actual post save method so we know the
 * real author of update.
 * Also, we want to fetch post and append given content to it here, as doing it
 * in livepress webservice can cause race conditions.
 * This handles passed values, updates the arguments and passes them to
 * xmlrpc.php so it works as nothing happened.
 *
 * Handles custom_fields correclty
 *
 * @param array $args
 *
 * @return bool
 */
function livepress_append_to_post( $args ) {
	global $wp_xmlrpc_server, $wpdb;

	$wp_xmlrpc_server->escape( $args );
	$username       = esc_sql( $args[1] );
	$password       = esc_sql( $args[2] );
	$post_id        = (int) $args[0];
	$content_struct = $args[3];

	if ( ! $user = _livepress_authenticate( $username, $password ) ) {
		return $wp_xmlrpc_server->error;
	}

	// at this point the user is authenticated

	// Check that the user can edit the post
	if ( ! user_can( $user, 'edit_post', $post_id ) ) {
		return false;
	}

	// Verify that the post is live
	$blogging_tools = new LivePress_Blogging_Tools();
	$is_live = $blogging_tools->get_post_live_status( $post_id );
	if ( ! $is_live ){
		return false;
	}
	wp_set_current_user( $user->ID ); // Allow updates

	if ( isset( $content_struct['display_author'] ) ) {
		LivePress_Updater::instance()->set_custom_author_name( $content_struct['display_author'] );
	}

	if ( isset( $content_struct['created_at'] ) ) {
		LivePress_Updater::instance()->set_custom_timestamp( $content_struct['created_at'] );
	}

	if ( isset( $content_struct['avatar_url'] ) ) {
		LivePress_Updater::instance()->set_custom_avatar_url( $content_struct['avatar_url'] );
	}

	$plugin_options = get_option( LivePress_Administration::$options_name );

	// Adds metainfo shortcode
	$content_struct['description'] = "[livepress_metainfo] "
		. $content_struct['description'];


	$content_struct['ID'] = $args[0];
	$content_struct['post_content'] = $content_struct['description'];

	return wp_update_post( $content_struct );
}

/**
 * Called by livepress webservice to set the live status of a post.
 *
 * @param array $args {
 *		An array of arguments.
 *		@param  string     $username    Username for action.
 *		@param  string     $password    Livepress generated user access key.
 *		@param  string     $post_id     ID of the post to set the status of, -1 to check password only.
 *		@param  bool       $live_status True to turn live on, false to turn live off.
 *		@return int|Object 1 if password verified or status updated, false on error.
 * }
 */
function livepress_set_post_live_status( $args ) {
	global $wp_xmlrpc_server, $wpdb;

	$wp_xmlrpc_server->escape( $args );
	$username    = esc_sql( $args[0] );
	$password    = esc_sql( $args[1] );
	$post_id     = (int)  $args[2];
	$live_status = (bool) $args[3];

	// Verify user is authorized
	if ( ! $user = _livepress_authenticate( $username, $password ) ) {
		return false;
	}

	// If post_id is -1 call is only to check password
	if ( -1 == $post_id ) {
		return true;
	}

	// Verify user can edit post
	if ( ! user_can( $user, 'edit_post', $post_id ) ) {
		return false;
	}

	// Set the post live status
	$blogging_tools = new LivePress_Blogging_Tools();
	$blogging_tools->set_post_live_status( $post_id, $args[3] );
	return 1;
}

/**
 * Called by livepress webservice to create a new live post.
 *
 * @param array $args {
 *		An array of arguments.
 *		@param  int    $blog_id           Id of blog to to add post to.
 *		@param  string $username          Username for action.
 *		@param  string $password          Livepress generated user access key.
 *		@param  array  $content_struct    Initial post content.
 *		@return Object Post object or false on error.
 * }
 */
function livepress_new_live_post( $args ) {
	global $wp_xmlrpc_server, $wpdb;

	$wp_xmlrpc_server->escape( $args );
	$blog_id        = (int) $args[0];
	$username       = $args[1];
	$password       = $args[2];
	$content_struct = $args[3];

	// Verify user is authorized
	if ( ! $user = _livepress_authenticate( $username, $password ) ) {
		return false;
	}

	// Verify user can edit posts
	if ( ! user_can( $user, 'edit_posts' ) ) {
		return false;
	}

	unset( $content_struct['ID'] );

	$defaults = array( 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $user->ID,
		'post_title' => $content_struct['title'] );

	$post_data = wp_parse_args( $content_struct, $defaults );

	// Insert the new post
	$post_id = wp_insert_post( $post_data, true );


	if ( is_wp_error( $post_id ) ) {
		return false;
	}

	if ( ! $post_id ) {
		return false;
	}

	$post = get_post( $post_id );

	if ( ! $post ) {
		return false;
	}

	// Set the post live status to live
	$blogging_tools = new LivePress_Blogging_Tools();
	$blogging_tools->set_post_live_status( $post_id, true );

	return $post;
}





