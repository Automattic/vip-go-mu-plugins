<?php
/**
 * WP_SocialFlow - WordPress SocialFlow Library
 *
 * @author Pete Mall
 */

// Load the OAuth library.
if ( ! class_exists( 'OAuthConsumer' ) )
	require( 'OAuth.php' );

class WP_SocialFlow {

	/**
	 * SocialFlow host url
	 *
	 * @since 1.0
	 * @access private
	 * @var string
	 */
	public $host = 'https://api.socialflow.com/';

	/**
	 * Oauth request token url
	 *
	 * @since 2.0
	 * @access private
	 * @var string
	 */
	const request_token_url = 'https://www.socialflow.com/oauth/request_token';

	/**
	 * Oauth access token url
	 *
	 * @since 2.0
	 * @access private
	 * @var string
	 */
	const access_token_url = 'https://www.socialflow.com/oauth/access_token';

	/**
	 * Oauth authorize url
	 *
	 * @since 2.0
	 * @access private
	 * @var string
	 */
	const authorize_url = 'https://www.socialflow.com/oauth/authorize';

	/**
	 * Create new api object
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string consumer key
	 * @param string consumer secret
	 * @param string oauth token key
	 * @param string oauth token secret
	 */
	function __construct( $consumer_key, $consumer_secret, $oauth_token = NULL, $oauth_token_secret = NULL ) {
		$this->signature_method = new OAuthSignatureMethod_HMAC_SHA1();
		$this->consumer = new OAuthConsumer( $consumer_key, $consumer_secret );

		if ( !empty( $oauth_token ) && !empty( $oauth_token_secret ) )
			$this->token = new OAuthConsumer( $oauth_token, $oauth_token_secret );
		else
			$this->token = NULL;
	}

	/**
	 * Get oauth request token
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string consumer key
	 */
	function get_request_token( $oauth_callback = NULL ) {
		$parameters = array();
		if ( !empty( $oauth_callback ) )
			$parameters['oauth_callback'] = $oauth_callback;

		$request = $this->oauth_request( self::request_token_url, 'GET', $parameters );

		if ( 200 != wp_remote_retrieve_response_code( $request ) )
			return false;

		$token = OAuthUtil::parse_parameters( wp_remote_retrieve_body( $request ) );
		$this->token = new OAuthConsumer( $token['oauth_token'], $token['oauth_token_secret'] );
		return $token;
	}

	/**
	 * Format and sign an OAuth / API request
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string url for sending request
	 * @param string
	 * @param array additional parameters
	 */
	private function oauth_request( $url, $method, $parameters ) {
		$request = OAuthRequest::from_consumer_and_token( $this->consumer, $this->token, $method, $url, $parameters );
		$request->sign_request( $this->signature_method, $this->consumer, $this->token );

		$args = array( 
			'sslverify' => false, 
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( 'sf_partner' . ':' . 'our partners' ) ),
			'timeout' => 20
		);
		$parameters = array_merge( $request->get_parameters(), $args );

