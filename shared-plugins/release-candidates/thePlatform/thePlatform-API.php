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

require_once( dirname( __FILE__ ) . '/thePlatform-URLs.php' );

/**
 * Simple wrapper class for the WordPress HTTP API methods
 */
class ThePlatform_API_HTTP {

	/**
	 * Checks API calls for authentication errors, and re-authenticates if needed
	 *
	 * @param array $response The response from thePlatform's API
	 * @param string $url The URL used to make the initial call that failed
	 *
	 * @return bool|string Returns the URL with a new authentication token if the call failed, or false if the call did not have an authentication error
	 */
	private static function check_for_auth_error( $response, $url ) {
		// Sign in if the token is invalid/expired
		$responseBody = wp_remote_retrieve_body( $response );

		//If the response is longer than 500 characters, then it's definitely not an auth exception, don't process very long strings.
		if ( strlen( $responseBody ) < 500 && strpos( $responseBody, 'Invalid security token.' ) !== false ) {
			$tp_api   = new ThePlatform_API();
			$oldToken = get_option( TP_TOKEN_OPTIONS_KEY );
			$token    = $tp_api->mpx_signin( true );
			$newUrl   = str_replace( $oldToken, $token, $url );

			return $newUrl;
		} else {
			return false;
		}
	}

	/**
	 * Make a HTTP GET request to the provided URL
	 *
	 * @param string $url URL to make the request to
	 * @param array $data optional Data to send with the request
	 * @param boolean $cache Try and make a cache request first
	 *
	 * @return wp_response Results of the GET request
	 */
	static function get( $url, $data = array(), $cache = false ) {
		// esc_url_raw eats []'s , so I'm forced to skip it for urls containing
		// those characters - at this time only the account list request
		if ( ! strpos( $url, '[0]' ) ) {
			$url = esc_url_raw( $url );
		}

		if ( $cache && defined( 'WPCOM_IS_VIP_ENV' ) ) {
			return wpcom_vip_file_get_contents( $url );
		} else {
			$response = wp_remote_get( $url, $data );

			$newUrl = ThePlatform_API_HTTP::check_for_auth_error( $response, $url );

			if ( $newUrl === false ) {
				return $response;
			} else {
				return ThePlatform_API_HTTP::get( $newUrl, $data, $cache );
			}
		}
	}

	/**
	 * Make a HTTP PUT request to the provided URL
	 *
	 * @param string $url URL to make the request to
	 * @param array $data optional Data to send with the request
	 * @param boolean $isJSON optional|TRUE Whether our data is JSON encoded or not
	 *
	 * @return wp_response Results of the PUT request
	 */
	static function put( $url, $data = array(), $isJSON = true ) {
		return ThePlatform_API_HTTP::post( $url, $data, $isJSON, 'PUT' );
	}

	/**
	 * Make a HTTP POST request to the provided URL
	 *
	 * @param string $url URL to make the request to
	 * @param array $data Data to send with the request, default is a blank array
	 * @param boolean $isJSON optional|TRUE Whether our data is JSON encoded or not
	 * @param string $method optional|POST Sets the header HTTP request method
	 *
	 * @return wp_response Results of the POST request
	 */
	static function post( $url, $data, $isJSON = true, $method = 'POST' ) {
		$default_data = array(
			'method'  => $method,
			'timeout' => 10,
		);

		if ( $isJSON ) {
			$default_data['headers'] = array( 'content-type' => 'application/json; charset=UTF-8' );
		}

		$args = array_merge( $default_data, $data );

		$response = wp_remote_post( esc_url_raw( $url ), $args );

		$newUrl = ThePlatform_API_HTTP::check_for_auth_error( $response, $url );

		if ( $newUrl === false ) {
			return $response;
		} else {
			return ThePlatform_API_HTTP::post( $newUrl, $data, $isJSON, $method );
		}
	}
}

/**
 * Handle all calls to mpx API
 */
class ThePlatform_API {
	/**
	 * Class constructor
	 */
	function __construct() {
		$this->get_account();
		$this->get_preferences();
	}

	/**
	 * Gets the mpx account options from the database
	 */
	private function get_account() {
		if ( ! isset ( $this->account ) ) {
			$this->account = get_option( TP_ACCOUNT_OPTIONS_KEY );
		}
	}

