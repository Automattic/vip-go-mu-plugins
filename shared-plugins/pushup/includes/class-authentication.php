<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class PushUp_Notifications_Authentication {

	/**
	 * Internal variable for determining if we are currently authenticated
	 * (especially when transients are broken or cannot be trusted)
	 *
	 * - null means unknown
	 * - false is a cached value for not authenticated
	 * - true is a cached value for authenticated
	 *
	 * @var bool|null
	 */
	private static $_is_authenticated = null;

	/**
	 * The transient key that will be used to store the most recent authentication attemp.
	 *
	 * @var string
	 */
	public static $_authentication_transient_key = 'pushup_authentication';

	/**
	 * Handles initializing this class and returning the singleton instance after it's been cached.
	 *
	 * @return null|PushUp_Notifications_Authentication
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
	 * An unauthenticated request to the JSON API to check the validity of a
	 * username and an API key
	 *
	 * @return array
	 */
	public static function authenticate() {

		// Make an unauthenticated request to the API
		$response = PushUp_Notifications_JSON_API::perform_unauthenticated_query( array(
			'push.authenticate' => array(
				'username' => PushUp_Notifications_Core::get_username(),
				'api_key'  => PushUp_Notifications_Core::get_api_key(),
			)
		) );

		// If we got a response, let's use it
		if ( true === $response['push.authenticate'] ) {

			// Cache the authentication
			self::_set_cached_authentication( true );

			// Authenticated
			return true;
		}

		// Cache the authentication as false
		self::_set_cached_authentication( false );

		// Boourns... not authenticated
		return false;
	}

	/**
	 * Checks to make sure the user is authenticated or not.
	 *
	 * @param bool $use_api Optional. true (default) will hit the PushUp API
	 *                      if no cached authentication exists.
	 * @return bool
	 */
	public static function is_authenticated( $use_api = true ) {

		// Return true if valid cached authentication exists
		if ( true === self::_get_cached_authentication() ) {
			return true;
			
		// Return results of API hit if falling back
		} elseif ( true === $use_api  ) {
			return self::authenticate();
		}

		// Return false if no other authentication was found
		return false;
	}

	/**
	 * Get any pre-existing authentication data, if it exists.
	 *
	 * @return mixed False if no data exists. Array of data if data exists.
	 */
	public static function get_authentication_data() {

		// Get the required data for a valid authentication
		$domain        = PushUp_Notifications_Core::get_site_url();
		$websitePushID = PushUp_Notifications_Core::get_website_push_id();
		$userID        = PushUp_Notifications_Core::get_user_id();

		// Return an array of authenticated data if valid
		if ( ! empty( $domain ) && ! empty( $websitePushID ) && ! empty( $userID ) ) {
			return array(
				'domain'          => $domain,
				'website_push_id' => $websitePushID,
				'user_id'         => $userID
			);
		}

		// Return false if not a valid authentication
		return false;
	}

	/**
	 * Does a previously successful  authentication attempt exist?
	 *
	 * @return bool
	 */
	public static function _get_cached_authentication() {
		return ( self::$_is_authenticated || get_transient( self::$_authentication_transient_key ) );
	}

	/**
	 * Attempt to cache the current successful authentication
	 *
	 * @param bool|null $auth
	 * @return bool|null
	 */
	public static function _set_cached_authentication( $auth = null ) {

		// Nullifying any existing auths (delete transient and set to null)
		if ( null === $auth ) {
			self::$_is_authenticated = null;
			delete_transient( self::$_authentication_transient_key );
			return null;

		// Auth failed (save transient as false, set to false)
		} elseif ( false === $auth ) {
			self::$_is_authenticated = false;
			set_transient( self::$_authentication_transient_key, false, 6 * HOUR_IN_SECONDS );
			return false;

		// Auth success (save transient as true, set to true)
		} elseif ( true === $auth ) {
			self::$_is_authenticated = true;
			set_transient( self::$_authentication_transient_key, true, 6 * HOUR_IN_SECONDS );
			return true;
		}
	}
}
