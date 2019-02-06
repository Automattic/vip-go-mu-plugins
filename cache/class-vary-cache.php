<?php

namespace Automattic\VIP\Cache;

use WP_Error;

class Vary_Cache {
	private static $PREFIX_NO_CACHE = 'vip-go-cb';
	private static $PREFIX_SEGMENT = 'vip-go-seg';
	private static $PREFIX_AUTH = 'vip-go-auth';

	private static $encryption_enabled = false;

	/* nocache */
	static function set_no_cache_for_user() {
		// TODO: need to scope cookie domain/path + TTL
		setcookie( static::$PREFIX_NO_CACHE, 1 );

		self::track_action( 'no_cache' );
	}

	static function remove_no_cache_for_user() {
		if ( isset( $_COOKIE[ static::$PREFIX_NO_CACHE ] ) ) {
			setcookie( static::$PREFIX_NO_CACHE, '', time() - 3600 );
		}
	}

	/* Grouping */
	// will set the group cookie to the added group to indicate Varnish to cache it for those groups
	// TODO: group + value to allow multiple groups
	static function set_group_for_user( $group ) {
		//TODO: make sure headers aren't already sent
		//TODO: only send header if we added or changed things
		//TODO: don't set the cookie if was already set on the request
		// validate, process $group, etc.
		if ( self::is_encryption_enabled() ) {
			self::set_group_cookie_encrypted( $group );

			self::track_action( 'set_user_group_encrypted' );
		} else {
			self::set_group_cookie_plaintext( $group );

			self::track_action( 'set_user_group' );
		}
	}


	static function is_user_in_group( $group ) {
		return isset( $_COOKIE[ static::$PREFIX_SEGMENT ] ) && $_COOKIE[ static::$PREFIX_SEGMENT ] === $group;
	}

	static function get_user_groups() {
		// TODO
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
		setcookie( static::$PREFIX_AUTH, $cipher_cookie );
	}

	static private function set_group_cookie_plaintext( $value, $ttl = null ) {
		// TODO: need to scope cookie domain/path + TTL
		setcookie( static::$PREFIX_SEGMENT, $value );
	}

	// Send action for tracking purposes
	static private function track_action( $action ) {
		if ( defined( 'VIP_GO_APP_ID' ) ) {
			add_action( 'vipgo_did_vary_cache', $action, constant( 'VIP_GO_APP_ID' ) );
		}
	}

	// Hook to send the Vary header
	static function add_vary_headers() {
		if( ! headers_sent() ) {
			header( 'X-Vary2: abc' );
		}
	}
}

// TODO: move
add_action( 'send_headers', 'Automattic\Vip\Cache\Vary_Cache::add_vary_headers' );