		if ( 'GET' == $method ) {
			// Wordpress.com Vip recommend to use vip_safe_wp_remote_get() - cached version of wp_remote_get()
			$func = function_exists( 'vip_safe_wp_remote_get' ) ? 'vip_safe_wp_remote_get' : 'wp_remote_get';
			return call_user_func( $func, $request->to_url(), $parameters );
		}
		else
			return wp_remote_post( $request->to_url(), $parameters );
	  }

	/**
	 * Get authorize url
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string current oauth token
	 */
	function get_authorize_url( $token ) {
		if ( is_array( $token ) )
			$token = $token['oauth_token'];

		return self::authorize_url . "?oauth_token={$token}";
	}

	/**
	 * Exchange request token and secret for an access token and
	 * secret, to sign API calls.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string
	 * @return array containing token and secret as values ( 'oauth_token' => 'the-access-token', 'oauth_token_secret' => 'the-access-secret' )
	 */
	function get_access_token( $oauth_verifier = '' ) {
		$parameters = array();
		if ( !empty( $oauth_verifier ) )
			$parameters['oauth_verifier'] = $oauth_verifier;

		$request = $this->oauth_request( self::access_token_url, 'GET', $parameters );
		$token = OAuthUtil::parse_parameters( wp_remote_retrieve_body( $request ) );

		$this->token = new OAuthConsumer( $token['oauth_token'], $token['oauth_token_secret'] );
		
		return $token;
	}

	/**
	 * Send single message to socialflow
	 *
	 * @param string message content to send
	 * @param string service user id
	 * @param string account type
	 * @param string publishing type ( schedule|optimize|hold|publish )
	 * @param int shorten links
	 * @param array additional parameters to send
	 * @param string additional user id for errors return
	 *
	 * @return mixed (object|bool) return true if message was successfully sent or WP_Error object
	 */
	public function add_message( $service_user_id = '', $account_type = '', $args = array(), $account_id = '' ) {
		$error = new WP_Error;

		// default args
		$defaults = array (
	 		'message' => '',
	 		'publish_option' => 'hold',
			'shorten_links' => 0
		);

		$account_id = empty( $account_id ) ? $service_user_id : $account_id;

		// Parse incomming $args into an array and merge it with $defaults
		$args = wp_parse_args( $args, $defaults );

		// Check if required fields are not empty
		if ( !empty( $service_user_id ) && !empty( $account_type ) ) {
			$args[ 'service_user_id' ] = $service_user_id;
			$args[ 'account_type' ] = $account_type;

			$response = $this->post( 'message/add', $args );

			if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
				// Return posted message on success
				$message = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( $message && isset( $message['data']['content_item'] ) ) {
					return $message['data']['content_item'];
				} else {

					// No content present meaning that some error occurred
					if ( SF_DEBUG ) {
						$this->parse_responce_errors( $response, $error, $account_id );
					} else {
						return new WP_Error( 'error', __( '<b>Error</b> occurred. Please contact plugin author.', 'socialflow' ) );
					}
				}
			}
			elseif ( is_wp_error( $response ) ) {
				return $response;
			}
			elseif ( SF_DEBUG ) {
				$this->parse_responce_errors( $response, $error, $account_id );
			}
			else {
				$error->add( 'error', __( '<b>Error:</b> Error occurred.', 'socialflow' ), $account_id );
			}
		} else {
			$error->add( 'required', __( '<b>Required:</b> Message, service user id and account type are required params.' ), $account_id );
		}

		return $error;
	}

	/**
	 * Send single media to SocialFlow
	 *
	 * @param string media_url Image url
	 *
	 * @return mixed (object|bool) return true if message was successfully sent or WP_Error object
	 */
	public function add_media( $media_url = '' ) {
		$error = new WP_Error;

		$response = $this->post( 'message/attach_media', array( 'media_url' => $media_url ) );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {

			// Return posted message on success
			$message = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $message && isset( $message['data']['media'] ) ) {
				return $message['data']['media'];
			} else {

				// No content present meaning that some error occurred
				if ( SF_DEBUG ) {
					$this->parse_responce_errors( $response, $error, $account_id );
				} else {
					return new WP_Error( 'error', __( '<b>Error</b> occurred. Please contact plugin author.', 'socialflow' ) );
				}
			}
		}
		elseif ( is_wp_error( $response ) ) {
			return $response;
		}
		elseif ( SF_DEBUG ) {
			$this->parse_responce_errors( $response, $error, $account_id );
		}
		else {
			$error->add( 'error', __( '<b>Error:</b> Error occurred.', 'socialflow' ), $account_id );
		}
		

		return $error;
	}

	/**
	 * Parse errors in response 
	 */
	function parse_responce_errors( $response, &$error, $service_user_id ) {
		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( !$response )
			return $error;

		// add errors from response
		if ( isset( $response['data']['errors'] ) AND is_array( $response['data']['errors'] ) )
			foreach ( $response['data']['errors'] as $resp_error ) {
				if ( is_array( $resp_error ) ) {
					if ( isset( $resp_error['msgid'] ) and isset( $resp_error['message'] ) ) {
						$error->add( 'api', $resp_error['msgid'] . ' ' . $resp_error['message'], $service_user_id );
					}
				} else {
					$error->add( 'api', $resp_error, $service_user_id );
				}
			}

		// add message as errror
		if ( isset( $response['data']['message'] ) )
			$error->add( 'api_message', $response['data']['message'], $service_user_id );

		return $error;
	}

	/**
	 * All multiple messages
	 */
	public function add_multiple( $message = '', $service_user_ids, $account_types = '', $publish_option = 'publish now', $shorten_links = 0, $args = array() ) {
		if ( ! ( $message && $service_user_ids && $account_types ) )
			return false;

		$parameters = array(
			'message'          => stripslashes( urldecode( $message ) ),
			'service_user_ids' => $service_user_ids,
			'account_types'    => $account_types,
			'publish_option'   => $publish_option,
			'shorten_links'    => $shorten_links
		);
		$paramters = array_merge( $parameters, $args );

		$response = $this->post( 'message/add_multiple', $parameters );
		if ( 200 == wp_remote_retrieve_response_code( $response ) )
			return true;

		return false;
	}

	/**
	 * Get single message by message id
	 *
	 * @param int message id
	 * @return mixed ( array | object ) array with message data or WP_Error object
	 */
	public function view_message( $content_item_id ) {
		$content_item_id = absint( $content_item_id );
		
		if ( empty( $content_item_id ) ) {
			return new WP_Error( __( 'Invalid message id passed', 'socialflow' ) );
		}

		$response = $this->get( 'message/view', array( 'content_item_id' => $content_item_id ) );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			// Return posted message on success
			$message = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $message )
				return $message['data']['content_item'];
			else
				return new WP_Error( 'error', __( '<b>Error:</b> Error occurred, please try again .', 'socialflow' ) );
		}
		elseif ( is_wp_error( $response ) ) {
			return $response;
		}
		else {
			return new WP_Error( 'error', __( '<b>Error:</b> Error occurred, please try again .', 'socialflow' ) );
		}
	}

	/**
	 * Get list of connected accounts
	 */
	public function get_account_list() {
		$response = $this->get( 'account/list' );

		if ( 200 != wp_remote_retrieve_response_code( $response ) )
			return false;

		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! $response )
			return false;

		$accounts = array();
		foreach ( $response['data']['client_services'] as $account )
			$accounts[ $account['client_service_id'] ] = $account;

		return $accounts;
	}

	/**
	 * Shorten links in passed message string
	 * @param  string $message         Message that may contain links
	 * @param  string $service_user_id User account that will be used to shorten links
	 * @param  string $account_type    User account type
	 * @return (bool|string)           NOTE: may return false on invalid pass parameters or invalid server response
	 */
	public function shorten_links( $message, $service_user_id, $account_type ) {
		if ( !$message || !$service_user_id || !$account_type )
			return false;

		$response = $this->get( 'link/shorten_message', array( 'service_user_id' => $service_user_id, 'account_type' => $account_type, 'message' => stripslashes( $message ) ) );

		if ( 200 != wp_remote_retrieve_response_code( $response ) )
			return false;

		// May return false on failure
		return json_decode( wp_remote_retrieve_body( $response ) )->new_message;
	}

	public function get_account_links( $consumer_key = '' ) {
		if ( !$consumer_key )
			return false;

		// Wordpress.com Vip recommend to use vip_safe_wp_remote_get() - cached version of wp_remote_get()
		$func = function_exists( 'vip_safe_wp_remote_get' ) ? 'vip_safe_wp_remote_get' : 'wp_remote_get';

		$response = call_user_func( $func, "{$this->host}/account/links/?consumer_key={$consumer_key}", array( 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( 'sf_partner' . ':' . 'our partners' ) ), 'sslverify' => false ) );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $response && 200 == $response->status )
				return $response->data;
		}

		return false;
	}

	/**
	 * Retrieve user queue
	 * @param string service user id
	 * @param string account type
	 * @param string order queue messages by
	 * @param int page number
	 * @param int limit messages per page
	 *
	 * @return mixed ( json | WP_Error )
	 */
	public function get_queue( $service_user_id, $account_type, $sort_by = 'date', $page = 1, $limit = 5 ) {
		$error = new WP_Error;

		$response = $this->get( 'contentqueue/list', array( 
			'service_user_id' => $service_user_id,
			'account_type'    => $account_type,
			'sort_by'         => $sort_by,
			'page'            => $page,
			'limit'           => $limit
		 ) );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $response && 200 == $response->status )
				return $response->data->content_queue;
			else
				$error->add( 'error', __( '<b>Error</b> occured.', 'socialflow' ) );
		} else
			$error->add( 'error', __( '<b>Error</b> occured.', 'socialflow' ) );

		return $error;
	}

	/**
	 * Delete message from user query
	 *
	 * @param mixed ( string | array ) content_item_id Unique identifier for message. Can be comma separated to delete multiple messages in a Queue.
	 * @param string service_user_id unique id from service
	 * @param string account_type 
	 * @return mixed ( bool | WP_Error ) result of deleting
	 */
	public function delete_message( $content_item_id, $service_user_id, $account_type ) {
		$error = new WP_Error;

		$response = $this->post( 'message/delete', array( 
			'content_item_id' => $content_item_id,
			'service_user_id' => $service_user_id,
			'account_type'    => $account_type
		 ) );

		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			$response = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $response && 200 == $response->status )
				return true;
			else
				$error->add( 'error', __( '<b>Error</b> occured.', 'socialflow' ) );
		} else
			$error->add( 'error', __( '<b>Error</b> occured.', 'socialflow' ) );

		return $error;
	}

	/**
 	 * GET wrapper for oAuthRequest.
 	 */
	public function get( $url, $parameters = array() ) {
		$url = $this->host . $url;
		return $this->oauth_request( $url, 'GET', $parameters );
	}

	/**
 	 * POST wrapper for oAuthRequest.
 	 */
	public function post( $url, $parameters = array() ) {
		$url = $this->host . $url;
		return $this->oauth_request( $url, 'POST', $parameters );
	}

	
}