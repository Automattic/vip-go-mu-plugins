<?php

namespace Automattic\VIP\Cache;

use WP_Error;

class Vary_Cache {
	private const COOKIE_NO_CACHE = 'vip-go-cb';
	private const COOKIE_SEGMENT = 'vip-go-seg';
	private const COOKIE_AUTH = 'vip-go-auth';

	private const GROUP_SEPARATOR = "__";
	private const VALUE_SEPARATOR = "_--_";

	private static $encryption_enabled = false;
	private static $groups = [ ];

	/* nocache */
	static function set_no_cache_for_user() {
		// TODO: need to scope cookie domain/path + TTL
		setcookie( self::COOKIE_NO_CACHE, 1 );
	}

	static function remove_no_cache_for_user() {
		if ( isset( $_COOKIE[ self::COOKIE_NO_CACHE ] ) ) {
			setcookie( self::COOKIE_NO_CACHE, '', time() - 3600 );
		}
	}

	/* Grouping */

	static function register_groups( $groups ) {
		self::parseGroupCookie();
		if( is_array( $groups ) ) {
			foreach( $groups as $group){
				self::$groups[ $group ] = '';
			}
		} else {
			self::$groups[ $groups ] = '';
		}
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
		setcookie( self::COOKIE_AUTH, $cipher_cookie );
	}

	static private function set_group_cookie_plaintext( $value, $ttl = null ) {
		// TODO: need to scope cookie domain/path + TTL
		setcookie( self::COOKIE_SEGMENT, $value );
	}


	static private function parseGroupCookie() {
		if( isset( $_COOKIE[ self::COOKIE_SEGMENT ] ) ){
			$groupArray = explode( self::GROUP_SEPARATOR, $_COOKIE[ self::COOKIE_SEGMENT ] );
			foreach( $groupArray as $group )
			{
				$groupArray = explode( self::VALUE_SEPARATOR,$group );
				self::$groups[ $groupArray[ 0 ] ] = $groupArray[ 1 ] ?? '';
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

	//Hook to send the Vary header
	static function add_vary_headers() {
		if ( ! empty( self::$groups ) ) {
			header( 'Vary: X-VIP-Go-Segmentation' );
			header( 'X-VIP-Go-Segmentation-Debug: ' . self::stringifyGroups() );
		}
	}

}

// TODO: move
add_action( 'send_headers', 'Automattic\Vip\Cache\Vary_Cache::add_vary_headers' );
