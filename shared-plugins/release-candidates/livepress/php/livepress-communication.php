<?php
/**
 * LivePress Communication
 *
 * This files does all the communication within the
 * livepress service.
 *
 * @package Livepress
 */

require_once( LP_PLUGIN_PATH . 'php/livepress-config.php' );

if ( ! class_exists( 'WP_Http' ) ) {
	include_once( ABSPATH . WPINC. '/class-http.php' );
}

/**
 * Do the communication with the livepress service.
 *
 * @since 1.0
 *
 * @todo Make this class a singleton
 */
class LivePress_Communication {
	/**
	 * @access private
	 * @var null LivePress_Config
	 */
	private $livepress_config;

	/**
	 * @access private
	 * @var string $api_key
	 */
	private $api_key;

	/**
	 * @access private
	 * @var string $address;
	 */
	private $address;

	/**
	 * @access private
	 * @var string $last_error
	 */
	private $last_error;

	/**
	 * @access private
	 * @var null $last_response;
	 */
	private $last_response;

	/**
	 * Constructor
	 *
	 * @param string $api_key The api key to be used in the validations inside
	 *                        the livepress service.
	 */
	public function __construct( $api_key = '' ) {
		if ( '' == $api_key ) {}

		$this->livepress_config = LivePress_Config::get_instance();
		$this->api_key          = $api_key;
		// Note: site_url is the admin url on VIP
		$this->address          = site_url(); // WP API
		$this->last_error       = '';
		$this->last_response    = null;
	}

