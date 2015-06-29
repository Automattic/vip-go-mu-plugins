<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class PushUp_Notifications_JSON_API {

	/**
	 * The settings data, cached here to avoid duplicate requests.
	 *
	 * @var array
	 */
	protected static $_settings_data = null;

	/**
	 * The JSON endpoint that will be used when communicating.
	 *
	 * @var string
	 */
	protected static $_api_url = 'https://push.10up.com/json.php';

	/**
	 * The last remote request that was processed
	 *
	 * @var array
	 */
	protected static $_last_request = null;

	/**
	 * Handles initializing this class and returning the singleton instance after it's been cached.
	 *
	 * @return null|PushUp_Notifications_JSON_API
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * An empty constructor
	 */
	public function __construct() { /* Purposely do nothing here */ }

	/**
	 * Gets the settings data for an ideal settings page with everything unlocked. Caches these settings to an internal
	 * property so we don't make duplicate calls during the lifetime of a request. Note: we can cache this way simply
	 * because the domain name will not change and we won't need to bust the cache during a page load.
	 *
	 * @return array|bool|mixed|null
	 */
	public static function get_settings_data( $username = '', $api_key = '' ) {

		// Return if already requested
		if ( self::$_settings_data ) {
			return self::$_settings_data;
		}

		// Use username if none passed
		if ( empty( $username ) ) {
			$username = PushUp_Notifications_Core::get_username();
		}

		// Use api key is none passed
		if ( empty( $api_key ) ) {
			$api_key = PushUp_Notifications_Core::get_api_key();
		}

		$site_url = PushUp_Notifications_Core::get_site_url();
		$actions  = array(
			'push.authenticate' => array(
				'username' => $username,
				'api_key'  => $api_key,
			),
			'push.domain' => array(
				'domain' => $site_url,
			),
			'push.analytics' => array(
				'domain' => $site_url,
			),
			'push.domain.config' => array(
				'domain' => $site_url,
			),
			'push.user.id' => array(
				'username' => $username,
				'api_key' => $api_key,
			)
		);

		self::$_settings_data = self::perform_authenticated_query( $actions, $username, $api_key );

		return self::$_settings_data;
	}

	/** Set *******************************************************************/

	/**
	 * Makes a remote API request to update an icon.
	 *
	 * @param string $icon_url
	 * @param string $icon_id
	 * @param string $mode
	 * @return array|bool|mixed
	 */
	public static function update_icon( $icon_url = '', $icon_id = '', $mode = '' ) {
		$data = self::_get_base64_image( $icon_url );
		if ( empty( $data ) ) {
			return false;
		}

		return self::perform_authenticated_query( array(
			'push.icons.update' => array(
				'domain' => PushUp_Notifications_Core::get_site_url(),
				'data'   => $data,
				'id'     => $icon_id,
				'mode'   => $mode,
			),
		) );
	}

	/**
	 * Sets this domain's website name via the JSON API.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function set_website_name( $name = '', $username = '', $api_key = '' ) {
		return self::perform_authenticated_query( array(
			'push.domain.config.setWebsiteName' => array(
				'domain'       => PushUp_Notifications_Core::get_site_url(),
				'website_name' => sanitize_text_field( $name ),
			),
		), $username, $api_key );
	}

	/** Get *******************************************************************/

	/**
	 * Gets the push ID for this domain name.
	 *
	 * @return bool|string
	 */
	public static function get_push_id() {
		$settings_data = self::get_settings_data();
		$retval        = false;

		if ( self::_has_retrieved_action( 'push.domain' ) ) {
			if ( !empty( $settings_data[ 'push.domain' ][ 'push_id' ] ) ) {
				$retval = $settings_data[ 'push.domain' ][ 'push_id' ];
			}
		}

		return $retval;
	}

	/**
	 * Gets the domain's website name via the JSON API.
	 *
	 * @return bool|string
	 */
	public static function get_website_name() {
		$settings_data = self::get_settings_data();
		$retval        = false;

		if ( self::_has_retrieved_action( 'push.domain.config' ) ) {
			if ( !empty( $settings_data[ 'push.domain.config' ][ 'website_name' ] ) ) {
				$retval = $settings_data[ 'push.domain.config' ][ 'website_name' ];
			}
		}

		return $retval;
	}

	/**
	 * Gets the domain's post title via the JSON API.
	 *
	 * @return bool|string
	 */
	public static function get_user_id() {
		$settings_data = self::get_settings_data();
		$retval        = false;

		if ( self::_has_retrieved_action( 'push.user.id' ) ) {
			if ( !empty( $settings_data[ 'push.user.id' ] ) ) {
				$retval = $settings_data[ 'push.user.id' ];
			}
		}

		return $retval;
	}

	/**
	 * Gets the analytics for this domain name via the JSON API.
	 *
	 * @return bool|array
	 */
	public static function get_analytics() {
		$settings_data = self::get_settings_data();
		$retval        = false;

		if ( self::_has_retrieved_action( 'push.analytics' ) ) {
			if ( !empty( $settings_data[ 'push.analytics' ] ) ) {
				$retval = $settings_data[ 'push.analytics' ];
			}
		}

		return $retval;
	}

	/**
	 * Gets the icon data for this domain name.
	 *
	 * @return bool
	 */
	public static function get_icon_data() {
		$settings_data = self::get_settings_data();
		$retval        = false;

		if ( self::_has_retrieved_action( 'push.domain.config' ) ) {
			if ( !empty( $settings_data[ 'push.domain.config' ][ 'icons' ] ) ) {
				$retval = $settings_data[ 'push.domain.config' ][ 'icons' ];
			}
		}

		return $retval;
	}

	/**
	 * Takes an image URL and fetches the contents of that image and then base64 encodes the image so it can be
	 * manipulated.
	 *
	 * @param string $url
	 * @return bool|string
	 */
	protected static function _get_base64_image( $url = '' ) {
		$args = array(
			'timeout' => 20,
			'sslverify' => false,
		);

		$result = wp_remote_get( $url, $args );
		if ( empty( $result ) || is_wp_error( $result ) ) {
			return false;
		}

		$binary_image = wp_remote_retrieve_body( $result );
		if ( empty( $binary_image ) ) {
			return false;
		}

		return base64_encode( $binary_image );
	}

	/** _is_ ******************************************************************/

	/**
	 * Checks the settings for this domain name to see if it's enabled or not.
	 *
	 * @return bool
	 */
	public static function is_domain_enabled() {
		$settings_data = self::get_settings_data();

		if ( ! isset( $settings_data[ 'push.domain' ] ) ) {
			return false;
		} elseif ( isset( $settings_data[ 'push.domain' ][ 'error' ] ) ) {
			return false;
		}

		return $settings_data[ 'push.domain' ][ 'enabled' ];
	}

	/** Cache *****************************************************************/

	/**
	 * Checks to make sure we've successfully acquired a specific API setting.
	 *
	 * @param string $action
	 * @return bool
	 */
	public static function _has_retrieved_action( $action = '' ) {
		$settings_data = self::get_settings_data();

		if ( ! isset( $settings_data[ $action ] ) ) {
			return false;
		} elseif ( isset( $settings_data[ $action ][ 'error' ] ) ) {
			return false;
		}

		return true;
	}

	/** Requests **************************************************************/

	/**
	 * Sends a list of actions to the API to be performed as needed.
	 *
	 * @param array $actions
	 * @return array|bool|mixed
	 */
	public static function perform_unauthenticated_query( $actions = array() ) {

		$request = self::_remote_post( array(
			'actions' => $actions,
		) );

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * Sends a list of actions to the API to be performed as needed.
	 *
	 * @param array $actions
	 * @return array|bool|mixed
	 */
	public static function perform_authenticated_query( $actions = array(), $username = '', $api_key = '' ) {

		// Use username if none passed
		if ( empty( $username ) ) {
			$username = PushUp_Notifications_Core::get_username();
		}

		// Use api key is none passed
		if ( empty( $api_key ) ) {
			$api_key = PushUp_Notifications_Core::get_api_key();
		}

		$request = self::_remote_post( array(
			'username' => $username,
			'api_key'  => $api_key,
			'actions'  => $actions,
		) );

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * Attempt to connect to the json API endpoint
	 *
	 * @param array $data
	 * @return array|bool|mixed
	 */
	protected static function _remote_post( $data = array() ) {

		self::$_last_request = wp_remote_post( self::$_api_url, array(
			'timeout'   => 10,
			'blocking'  => true,
			'body'      => json_encode( $data ),
			'sslverify' => false,
		) );

		if ( is_wp_error( self::$_last_request ) || empty( self::$_last_request ) ) {
			return false;
		}

		return self::$_last_request;
	}

	/**
	 * Return the entire last remote request
	 *
	 * @return array
	 */
	public static function _get_last_request() {
		return self::$_last_request;
	}
}