	/**
	 * Checks if the mpx account ID is set and returns it if available
	 *
	 * @param boolean $urlEncoded Return the mpx account id in a url encoded format, TRUE by default
	 *
	 * @return string|boolean mpx account ID or False if not found
	 */
	private function get_mpx_account_id( $urlEncoded = true ) {
		$this->get_account();
		if ( empty( $this->account['mpx_account_id'] ) ) {
			return false;
		}
		if ( true === $urlEncoded ) {
			return urlencode( $this->account['mpx_account_id'] );
		}

		return $this->account['mpx_account_id'];
	}

	/**
	 * Gets the mpx preferences from the database
	 */
	private function get_preferences() {
		if ( ! isset ( $this->preferences ) ) {
			$this->preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );
		}
	}

	/**
	 * Construct a Basic Authorization header
	 * @return array
	 */
	private function basicAuthHeader() {
		$this->get_account();

		$encoded = base64_encode( $this->account['mpx_username'] . ':' . $this->account['mpx_password'] );

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $encoded
			)
		);

		return $args;
	}

	/**
	 * Convert a MIME type to an mpx-compliant format identifier
	 *
	 * @param string $mime A MIME-type string
	 * @param string extension The file extension for fallback
	 *
	 * @return string mpx-compliant format string
	 */
	function get_format( $mime, $extension ) {
		$response = ThePlatform_API_HTTP::get( TP_API_FORMATS_XML_URL, null, true );

		if ( ! defined( 'WPCOM_IS_VIP_ENV' ) ) {
			$response = wp_remote_retrieve_body( $response );
		}

		$xmlString = "<?xml version='1.0'?>" . $response;

		if ( empty( $response ) ) {
			return false;
		}

		$formats = simplexml_load_string( $xmlString );

		foreach ( $formats->format as $format ) {
			foreach ( $format->mimeTypes->mimeType as $mimetype ) {
				if ( $mimetype == $mime ) {
					return array(
						'title'       => (string) $format->title,
						'contentType' => (string) $format->defaultContentType
					);
				}
			}
		}

		foreach ( $formats->format as $format ) {
			foreach ( $format->extensions->extension as $ext ) {
				if ( $extension == $ext ) {
					return array(
						'title'       => (string) $format->title,
						'contentType' => (string) $format->defaultContentType
					);
				}
			}
		}

		return array(
			'title'       => 'Unknown',
			'contentType' => 'unknown'
		);
	}

	/**
	 * Get the mpx token from the database or API
	 *
	 * @param  boolean $forceRefresh Always grab a new token, even if the old one is still valid
	 * @param  boolean $updateOptions Update the existing token in the database. Use false for Uploads
	 *
	 * @return string                 Active mpx token
	 */
	function mpx_signin( $forceRefresh = false, $updateOptions = true ) {
		$token = get_option( TP_TOKEN_OPTIONS_KEY );
		if ( $forceRefresh == true || $token == false ) {
			$response = ThePlatform_API_HTTP::get( TP_API_SIGNIN_URL, $this->basicAuthHeader() );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$payload = $this->decode_json_from_server( $response );
			$token   = $payload['signInResponse']['token'];

			if ( $updateOptions == false ) {
				return $token;
			} else {
				update_option( TP_TOKEN_OPTIONS_KEY, sanitize_text_field( $token ) );
			}
		}

		return $token;
	}

	/**
	 * Update a Media Object's Metadata
	 *
	 * @param array $args Media update information
	 *
	 * @return string A message indicating whether or not the update succeeded
	 */
	function update_media_ajax() {
		$token = $this->mpx_signin();
		$args  = array( 'fields' => $_POST['params'], 'custom_fields' => $_POST['custom_params'] );
		$this->create_media_placeholder( $args, $token );
		wp_send_json_success( "Media Updated" );
	}

	/**
	 * Creates a placeholder Media object in mpx.
	 *
	 * @param array $args URL arguments to pass to the Media data service
	 * @param string $token The token for this upload session
	 *
	 * @return string JSON response from the Media data service
	 */
	function create_media_placeholder( $args, $token ) {
		$fields        = json_decode( stripslashes( $args['fields'] ), true );
		$custom_fields = json_decode( stripslashes( $args['custom_fields'] ), true );

		if ( empty( $fields ) ) {
			wp_send_json_error( 'No fields are set, unable to upload Media' );
		}

		// Create the Custom Fields namespace and values arrays
		$custom_field_ns     = array();
		$custom_field_values = array();
		if ( ! empty( $custom_fields ) ) {
			$fieldKeys        = implode( '|', array_keys( $custom_fields ) );
			$customfield_info = $this->get_customfield_info( $fieldKeys );
			foreach ( $customfield_info['entries'] as $value ) {
				if ( $value['namespacePrefix'] !== '' ) {
					$custom_field_ns[ $value['namespacePrefix'] ]                                 = $value['namespace'];
					$custom_field_values[ $value['namespacePrefix'] . '$' . $value['fieldName'] ] = $custom_fields[ $value['fieldName'] ];
				}
			}
		}

		$payload = array_merge( array(
			'$xmlns' => array_merge( array(), $custom_field_ns )
		), array_merge( $fields, $custom_field_values )
		);

		$url = TP_API_MEDIA_ENDPOINT;
		$url .= '&account=' . $this->get_mpx_account_id();
		$url .= '&token=' . $token;

		$data = array( 'body' => json_encode( $payload, JSON_UNESCAPED_SLASHES ) );

		$response = ThePlatform_API_HTTP::post( $url, $data, true );

		$data = $this->decode_json_from_server( $response );
		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			wp_send_json( $data );
		}

		return $data;
	}

	/**
	 * Gets custom fields namespaces and prefixes
	 *
	 * @param string $fields A pipe separated list of media fields
	 *
	 * @return string Default server returned from the Media Account Settings data service
	 */
	function get_customfield_info( $fields ) {
		$token = $this->mpx_signin();
		$url   = TP_API_MEDIA_FIELD_ENDPOINT;
		$url .= '&fields=namespace,namespacePrefix,fieldName';
		$url .= '&byFieldName=' . $fields;
		$url .= '&token=' . $token;

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		}

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			return array( "entries" => [ ] );
		}

		return $data;
	}

	/**
	 * Get the upload server URLs configured for the current user.
	 *
	 * @param string $server_id The current user's default server identifier
	 * @param string $token The token for this upload session
	 *
	 * @return string A valid upload server URL
	 */
	function get_upload_urls( $server_id, $token ) {
		$url = TP_API_FMS_GET_UPLOAD_URLS_ENDPOINT;
		$url .= '&token=' . urlencode( $token );
		$url .= '&account=' . $this->get_mpx_account_id();
		$url .= '&_serverId=' . urlencode( $server_id );

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			return false;
		}

		return $data['getUploadUrlsResponse'][0];
	}

	/**
	 * Initialize a media upload session.
	 *
	 * @return array An array of parameters for the fragmented uploader service
	 */
	function initialize_media_upload_ajax() {
		$args = array(
			'filesize'      => $_POST['filesize'],
			'filetype'      => $_POST['filetype'],
			'filename'      => $_POST['filename'],
			'fields'        => $_POST['fields'],
			'custom_fields' => $_POST['custom_fields'],
			'server_id'     => $_POST['server_id']
		);

		$token = $this->mpx_signin();

		if ( $args['filetype'] === "audio/mp3" ) {
			$args['filetype'] = "audio/mpeg";
		}

		// Get the file extension as a fallback for format MIME
		$extensionIndex = strrpos( $args['filename'], '.' );

		if ( $extensionIndex !== false ) {
			$extension = strtolower( substr( $args['filename'], $extensionIndex + 1 ) );
		} else {
			$extension = 'unk';
		}

		// Get the Format based on the file MIME type
		$format = $this->get_format( $args['filetype'], $extension );

		if ( ! $format ) {
			wp_send_json_error( 'Unable to get Formats.xml from mpx' );
		}
		// Get the upload url based on the server id
		// If no server id is supplied, get the default server for the Format
		$upload_server_id = $args['server_id'];

		if ( $upload_server_id === 'DEFAULT_SERVER' ) {
			$upload_server_id = $this->get_default_upload_server( $format['title'] );
		}

		if ( ! $upload_server_id ) {
			wp_send_json_error( "Unable to determine mpx Server ID, please check your account configuration" );
		}

		$upload_server_base_url = $this->get_upload_urls( $upload_server_id, $token );

		if ( ! $upload_server_base_url ) {
			wp_send_json_error( "Can't determine the mpx upload endpoint." );
		}

		// Create a placeholder media to store the new file in
		$media  = $this->create_media_placeholder( $args, $token );
		$params = array(
			'token'       => $token,
			'mediaId'     => $media['id'],
			'serverId'    => $upload_server_id,
			'account'     => $this->get_mpx_account_id( false ),
			'uploadUrl'   => $upload_server_base_url,
			'format'      => $format['title'],
			'contentType' => $format['contentType']
		);

		wp_send_json_success( $params );
	}

	/**
	 * Returns a default server for the specific format
	 *
	 * @param string $formatTitle mpx Format title
	 *
	 * @return string|boolean mpx Server ID or FALSE
	 */
	function get_default_upload_server( $formatTitle ) {
		$accountSettings = $this->get_account_settings();

		if ( ! $accountSettings ) {
			return false;
		}

		$defaultServers   = $accountSettings['entries'][0]['defaultServers'];
		$defaultServerURN = "urn:theplatform:format:default";

		if ( array_key_exists( $formatTitle, $defaultServers ) ) {
			return $defaultServers[ $formatTitle ];
		} else if ( array_key_exists( $defaultServerURN, $defaultServers ) ) {
			return $defaultServers[ $defaultServerURN ];
		} else {
			$servers = $this->get_servers( array( 'formats' ), '&byFormats=' . $formatTitle );
			if ( array_key_exists( 0, $servers ) ) {
				return $servers[0]["id"];
			} else {
				return false;
			}
		}
	}

	/**
	 * Get the first Streaming Release form mpx based on a Media ID
	 *
	 * @param string $media_id the mpx Media ID
	 *
	 * @return string The Release PID
	 */
	function get_release_by_id( $media_id ) {
		$token = $this->mpx_signin();

		$url = TP_API_MEDIA_RELEASE_ENDPOINT . '&fields=pid';
		$url .= '&byMediaId=' . $media_id;
		$url .= '&token=' . $token;

		$response = ThePlatform_API_HTTP::get( $url );

		$payload    = $this->decode_json_from_server( $response );
		$releasePID = $payload['entries'][0]['plrelease$pid'];


		return $releasePID;
	}

	/**
	 * Query mpx for videos
	 * @return array The Media data service response
	 */
	function get_videos_ajax() {
		$token = $this->mpx_signin();

		$fields = $this->get_query_fields( $this->get_custom_metadata_fields() );

		$url = TP_API_MEDIA_ENDPOINT . '&fields=id,guid,pid,title' . $fields . '&token=' . $token . '&range=' . $_POST['range'];

		if ( $_POST['isEmbed'] === "true" ) {
			$url .= '&byApproved=true&byContent=byReleases=byDelivery%253Dstreaming';
		}

		if ( ! empty( $_POST['myContent'] ) && $_POST['myContent'] === 'true' ) {
			$url .= '&byCustomValue=' . urlencode( '{' . $this->preferences['user_id_customfield'] . '}{' . wp_get_current_user()->ID . '}' );
		}

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		} else {
			wp_send_json_error( 'mpx Account is not set, unable to retrieve videos.' );
		}

		if ( ! empty( $_POST['query'] ) ) {
			$url .= '&' . $_POST['query'];
		}

		$response = ThePlatform_API_HTTP::get( $url, array( "timeout" => 120 ) );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			wp_send_json( $data );
		}

		// Find the userID response and transform it to a human readable value.
		foreach ( $data['entries'] as $entryKey => $entry ) {
			$data['entries'][ $entryKey ] = $this->transform_user_id( $entry );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Query mpx for videos
	 * @return array The Media data service response
	 */
	function get_video_count_ajax() {
		$token = $this->mpx_signin();

		$url = TP_API_MEDIA_ENDPOINT . '&entries=false&count=true&token=' . $token;

		if ( ! empty( $_POST['myContent'] ) && $_POST['myContent'] === 'true' ) {
			$url .= '&byCustomValue=' . urlencode( '{' . $this->preferences['user_id_customfield'] . '}{' . wp_get_current_user()->ID . '}' );
		}

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		} else {
			wp_send_json_error( 'mpx Account is not set, unable to retrieve videos.' );
		}

		if ( ! empty( $_POST['query'] ) ) {
			$url .= '&' . $_POST['query'];
		}

		if ( $_POST['isEmbed'] === "true" ) {
			$url .= '&byApproved=true&byContent=byReleases=byDelivery%253Dstreaming';
		}

		$response = ThePlatform_API_HTTP::get( $url, array( "timeout" => 120 ) );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			wp_send_json( $data );
		}

		wp_send_json_success( $data['totalResults'] );
	}

	/**
	 * Query mpx for a specific video
	 *
	 * @return array The Media data service response
	 */
	function get_video_by_id_ajax() {
		if ( ! isset( $_POST['mediaId'] ) ) {
			wp_send_json_error( "No Media ID specificed in the request" );
		}

		$fullId = strval( $_POST['mediaId'] );

		$id = substr( $fullId, strrpos( $fullId, '/' ) + 1 );

		$token  = $this->mpx_signin();
		$fields = $this->get_query_fields( $this->get_custom_metadata_fields() );

		$url = TP_API_MEDIA_ENDPOINT . '&fields=id,guid,pid,title' . $fields . ' &token=' . $token . '&byId=' . urlencode( $id );

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			wp_send_json( $data );
		}

		wp_send_json_success( $this->transform_user_id( $data['entries'][0] ) );
	}

	function publish_media_ajax() {
		if ( $_POST['profile'] === 'wp_tp_none' ) {
			wp_send_json_success( "No Publishing Profile Selected" );
		}

		$token = $this->mpx_signin();

		$profileId = $_POST['profile'];
		$mediaId   = $_POST['mediaId'];

		$publishUrl = TP_API_PUBLISH_BASE_URL;
		$publishUrl .= '&token=' . urlencode( $token );
		$publishUrl .= '&account=' . urlencode( $_POST['account'] );
		$publishUrl .= '&_mediaId=' . urlencode( $mediaId );
		$publishUrl .= '&_profileId=' . urlencode( $profileId );

		$response = ThePlatform_API_HTTP::get( $publishUrl, array( "timeout" => 120 ) );

		$data = $this->decode_json_from_server( $response, false );

		wp_send_json_success( $data );
	}

	function revoke_media_ajax() {
		$token = $this->mpx_signin();

		$profileId = $_POST['profile'];
		$mediaId   = $_POST['mediaId'];

		$publishUrl = TP_API_REVOKE_BASE_URL;
		$publishUrl .= '&token=' . urlencode( $token );
		$publishUrl .= '&account=' . urlencode( $_POST['account'] );
		$publishUrl .= '&_mediaId=' . urlencode( $mediaId );
		$publishUrl .= '&_profileId=' . urlencode( $profileId );

		$response = ThePlatform_API_HTTP::get( $publishUrl, array( "timeout" => 120 ) );

		$data = $this->decode_json_from_server( $response, false );

		wp_send_json_success( $data );
	}

	/**
	 * Transforms a numerical WordPress User ID from a custom field to a human readable value
	 *
	 * @param array $media mpx Media Object
	 *
	 * @return array Returns the same Media object back with the field transformed
	 */
	function transform_user_id( $media ) {
		$customIdFieldName = $this->preferences['user_id_customfield'];
		if ( array_key_exists( $this->preferences['user_id_customfield'], $media ) ) {
			$user = get_userdata( $media[ $customIdFieldName ] );
			if ( $user ) {
				switch ( $this->preferences['transform_user_id_to'] ) {
					case 'username':
						$media[ $customIdFieldName ] = $user->user_login;
						break;
					case 'nickname':
						$media[ $customIdFieldName ] = $user->nickname;
						break;
					case 'email':
						$media[ $customIdFieldName ] = $user->user_email;
						break;
					case 'full_name':
						$media[ $customIdFieldName ] = $user->user_firstname . ' ' . $user->user_lastname;
						break;
					default:
						break;
				}
			}
		}

		return $media;
	}

	/**
	 * Query mpx for players
	 *
	 * @param array $fields Optional set of fields to request from the data service
	 *
	 * @return array The Player data service response
	 */
	function get_players( $fields = array() ) {
		$default_fields = array( 'id', 'title', 'pid', 'disabled' );

		$fieldsString = implode( ',', array_merge( $default_fields, $fields ) );

		$token = $this->mpx_signin();

		$url = TP_API_PLAYER_PLAYER_ENDPOINT . '&sort=title&fields=' . $fieldsString . '&token=' . $token;

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		}

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			return array();
		}

		$ret = array_filter( $data['entries'], array( $this, "filter_disabled_players" ) );

		return $ret;
	}

	/**
	 * Filtering function to remove all disabled players from mpx results
	 *
	 * @param  Object $var Player entry
	 *
	 * @return boolean
	 */
	function filter_disabled_players( $var ) {
		return $var['disabled'] == false;
	}

	/**
	 * Query mpx for custom metadata fields
	 *
	 * @param boolean $forceRefresh If true, get content from our dataservices, otherwise load it from tranisent storage
	 *
	 * @return array The Media Field data service response
	 */
	function get_custom_metadata_fields( $forceRefresh = false ) {
		if ( $forceRefresh === true || ( false === ( $value = get_transient( TP_TRANSIENT_CUSTOM_METADATA_FIELDS ) ) ) ) {
			$default_fields = array( 'id', 'title', 'description', 'added', 'allowedValues', 'dataStructure', 'dataType', 'fieldName', 'defaultValue', 'namespace', 'namespacePrefix' );

			$fieldsString = implode( ',', $default_fields );

			$this->get_preferences();

			$token = $this->mpx_signin();

			$url = TP_API_MEDIA_FIELD_ENDPOINT . '&fields=' . $fieldsString . '&token=' . $token;

			if ( $this->get_mpx_account_id() ) {
				$url .= '&account=' . $this->get_mpx_account_id();
			}

			$response = ThePlatform_API_HTTP::get( $url );

			$data = $this->decode_json_from_server( $response );

			if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
				return array();
			}
			set_transient( TP_TRANSIENT_CUSTOM_METADATA_FIELDS, $data['entries'], 24 * HOUR_IN_SECONDS );

			return $data['entries'];
		} else {
			return $value;
		}
	}

	/**
	 * Query mpx for available servers
	 *
	 * @param array $fields Optional set of fields to request from the data service
	 * @param String $query Query fields to append to the request URL
	 *
	 * @return array The Media data service response
	 */
	function get_servers( $fields = array(), $query = "" ) {
		$default_fields = array( 'id', 'title', 'description', 'added' );

		$fieldsString = implode( ',', array_merge( $default_fields, $fields ) );

		$token = $this->mpx_signin();

		$url = TP_API_MEDIA_SERVER_ENDPOINT . '&fields=' . $fieldsString . '&token=' . $token;

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		}

		if ( ! empty( $query ) ) {
			$url .= $query;
		}

		$response = ThePlatform_API_HTTP::get( $url );
		$data     = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			return array();
		}

		return $data['entries'];
	}

	/**
	 * Returns the account setting objects, this is actually used to test our connection
	 * @return array AccountSettings response
	 */
	function get_account_settings() {
		$token = $this->mpx_signin();

		$url = TP_API_MEDIA_ACCOUNTSETTINGS_ENDPOINT . '&token=' . $token;

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		}

		$response = ThePlatform_API_HTTP::get( $url );
		$data     = $this->decode_json_from_server( $response, false );

		if ( is_null( $data ) || ( array_key_exists( 'success', $data ) && $data['success'] == false ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * Query mpx for account categories
	 *
	 * @return array The Media data service response
	 */
	function get_categories() {
		$token = $this->mpx_signin();

		$url = TP_API_MEDIA_CATEGORY_ENDPOINT . '&fields=title,fullTitle&sort=title,order&token=' . $token;

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		}

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			return array();
		}

		return $data['entries'];
	}

	function get_categories_ajax() {
		wp_send_json_success( $this->get_categories() );
	}

	/**
	 * Query mpx for subaccounts associated with the configured account
	 *
	 * @return array The Media data service response
	 */
	function get_subaccounts() {

		$token = $this->mpx_signin();

		$url = TP_API_ACCESS_AUTH_ENDPOINT . '&_operations[0].service=Media%20Data%20Service&_operations[0].method=GET&_operations[0].endpoint=Media&token=' . $token . '&sort=title&range=1-1000';

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );


		return $data['authorizeResponse']['accounts'];
	}

	/**
	 * Query mpx for Publishing Profiles associated with the configured account
	 *
	 * @param array $fields Optional set of fields to request from the data service
	 *
	 * @return array The Media data service response
	 */
	function get_publish_profiles( $fields = array() ) {
		$default_fields = array( 'id', 'title' );

		$fieldsString = implode( ',', array_merge( $default_fields, $fields ) );

		$token = $this->mpx_signin();

		$url = TP_API_PUBLISH_PROFILE_ENDPOINT . '&bySupportingProfile=false&fields=' . $fieldsString . '&token=' . $token . '&sort=title';

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		}

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			return array();
		}

		return $data['entries'];
	}

	/**
	 * Query mpx for Task Templates for generating Thumbnails associated with the configured account
	 *
	 * @param array $fields Optional set of fields to request from the data service
	 *
	 * @return array The Media data service response
	 */
	function get_thumbnail_encoding_profiles( $fields = array() ) {
		$default_fields = array( 'id', 'title' );

		$fieldsString = implode( ',', array_merge( $default_fields, $fields ) );

		$token = $this->mpx_signin();

		$url = TP_API_TASK_TEMPLATE_ENDPOINT . '&byTaskType=thePlatform.RMP.Task.ImageGenerator&fields=' . $fieldsString . '&token=' . $token . '&sort=title';

		if ( $this->get_mpx_account_id() ) {
			$url .= '&account=' . $this->get_mpx_account_id();
		}

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		if ( array_key_exists( 'success', $data ) && $data['success'] == false ) {
			return array();
		}

		return $data['entries'];
	}

	/**
	 * Generate a thumbnail from the Media either at the default, or provided time
	 */
	function generate_thumbnail_ajax() {

		if ( isset( $_POST['time'] ) ) {
			$time = $_POST['time'];
		}

		if ( ! isset( $_POST['mediaId'] ) ) {
			wp_send_json_error( array( 'description' => 'Media ID is not set' ) );
		}

		$mediaId        = $_POST['mediaId'];
		$taskTemplateId = $this->preferences['thumbnail_profile_id'];
		$task           = $this->get_task_template_by_id( $taskTemplateId );

		if ( $task == false ) {
			wp_send_json_error( array( 'description' => 'Profile ID not found' ) );
		}

		foreach ( $task['taskArguments'] as $argument ) {
			if ( $argument['name'] == 'width' ) {
				$taskWidth = $argument['value'];
			}
		}
		$mediaFiles = $this->get_video_files_by_media_id( $mediaId )['entries'];

		// Get the nearest video in size
		foreach ( $mediaFiles as $file ) {
			if ( $file['width'] >= $taskWidth && ! in_array( $file['format'], TP_MANIFEST_FORMATS() ) ) {
				$mediaFileId = $file['id'];
				break;
			}
		}

		// We couldn't find a video bigger than the thumbnail template
		// Get the biggest one and crop it
		if ( empty( $mediaFileId ) ) {
			$mediaFilesSize = count( $mediaFiles ) - 1;

			if ( $mediaFilesSize < 0 ) {
				wp_send_json_error( array( 'description' => 'No Media Files Found' ) );
			}
			$mediaFileId = $mediaFiles[ $mediaFilesSize ]['id'];
			$crop        = true;
			$mediaHeight = $mediaFiles[ $mediaFilesSize ]['height'];
			$mediaWidth  = $mediaFiles[ $mediaFilesSize ]['width'];
		}

		$url = TP_API_FMS_GENERATE_THUMBNAIL_ENDPOINT;
		$url .= '&_sourceFileIds[0]=' . urlencode( $mediaFileId );
		$url .= '&_transformId=' . urlencode( $taskTemplateId );
		$url .= '&_mediaId=' . urlencode( $mediaId );
		$url .= '&_mediaFileSettings[0].mediaFileInfo.contentType=image';
		$url .= '&_mediaFileSettings[0].mediaFileInfo.isThumbnail=true';

		if ( isset( $time ) ) {
			$url .= '&_transformArguments[0].name=startTime';
			$url .= '&_transformArguments[0].value=' . urlencode( $time );
		}

		if ( isset( $crop ) ) {
			$url .= '&_transformArguments[1].name=cropTop';
			$url .= '&_transformArguments[1].value=0';
			$url .= '&_transformArguments[2].name=cropWidth';
			$url .= '&_transformArguments[2].value=' . $mediaWidth;
			$url .= '&_transformArguments[3].name=cropHeight';
			$url .= '&_transformArguments[3].value=' . $mediaHeight;
		}

		$url .= '&token=' . $this->mpx_signin();


		ThePlatform_API_HTTP::get( $url );

		wp_send_json_success( 'Completed' );
	}

	/**
	 * Returns all of the MediaFile objects for the provided Media ID
	 *
	 * @param string $mediaId mpx Media ID
	 * @param array $fields Media File fields to query
	 *
	 * @return array MediaFile array
	 */
	function get_video_files_by_media_id( $mediaId, $fields = array() ) {
		$default_fields = array( 'id', 'title', 'width', 'height', 'disabled', 'format' );
		$fieldsString   = implode( ',', array_merge( $default_fields, $fields ) );

		$url = TP_API_MEDIA_FILE_ENDPOINT;
		$url .= '&byMediaId=' . urlencode( $mediaId );
		$url .= '&fields=' . $fieldsString;
		$url .= '&byContentType=video';
		$url .= '&sort=width';
		$url .= '&token=' . $this->mpx_signin();

		$response = ThePlatform_API_HTTP::get( $url );

		return $this->decode_json_from_server( $response );
	}

	/**
	 * Get the TaskTemplate object from mpx by ID
	 *
	 * @param string $taskTemplateId The full TaskTemplate URI
	 *
	 * @return bool|array False if the TaskTemplate can't be found, otherwise returns the TaskTemplate object as an array
	 */
	function get_task_template_by_id( $taskTemplateId ) {
		$url = TP_API_TASK_TEMPLATE_ENDPOINT;

		$id = substr( $taskTemplateId, strrpos( $taskTemplateId, '/' ) + 1 );

		$url .= '&byId=' . $id;
		$url .= '&token=' . $this->mpx_signin();

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		if ( count( $data['entries'] ) == 0 ) {
			return false;
		}

		return $data['entries'][0];
	}

	/**
	 * Return publish profile results for the provided media
	 *
	 * @return array          ProfileResults response
	 */
	function get_profile_results_ajax() {
		$mediaId = $_POST['mediaId'];
		$token   = $this->mpx_signin();

		$url = TP_API_WORKFLOW_PROFILE_RESULT_ENDPOINT . '&token=' . $token . '&fields=profileId,status&byMediaId=' . urlencode( $mediaId );

		$response = ThePlatform_API_HTTP::get( $url );

		$data = $this->decode_json_from_server( $response );

		wp_send_json_success( $data['entries'] );
	}

	/**
	 * Used to verify the account server settings on the server side
	 * @return type
	 */
	function verify_account_settings() {
		$this->get_account();

		$username = trim( $this->account['mpx_username'] );
		$password = trim( $this->account['mpx_password'] );

		if ( $username === "mpx/" || $username === "" || $password === "" ) {
			return false;
		}

		$hash = base64_encode( $username . ':' . $password );

		$response = ThePlatform_API_HTTP::get( TP_API_SIGNIN_URL, array( 'headers' => array( 'Authorization' => 'Basic ' . $hash ) ) );

		$payload = $this->decode_json_from_server( $response, false );

		if ( is_null( $response ) || is_wp_error( $response ) ) {
			return false;
		}

		if ( ! array_key_exists( 'isException', $payload ) ) {
			update_option( TP_TOKEN_OPTIONS_KEY, sanitize_text_field( $payload['signInResponse']['token'] ) );

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Verify that the account you've selected is within the region you've selected
	 * @return bool True if the account is within the same region
	 */
	function verify_account_region() {
		if ( ! $this->get_mpx_account_id() ) {
			return false;
		}

		$response = $this->get_account_settings();

		if ( is_null( $response ) && ! is_array( $response ) ) {
			return false;
		}

		if ( ! array_key_exists( 'isException', $response ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Catch JSON decode errors
	 *
	 * @param wp_response $input a HTTP Response object
	 * @param bool $validateResponse Determines if we should check the response or just return the response body
	 *
	 * @return array|mixed
	 */
	function decode_json_from_server( $input, $validateResponse = true ) {

		$response = json_decode( wp_remote_retrieve_body( $input ), true );

		if ( false === $validateResponse ) {
			return $response;
		}

		$errorResponse = array(
			'success' => false,
			'data'    => 'There was an error getting data from mpx, if the error persists please contact thePlatform. '
		);

		if ( is_null( $response ) && wp_remote_retrieve_response_code( $input ) != "200" ) {
			return $errorResponse;
		}

		if ( is_null( $response ) && wp_remote_retrieve_response_code( $input ) == "200" ) {
			return $response;
		}

		if ( is_wp_error( $response ) ) {
			$errorResponse['data'] .= $response->get_error_message();

			return $errorResponse;
		}

		if ( is_array( $response ) && array_key_exists( 'isException', $response ) ) {
			$errorResponse['data'] .= $response['description'];

			return $errorResponse;
		}

		return $response;
	}

	/**
	 * Parse Custom Fileds and Upload Fields and returns a query string for mpx API calls
	 *
	 * @param array $metadata Custom Fields
	 *
	 * @return string mpx fields in query form
	 */
	function get_query_fields( $metadata ) {
		$custom_metadata_options = get_option( TP_CUSTOM_METADATA_OPTIONS_KEY );
		$basic_metadata_options  = get_option( TP_BASIC_METADATA_OPTIONS_KEY );

		$fields = 'id,defaultThumbnailUrl,content';

		foreach ( $basic_metadata_options as $basic_field => $val ) {
			if ( $val == 'hide' ) {
				continue;
			}

			$field_title = ( strstr( $basic_field, '$' ) !== false ) ? substr( strstr( $basic_field, '$' ), 1 ) : $basic_field;
			if ( ! empty( $fields ) ) {
				$fields .= ',';
			}
			$fields .= $field_title;
		}

		foreach ( $custom_metadata_options as $custom_field => $val ) {
			if ( $val == 'hide' ) {
				continue;
			}

			$metadata_info = null;
			foreach ( $metadata as $entry ) {
				if ( array_search( $custom_field, $entry ) ) {
					$metadata_info = $entry;
					break;
				}
			}

			if ( is_null( $metadata_info ) ) {
				continue;
			}

			$field_title = $metadata_info['fieldName'];

			if ( empty( $fields ) ) {
				$fields .= ':';
			} else {
				$fields .= ',:';
			}

			$fields .= $field_title;
		}

		return $fields;
	}
}