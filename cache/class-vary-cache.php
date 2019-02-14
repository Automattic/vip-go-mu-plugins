<?php

namespace Automattic\VIP\Cache;

use WP_Error;

class Vary_Cache {
	private const COOKIE_NO_CACHE = 'vip-go-cb';
	private const COOKIE_SEGMENT = 'vip-go-seg';
	private const COOKIE_AUTH = 'vip-go-auth';

	// Allowed values in cookie are alphanumerics (A-Za-z0-9) and underscore (_) and hyphen (-)
	private const GROUP_SEPARATOR = "__";
	private const VALUE_SEPARATOR = "_--_";

	private static $encryption_enabled = false;
	private static $groups = [];

	private static $cookie_expiry = MONTH_IN_SECONDS;

	/* nocache */
	static function set_no_cache_for_user() {
		self::set_cookie( self::COOKIE_NO_CACHE, 1 );
	}

	static function remove_no_cache_for_user() {
		if ( isset( $_COOKIE[ self::COOKIE_NO_CACHE ] ) ) {
			self::unset_cookie( self::COOKIE_NO_CACHE );
		}
	}

	/* Grouping */

	static function register_groups( $groups ) {
		if( is_array( $groups ) ) {
			foreach( $groups as $group){
				self::$groups[ $group ] = '';
			}
		} else {
			self::$groups[ $groups ] = '';
		}

		self::parseGroupCookie();
	}

	// will set the group cookie to the added group to indicate Varnish to cache it for those groups
	static function set_group_for_user( $group, $value ) {
		//TODO: make sure headers aren't already sent
		//TODO: only send header if we added or changed things
		//TODO: don't set the cookie if was already set on the request
		// validate, process $group, etc.
		self::$groups[ $group ] = $value;
		if ( self::is_encryption_enabled() ) {

			self::set_group_cookie_encrypted( self::stringifyGroups() );
		} else {
			self::set_group_cookie_plaintext( self::stringifyGroups() );
		}
	}


	/* check if the user has a group set and optionally that the group value matches */
	static function is_user_in_group( $group, $value ) {
		self::parseGroupCookie();
		if( ! isset ( self::$groups[ $group ] ) ) {
			return false;
		}

		return ( null === $value ) || ( self::$groups[ $group ] === $value );
	}

	static function get_user_groups( ) {
		self::parseGroupCookie();
		return self::$groups;
	}

	static function set_encryption( $value = true ) {
		static::$encryption_enabled = $value;
	}

	static function is_encryption_enabled() {
		return static::$encryption_enabled;
	}

	static private function set_group_cookie_encrypted( $value, $ttl = null ) {
		// Validate that we have the secret values
		if ( ! defined( 'VIP_GO_AUTH_COOKIE_KEY' ) || ! defined( 'VIP_GO_AUTH_COOKIE_IV' ) ) {
			// TODO: check that values are not empty
			trigger_error( 'Secrets not defined for encrypted vary cookies', E_USER_WARNING );
			return;
		}

		$client_key = constant( 'VIP_GO_AUTH_COOKIE_KEY' );
		$client_iv = constant( 'VIP_GO_AUTH_COOKIE_IV' );
		$cookie_value = random_bytpes( 32 ) + '|' . $value . '|' . time() + $ttl;
		$cipher_cookie = openssl_encrypt( $cookie_value, 'aes-128-cbc', $client_key, 0, $client_iv );

		// TODO: need to scope cookie domain/path + TTL
		self::set_cookie( self::COOKIE_AUTH, $cipher_cookie );
	}

	static private function set_group_cookie_plaintext( $value, $ttl = null ) {
		// TODO: need to scope cookie domain/path + TTL
		self::set_cookie( self::COOKIE_SEGMENT, $value );
	}


	static private function parseGroupCookie() {
		if ( isset( $_COOKIE[ self::COOKIE_SEGMENT ] ) ) {
			$groups = explode( self::GROUP_SEPARATOR, $_COOKIE[ self::COOKIE_SEGMENT ] );
			foreach( $groups as $group ) {
				// TODO: error handling (what if it's not in the right format?)
				list( $group_name, $group_value ) = explode( self::VALUE_SEPARATOR, $group );
				self::$groups[ $group_name ] = $group_value ?? '';
			}
		}
	}


	/*flatten the 2D array into a serialzied string compatible with the cookie format */
	static private function stringifyGroups()
	{
		ksort( self::$groups ); //make sure the string order is the same every time
		$flatten = function ( $key, $value ) {
			return $key . self::VALUE_SEPARATOR . $value;
		};
		$flattened = array_map( $flatten, array_keys( self::$groups ), self::$groups );

		return implode(self::GROUP_SEPARATOR, $flattened );
	}

	/**
	 * Adjust the default cookie expiry
	 *
	 * @param int $expiry Seconds in the future when the cookie should expire (e.g. MONTH_IN_SECONDS). Must be more than 1 hour.
	 */
	static function set_cookie_expiry( int $expiry ) {
		if ( $expiry < HOUR_IN_SECONDS ) {
			trigger_error( sprintf( '%s: cookie expiry must be greater than or equal to 1 hour (%d)', __METHOD__, HOUR_IN_SECONDS ), E_USER_WARNING );
			return;
		}
		self::$cookie_expiry = $expiry;
	}

	// Hook to send the Vary header
	static function add_vary_headers() {
		if ( ! empty( self::$groups ) ) {
			header( 'Vary: X-VIP-Go-Segmentation' );
			header( 'X-VIP-Go-Segmentation-Debug: ' . self::stringifyGroups() );
		}
	}

	static private function set_cookie( $name, $value ) {
		$expiry = time() + self::$cookie_expiry;
		setcookie( $name, $value, $expiry, COOKIEPATH, COOKIE_DOMAIN );
	}

	static private function unset_cookie( $name ) {
		setcookie( $name, '', time() - 3600 );
	}
}

// TODO: move
add_action( 'send_headers', 'Automattic\Vip\Cache\Vary_Cache::add_vary_headers' );
