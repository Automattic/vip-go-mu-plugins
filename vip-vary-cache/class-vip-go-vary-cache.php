<?php

namespace Automattic\VIP\Cache;

//use WP_Error;


class Vary_Cache
{
	private static $PREFIX_NO_CACHE = "vip-go-cb";
	private static $PREFIX_SEGMENT = "vip-go-seg";
	private static $PREFIX_AUTH = "vip-go-auth";

	private static $encryption_enabled = false;

	public function __construct(  )
	{
	}

	/* nocache */
	static function set_no_cache_for_user( ) {
		setcookie(static::$PREFIX_NO_CACHE, 1);
	}

	static function remove_no_cache_for_user( ) {
		if(isset($_COOKIE[static::$PREFIX_NO_CACHE])){
			setcookie( static::$PREFIX_NO_CACHE, '', time() - 3600 );
		}
	}

	/* Grouping */
	static function set_group_for_user( $group ) {
		// validate, process $group, etc.
		if ( self::is_encryption_enabled() ) {
			self::set_group_cookie_encrypted( $group );
		} else {
			self::set_group_cookie_plaintext( $group );
		}
	}

	static function is_user_in_group( $group ) {

	}

	static function get_user_groups( $group ) {
	}

	static function enable_encryption() {
		//check if there's meta values et for the the IV & key
		static::$encryption_enabled = true;
	}

	static function is_encryption_enabled() {
		return static::$encryption_enabled;
	}

	static private function set_group_cookie_encrypted( $value, $ttl = null ) {
		//validate that we have the secret values
		if ( ! defined( '' ) || ! defined( 'FILES_ACCESS_TOKEN' ) ) {
			error_log('Secrets not defined for encrypted vary cookies ');
			return;
		}
		$client_key = constant( 'VIP_GO_AUTH_COOKIE_KEY' );
		$client_iv = constant( 'VIP_GO_AUTH_COOKIE_IV' );
		$cookie_value = random_bytpes(32) + "|" . $value . "|".time() + $ttl;
		$cipher_cookie = openssl_encrypt( $cookie_value, 'aes-128-cbc', $client_key, 0, $client_iv );
		setcookie(static::$PREFIX_AUTH, $cipher_cookie);
	}

	static private function set_group_cookie_plaintext( $value, $ttl = null ) {
		setcookie(static::$PREFIX_SEGMENT, $value);
	}


}