	/**
	 * Read current blog status.
	 *
	 * Returns:
	 *   twitter_username
	 *   shortname
	 *
	 * @param $post_vars
	 */
	public function get_blog($post_vars=null) {
		try {
			return json_decode( $this->request_content_from_livepress( '/blog/get', 'post', $post_vars ) )->blog;
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Reset all the blogs configuration on LP! service.
	 */
	public function reset_blog() {
		$this->request_to_livepress( '/blog/reset', 'post' );
	}

	/**
	 * Get the last message returned with code different than 200 and erase it.
	 *
	 * @return string           Last error message.
	 */
	public function get_last_error_message() {
		$msg = $this->last_error;
		$this->last_error = '';
		return $msg;
	}

	/**
	 * Retrieve last response.
	 *
	 * @return mixed
	 */
	public function get_last_response() {
		$msg = $this->last_response;
		$this->last_response = null;
		return $msg;
	}

	/**
	 * Send to the livepress service the old and new content of a post
	 * so it can be diffed and send to the users.
	 *
	 * $post_vars can have the following:
	 * - data:          New content of the post.
	 * - old_data:      Old content of the post.
	 * - post_id:       ID of the post being modified.
	 * - author:        For Post to other networks.
	 * - updated_at:    Datetime to display in the update box.
	 * - is_new:        Is that first update for newly created post.
	 * - previous_uuid: The uuid used on the last oortle message.
	 * - uuid:          The uuid to be used on this oortle message.
	 * - post_title:
	 * - post_link:
	 *
	 * @param array $post_vars See description.
	 * @return string UUID to check the status of the delivery to oortle.
	 * @throws LivePress_Communication_Exception If the request don't return the http code 200.
	 */
	public function send_to_livepress_post_update($post_vars) {
		$return_data = json_decode( $this->request_content_from_livepress( '/message/post_update', 'post', $post_vars ) );
		return $return_data->oortle->jobs->reader;
	}

	/**
	 * Broadcast any message over post
	 */
	public function send_to_livepress_broadcast( $post_id, $data ) {
		$post_vars = array(
			'post_id' => $post_id,
			'data' => json_encode( $data )
		);
		$return_data = json_decode( $this->request_content_from_livepress( '/message/broadcast', 'post', $post_vars ) );
		return $return_data->oortle->jobs->publish;
	}

	/**
	 * Incremental api functions
	 * TODO: write docs
	 * op == append | prepend | replace | delete
	 */
	public function send_to_livepress_incremental_post_update($op, $post_vars) {
		$return_data = json_decode( $this->request_content_from_livepress( '/message/'.$op.'_update', 'post', $post_vars ) );
		return $return_data->oortle->jobs->reader;
	}

	/**
	 * Get the current status of a job.
	 *
	 * @param string $uuid The job uuid
	 * @return string completed / failed / queued
	 */
	public function get_job_status($uuid) {
		$params['uuid'] = $uuid;
		return $this->request_content_from_livepress( '/message/job_status', 'GET', $params );
	}

	/**
	 * Send to the livepress service the new post title
	 *
	 * $params can have the following:
	 * - post_id:       ID of the post being modified. (Int)
	 * - previous_uuid: The uuid used on the last oortle message.
	 * - uuid:          The uuid used on this oortle message.
	 * - post_link:
	 *
	 * @param array  $params    See description.
	 * @param string $old_title Old post title.
	 * @param string $new_title New post title.
	 *
	 * @throws LivePress_Communication_Exception If the request don't return the http code 200.
	 */
	public function send_to_livepress_title_changed($params, $old_title, $new_title) {
		$params['data'] = JSON_encode( array( $old_title, $new_title ) );
		$params['post_title'] = $new_title;

		$this->request_content_from_livepress( '/message/post_title_update', 'post', $params );
	}

	/**
	 * Sends to livepress a new post notification.
	 *
	 * $params can have the following:
	 * - data:          Data to be delivered to JS (string)
	 * - post_id:       The current post id, used by the PuSH feature
	 * - post_title:    The current post title, used by the PuSH feature
	 * - post_link:     The current post permalink, used by the PuSH feature
	 * - previous_uuid: The uuid used on the last oortle message.
	 *
	 * @param array $params See description.
	 * @return array Containing oortle_msg - Oortle message hash, feed_link - Link to the updates feed.
	 * @throws LivePress_Communication_Exception If the request don't return the http code 200.
	 */
	public function send_to_livepress_new_post($params) {
		$params['uuid'] = $this->new_uuid();
		$params['previous_uuid'] = get_option( LP_PLUGIN_NAME.'_new_post' );
		$params['debug'] = $this->livepress_config->get_debug_data();
		$return_data = json_decode( $this->request_content_from_livepress( '/message/new_post', 'post', $params ) );
		return array(
			'oortle_msg' => $params['uuid'],
			'feed_link'  => isset( $return_data ) ? $return_data->feed_link : '',
		);
	}

	/**
	 * Send to livepress service test message request.
	 *
	 * @param array $post_vars
	 * @return int
	 */
	public function send_to_livepress_test_message_request( $post_vars ) {
		return $this->request_to_livepress( '/im_bot/test_message/', 'post', $post_vars );
	}

	/**
	 * Send to the livepress service the content of a comment.
	 *
	 * Please note that the passed param list is filtered to the one
	 * mentioned below, so make sure you name them properly.
	 *
	 * @param array $params {
	 *     Arguments to be passed to livepress.
	 *
	 *     @type int    $comment_id                   ID of the comment being added.
	 *     @type int    $post_id                      Post ID.
	 *     @type string $post_author                  Username of post author being commented.
	 *     @type string $post_title                   Title of post being commented.
	 *     @type string $old_template                 Content of the old comment template.
	 *     @type string $new_template                 Content of the new comment template.
	 *     @type string $old_template_logged          Content of the old comment template when the user is logged.
	 *     @type string $new_template_logged          Content of the new comment template when the user is logged.
	 *     @type string $content                      The comment content text.
	 *     @type string $author                       The comment author name.
	 *     @type string $avatar_url                   Comment author avatar URL.
	 *     @type string $author_url                   Comment author URL.
	 *     @type string $comment_url                  Comment URL.
	 *     @type string $comment_gmt                  Comment time in GMT.
	 *     @type string $omment_parent                Comment parent ID.
	 *     @type string $comment_html                 HTML of the comment node.
	 *     @type string $comment_html_logged          HTML of the comment node when the user is logged.
	 *     @type string $comments_counter_only        HTML with all comments without the new one but with
	 *                                                updated counter only.
	 *     @type string $comments_counter_only_logged As comment_counter_only but for logged user.
	 *     @type string $ajax_nonce                   As unique identifier to have possibility if current user is an
	 *                                                author of comment event if he's anonymous.
	 * }
	 * @throws LivePress_Communication_Exception If the request don't return the http code 200.
	 */
	public function send_to_livepress_approved_comment( $params ) {
		$this->request_content_from_livepress( '/message/approved_comment', 'post', $params );
	}

	/**
	 * Send to the livepress service the content of a comment.
	 *
	 * This is different from the above one because it is posted straight to the oortle without the diffs
	 *
	 * @param array $params {
	 *     Arguments to be passed to livepress.
	 *
	 *     @type int    $comment_id   ID of the comment being added.
	 *     @type int    $post_id      Post ID.
	 *     @type string $post_title   Title of post being commented.
	 *     @type string $post_link    Link of the post being commented. e when the user is logged.
	 *     @type string $content      The comment content text.
	 *     @type string $author       The comment author name.
	 *     @type string $status       Comment status.
	 *     @type string $author_email Comment author email.
	 *     @type string $author_url   Comment author URL.
	 *     @type string $avatar_url   Comment author avatar URL.
	 *     @type string $comment_url  Comment URL.
	 *     @type string $comment_gmt  Comment time in GMT.
	 * }
	 * @return string Message identification hash.*
	 * @throws LivePress_Communication_Exception If the request don't return the http code 200.
	 */
	public function send_to_livepress_new_created_comment( $params ) {
		$params['uuid'] = $this->new_uuid();
		$this->request_content_from_livepress( '/message/new_comment', 'post', $params );
		return $params['uuid'];
	}

	/**
	 * Send to the livepress service the Twitter handle search.
	 *
	 * @param string $action Type of action, such as 'clear', 'add', or 'remove'.
	 * @param string $term   Twitter handle to search.
	 * @return string
	 */
	public function send_to_livepress_handle_twitter_search( $action, $term ) {
		$params = array();
		$params['term'] = $term;
		switch ( $action ) {
			case 'clear':
				$ws_action = 'clear_twitter_search_terms';
			break;
			case 'add':
				$ws_action = 'add_twitter_search_term';
			break;
			case 'remove':
				$ws_action = 'remove_twitter_search_term';
			break;
		}
		$params['format'] = 'json';
		return wp_remote_retrieve_body( ( $this->do_post_to_livepress( "/blog/$ws_action", $params ) ) );
	}

	/**
	 * Sent to the livepress service the Twitter handle to follow.
	 *
	 * @param string $action   Type of action, such as 'clear', 'add', or 'remove'.
	 * @param string $username Twitter username.
	 * @param int    $postId   Post ID.
	 * @param string $login
	 * @return string
	 */
	public function send_to_livepress_handle_twitter_follow( $action, $username, $postId, $login ) {
		$params = array();
		$params['username'] = $username;
		switch ( $action ) {
			case 'clear':
				$ws_action = 'clear_guest_blogger';
			break;
			case 'add':
				$ws_action = 'add_guest_blogger';
			break;
			case 'remove':
				$ws_action = 'remove_guest_blogger';
			break;
		}
		$params['post_id'] = $postId;
		$params['login']   = $login;
		$params['format']  = 'json';
		return wp_remote_retrieve_body( ( $this->do_post_to_livepress( "/blog/$ws_action", $params ) ) );
	}

	/**
	 * Validate the API key on livepress service.
	 *
	 * @param  Array $domains The urls to be validated with the api key.
	 * @return int   0 if wrong API key, 1 if OK, 2 if Pending, and -1 if connection problems.
	 */
	public function validate_on_livepress( $domains ) {
		$params = array();
		$params['title'] = esc_html( get_bloginfo( 'name' ) );
		$params['debug'] = $this->livepress_config->get_debug_data();
		$params = is_array( $domains ) ? array_merge( $params, $domains ) : $params;
		$status = $this->request_to_livepress( '/message/api_key_validate', 'post', $params );

		if ( $status == 202 ) {
			return 2;
		} else if ( $status == 200 ) {
			return 1;
		} else if ( $status == 401 ) {
			return 0;
		} else {
			return -1;
		}
	}

	/**
	 * Create user record at livepress side.
	 *
	 * @param string $username User username.
	 * @param string $password Generated password for remote post on user behalf.
	 * @param int    $blog_id  Optional. In case of multi-blog install, send ID of blog, user belongs to
	 *                         (looks like currently WP ignore that field). Default 1.
	 * @return int HTTP response code (200 is ok).
	 */
	public function create_blog_user( $username, $password = '', $blog_id = 1 ) {
		$params['username'] = $username;
		$params['password'] = $password;
		$params['blog_rpc'] = $blog_id;
		$action = 'create';

		$code = $this->request_to_livepress( '/blog_user/' . $action, 'post', $params );

		if ( $code == 403 ) {
			$params['blog_user_updates[password]'] = $password;
			$code = $this->request_to_livepress( '/blog_user/update', 'post', $params );
		}
		return $code;
	}

	/**
	 * Enable/Disable a blog user to remote post from twitter.
	 *
	 * @param string $screen_name The twitter username to follow.
	 * @param string $username    Blog user login.
	 *
	 * @return string Server HTTP response.
	 * @throws LivePress_Communication_Exception If failed at some step.
	 */
	public function manage_remote_post_from_twitter($screen_name, $username) {
		$params['username'] = $username;
		$params['blog_user_updates[twitter_username]'] = $screen_name;
		return $this->request_content_from_livepress( '/blog_user/manage_remote_post_from_twitter', 'post', $params );
	}


	/**
	 * Enable/Disable a blog user to remote post from twitter.
	 *
	 * @param string $screen_name The twitter username to follow.
	 * @param string $username    Blog user login.
	 *
	 * @return string Server HTTP response.
	 * @throws LivePress_Communication_Exception If failed at some step.
	 */
	public function set_phone_number( $phone_number, $username ) {
		$params['username'] = $username;
		$params['blog_user_updates[phone_number]'] = $phone_number;
		return $this->request_content_from_livepress( '/blog_user/set_phone_number', 'post', $params );
	}

	/**
	 * Do a request to Twitter api to get an avatar url.
	 *
	 * @param string $username The username of the Twitter account.
	 * @return string Avatar url retrieved from Twitter.
	 */
	public function get_twitter_avatar( $username ) {
		$cachekey = md5( 'twitteravatar' . $username ); // Cache key
		if ( $profile_image = get_transient( $cachekey ) ){
			return $profile_image;
		} else {
			$url  = 'http://api.twitter.com/1/users/show.json?screen_name=';
			$url .= urlencode( $username );
			if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
				$res = vip_safe_wp_remote_get(
					$url,
					'',    /* fallback value */
					5,     /* threshold */
					10,     /* timeout */
					20,    /* retry */
				array( 'reject_unsafe_urls' => false ) );
			} else {
				$res = wp_remote_get( $url, array( 'reject_unsafe_urls' => false ) );
			}
			if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
				return '';
			} else {
				$decoded = json_decode( wp_remote_retrieve_body( $res ) );
				set_transient( $cachekey, $decoded->profile_image_url, DAY_IN_SECONDS ); // Cache twitter avatars for 1 day
					return $decoded->profile_image_url;
			}
		}
	}

	/**
	 * Updates an blog shortname.
	 *
	 * @param string $shortname New blog shortname.
	 * @return int HTTP code response.
	 */
	public function set_blog_shortname( $shortname ) {
		$params['shortname'] = $shortname;
		$code = $this->request_to_livepress( '/blog/set_shortname', 'post', $params );
		return $code;
	}

	/**
	 * Gets a new OAuth authorization url to enable post to twitter.
	 *
	 * @return string A valid url to enable OAuth access.
	 * @throws LivePress_Communication_Exception If failed to get url from livepress.
	 */
	public function get_oauth_authorization_url() {
		// It's not getting an OK response in the first time, so do 3 attemps
		// before declare as failed.
		$attemps_left = 3;
		while ( $attemps_left ) {
			try {
				return $this->request_content_from_livepress(
					'/twitter_oauth/get_authorization_url'
				);
			} catch ( LivePress_Communication_Exception $e ) {
				if ( $attemps_left-- ) {
					throw $e;
				}
			}
		}
	}

	/**
	 * Check if is already authorized twitter access.
	 *
	 * @returns object Status and username of OAuth authorization.
	 * @throws LivePress_Communication_Exception    If failed to authorize on livepress
	 */
	public function is_authorized_oauth() {
		return json_decode(
			$this->request_content_from_livepress( '/twitter_oauth/status' )
		);
	}

	/**
	 * Retrieve tracked Twitter accounts.
	 *
	 * @param array $params
	 * @return string
	 */
	public function followed_tracked_twitter_accounts( $params ) {
		return wp_remote_retrieve_body( ( $this->do_post_to_livepress( '/blog/followed_tracked_twitter_accounts', $params ) ) );
	}

	/**
	 * Gets the twitter username of the authorized user.
	 *
	 * @return string Twitter username.
	 * @throws LivePress_Communication_Exception If livepress failed to respond.
	 */
	public function get_authorized_user() {
		try {
			return $this->request_content_from_livepress( '/twitter_oauth/is_authorized' );
		} catch ( LivePress_Communication_Exception $e ) {
			if ( $e->get_code() == 403 ) {
				return '';
			} else {
				throw $e;
			}
		}
	}

	/**
	 * Destroy the authorized twitter user
	 *
	 */
	public function destroy_authorized_twitter_user() {
		$this->request_to_livepress( '/twitter_oauth/destroy', 'post' );
	}

	/**
	 * New UUID.
	 *
	 * @return string A random version 4 UUID.
	 */
	public function new_uuid() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			// 16 bits
			mt_rand( 0, 0xffff ),
			// 16 bits
			mt_rand( 0, 0x0fff ) | 0x4000,
			// 16 bits
			mt_rand( 0, 0x3fff ) | 0x8000,
			// 48 bits
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	/**
	 * Do a post to the livepress service.
	 *
	 * @param string $action    Livepress service action url.
	 * @param string $post_vars Variables to get.
	 *
	 * @return WP_Error or HTTP API result array.
	 */
	private function do_post_to_livepress($action, $post_vars = array()) {
		$post_vars['address'] = $this->address;
		$post_vars['api_key'] = $this->api_key;

		$res = wp_remote_post(
			$this->livepress_config->livepress_service_host() . $action,
			array(
				'reject_unsafe_urls' => false,
				'body'               => $post_vars
			)
		);
		return $res;
	}

	private function add_vars_to_URL( $get_vars ){
		$url = '';
		foreach ( $get_vars as $key => $value ) {
			$url .= $key;
			$url .= '=';
			$url .= ( is_array( $value ) ) ? $this->add_vars_to_URL( $value ) : urlencode( $value );
			$url .= '&';

		}
		return $url;
	}
	/**
	 * Do a get to the livepress service.
	 *
	 * @param string $action   Livepress service action url.
	 * @param string $get_vars Variables to get.
	 *
	 * @return WP_Error or HTTP API result array.
	 */
	private function do_get_to_livepress($action, $get_vars = array()) {
		$url  = $this->livepress_config->livepress_service_host() . $action;
		$url .= '?';
		$url .= $this->add_vars_to_URL( $get_vars );

		$url .= 'address=';
		$url .= urlencode( $this->address );
		$url .= '&';
		$url .= 'api_key=';
		$url .= urlencode( $this->api_key );

		if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
			return vip_safe_wp_remote_get(
				$url,
				'',    /* fallback value */
				5,     /* threshold */
				10,     /* timeout */
				20,    /* retry */
				array(
					'reject_unsafe_urls' => false,
				)
			);
		} else {
			return wp_remote_get(
				$url,
				array(
					'reject_unsafe_urls' => false,
				)
			);
		}
	}

	/**
	 * Do an HTTP request to livepress service.
	 *
	 * @param string $action Livepress service action url.
	 * @param string $method The HTTP method to be used, if invalid, uses GET
	 * @param string $vars   Variables to post.
	 *
	 * @return int Server HTTP return code.
	 */
	private function request_to_livepress($action, $method = 'get', $vars = array()) {
		$method = strtolower( $method );
		if ( $method == 'post' ) {
			$res = $this->do_post_to_livepress( $action, $vars );
		}
		else {
			$res = $this->do_get_to_livepress( $action, $vars );
		}

		if ( is_wp_error( $res ) ) {
			$this->last_error    = $res->get_error_message();
			$this->last_response = null;
		}
		elseif ( wp_remote_retrieve_response_code( $res ) == 111 ) {
			$this->last_error    = esc_html__( 'Connection refused.', 'livepress' );
			$this->last_response = null;
		}
		elseif ( wp_remote_retrieve_response_code( $res ) != 200 ) {
			$this->last_error    = wp_remote_retrieve_response_message( $res );
			$this->last_response = wp_remote_retrieve_body( $res );
		} else {
			$this->last_response = wp_remote_retrieve_body( $res );
		}

		return wp_remote_retrieve_response_code( $res );
	}

	/**
	 * Do a request to the livepress service.
	 *
	 * @param string $action Livepress service action url.
	 * @param string $method The HTTP method to be used, if invalid, uses GET
	 * @param string $vars   Variables to get.
	 *
	 * @return string Content.
	 * @throws LivePress_Communication_Exception If the request don't return the http code 200.
	 */
	private function request_content_from_livepress($action, $method = 'get', $vars = array()) {
		$method = strtolower( $method );
		if ( $method == 'post' ) {
			$res = $this->do_post_to_livepress( $action, $vars );
		}
		else {
			$res = $this->do_get_to_livepress( $action, $vars );
		}

		if ( is_wp_error( $res ) ) {
			throw new LivePress_Communication_Exception( $res->get_error_message(), -1 );
		}
		elseif ( wp_remote_retrieve_response_code( $res ) != 200 ) {
			throw new LivePress_Communication_Exception( wp_remote_retrieve_body( $res ), wp_remote_retrieve_response_code( $res ) );
		}

		return wp_remote_retrieve_body( $res );
	}
}

/**
 * Request to livepress failed.
 */
class LivePress_Communication_Exception extends Exception {
	/**
	 * Getter - code.
	 *
	 * @return <type> The request HTTP return code.
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * Logs a in the default php error log
	 * @param string $failed_message The kind of message that failed to be delivered
	 */
	public function log($message) {
		$error_msg = 'Failed to send *' . esc_html( $message ).
			'* message to livepress service
			(HTTP return code: ' . esc_html( $this->get_code() ) . ')';
	}

}
