<?php

namespace Automattic\VIP\Cache;

use WP_Error;

class Vary_Cache {
	private const COOKIE_NO_CACHE = 'vip-go-cb';
	private const COOKIE_SEGMENT = 'vip-go-seg';
	private const COOKIE_AUTH = 'vip-go-auth';

	// Allowed values in cookie are alphanumerics (A-Za-z0-9) and underscore (_) and hyphen (-).
	private const GROUP_SEPARATOR = '__';
	private const VALUE_SEPARATOR = '_--_';
	private const VERSION_PREFIX = 'vc-v1__';

	/**
	 * Flag to indicate if this an encrypted group request
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     bool  true if encrypted
	 */
	private static $encryption_enabled = false;

	/**
	 * Member variable to store the parsed group values.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     array  Key - Group,  Value - group value
	 */
	private static $groups = [];

	/**
	 * Local reference for cookie expiry.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     int expiration in seconds
	 */
	private static $cookie_expiry = MONTH_IN_SECONDS;

	/** Nocache */
	public static function set_no_cache_for_user() {
		self::set_cookie( self::COOKIE_NO_CACHE, 1 );
	}

	/** Clears the cache-busting flag */
	public static function remove_no_cache_for_user() {
		if ( isset( $_COOKIE[   self::COOKIE_NO_CACHE ] ) ) {
			self::unset_cookie( self::COOKIE_NO_CACHE );
		}
	}

	/**
	 * Set request to indicate the request will vary on a group
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param  array $groups  One or more groups to vary on.
	 */
	public static function register_groups( $groups ) {
		if ( is_array( $groups ) ) {
			foreach ( $groups as $group ) {
				self::$groups[ $group ] = '';
			}
		} else {
			self::$groups[ $groups ] = '';
		}

		self::parse_group_cookie();
	}

	/**
	 * Assigns the user to given group and optionally a value for that group. E.g. location=US
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param  string $group  Group name to vary the request on.
	 * @param  string $value A value for the group.
	 */
	public static function set_group_for_user( $group, $value ) {
		// TODO: make sure headers aren't already sent
		// TODO: only send header if we added or changed things
		// TODO: don't set the cookie if was already set on the request
		// validate, process $group, etc.
		self::$groups[ $group ] = $value;
		if ( self::is_encryption_enabled() ) {
			$cookie_value = self::encrypt_cookie_value( self::stringify_groups() );
			self::set_cookie( self::COOKIE_AUTH, $cookie_value );
		} else {
			self::set_cookie( self::COOKIE_SEGMENT, self::stringify_groups() );
		}
	}

	/**
	 * Checks if the request has some in with agroup cookie matching a given group and optionally a value
	 *
	 * @param  string $group  Group name.
	 * @param  string $value Optional - A value for the group.
	 *
	 * @return bool   True on success. False on failure.
	 */
	public static function is_user_in_group( $group, $value ) {
		self::parse_group_cookie();
		if ( ! isset( self::$groups[ $group ] ) ) {
			return false;
		}

		return ( null === $value ) || ( self::$groups[ $group ] === $value );
	}

	/**
	 * Returns the associated groups for the request.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return array  user's group-value pairs
	 */
	public static function get_user_groups() {
		self::parse_group_cookie();
		return self::$groups;
	}

	/**
	 * Sets the context of the the group segmentation to be encrypted or not.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param bool $value true for encrypted requests.
	 */
	public static function set_encryption( $value = true ) {
		static::$encryption_enabled = $value;
	}

	/**
	 * Returns the encryption flag
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return bool true if encryption is set for this request
	 */
	public static function is_encryption_enabled() {
		return static::$encryption_enabled;
	}

	/**
	 * Encrypts a string using the auth credentials for the site.
	 *
	 * @since   1.0.0
	 * @access  private
	 *
	 * @param string $value cookie text value.
	 * @throws string If credentials ENV Variables aren't defined.
	 * @return string encrypted version of string
	 */
	private static function encrypt_cookie_value( $value ) {
		// Validate that we have the secret values.
		if ( ! defined( 'VIP_GO_AUTH_COOKIE_KEY' ) || ! defined( 'VIP_GO_AUTH_COOKIE_IV' ) ) {
			// TODO: check that values are not empty.
			trigger_error( 'Secrets not defined for encrypted vary cookies', E_USER_WARNING );
			return;
		}

		$client_key = constant( 'VIP_GO_AUTH_COOKIE_KEY' );
		$client_iv = constant( 'VIP_GO_AUTH_COOKIE_IV' );
		$cookie_value = random_bytes( 32 ) . '|' . $value . '|' . ( time() + self::$cookie_expiry );
		$cipher_cookie = openssl_encrypt( $cookie_value, 'aes-128-cbc', $client_key, 0, $client_iv );

		return $cipher_cookie;
	}

	/**
	 * Decrypts a string using the auth credentials for the site.
	 *
	 * @since   1.0.0
	 * @access  private
	 *
	 * @param string $cookie_value the encrypted string.
	 * @throws string If credentials ENV Variables aren't defined.
	 * @return string decrypted version of string
	 */
	private static function decrypt_cookie_value( $cookie_value ) {
		// Validate that we have the secret values.
		if ( ! defined( 'VIP_GO_AUTH_COOKIE_KEY' ) || ! defined( 'VIP_GO_AUTH_COOKIE_IV' ) ) {
			// TODO: check that values are not empty.
			trigger_error( 'Secrets not defined for encrypted vary cookies', E_USER_WARNING );
			return;
		}

		$client_key = constant( 'VIP_GO_AUTH_COOKIE_KEY' );
		$client_iv = constant( 'VIP_GO_AUTH_COOKIE_IV' );
		$cipher_cookie = openssl_decrypt( $cookie_value, 'aes-128-cbc', $client_key, 0, $client_iv );
		$cookie_array = explode( '|', $cipher_cookie );
		// Parse out the group payload (2nd item).
		if ( count( $cookie_array ) < 2 ) {
			return null;
		}
		return $cookie_array [1];
	}

	/**
	 * Parses the text cookie into the local groups array of key-values.
	 *
	 * @since   1.0.0
	 * @access  private
	 */
	private static function parse_group_cookie() {
		if ( isset( $_COOKIE[ self::COOKIE_SEGMENT ] ) || isset( $_COOKIE[ self::COOKIE_AUTH ] ) ) {

			if ( self::is_encryption_enabled() ) {
				$cookie_value = str_replace( self::VERSION_PREFIX, '', $_COOKIE[ self::COOKIE_AUTH ] );
				$cookie_value = self::decrypt_cookie_value( $cookie_value );
			} else {
				$cookie_value = str_replace( self::VERSION_PREFIX, '', $_COOKIE[ self::COOKIE_SEGMENT ] );
			}

			$groups = explode( self::GROUP_SEPARATOR, $cookie_value );
			foreach ( $groups as $group ) {
				list( $group_name, $group_value ) = explode( self::VALUE_SEPARATOR, $group );
				self::$groups[ $group_name ] = $group_value ?? '';
			}
		}
	}


	/**
	 * Flattens the 2D array into a serialized string compatible with the cookie format.
	 *
	 * @since   1.0.0
	 * @access  private
	 *
	 * @returns string A string representation of the groups
	 */
	private static function stringify_groups() {
		ksort( self::$groups ); // make sure the string order is the same every time.
		$flatten = function ( $key, $value ) {
			return $key . self::VALUE_SEPARATOR . $value;
		};
		$flattened = array_map( $flatten, array_keys( self::$groups ), self::$groups );

		return implode( self::GROUP_SEPARATOR, $flattened );
	}

	/**
	 * Adjust the default cookie expiry
	 *
	 * @param int $expiry Seconds in the future when the cookie should expire (e.g. MONTH_IN_SECONDS). Must be more than 1 hour.
	 */
	public static function set_cookie_expiry( int $expiry ) {
		if ( $expiry < HOUR_IN_SECONDS ) {
			trigger_error( sprintf( '%s: cookie expiry must be greater than or equal to 1 hour (%d)', __METHOD__, HOUR_IN_SECONDS ), E_USER_WARNING );
			return;
		}
		self::$cookie_expiry = $expiry;
	}

	/**
	 * Add the vary cache headers to indicate that the response should be cached
	 */
	public static function add_vary_headers() {
		if ( ! empty( self::$groups ) ) {
			if ( self::is_encryption_enabled() ) {
				header( 'Vary: X-VIP-Go-Auth' );
			} else {
				header( 'Vary: X-VIP-Go-Segmentation' );
			}
		}
	}

	/**
	 * Wrapper for the set cookie function to control the TTL
	 *
	 * @param string $name  Cookie Name.
	 * @param string $value Cookie Value.
	 */
	private static function set_cookie( $name, $value ) {
		$expiry = time() + self::$cookie_expiry;
		setcookie( $name, self::VERSION_PREFIX . $value, $expiry, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Wrapper for the set cookie function to slear out the cookie
	 *
	 * @param string $name  Cookie Name.
	 */
	private static function unset_cookie( $name ) {
		setcookie( $name, '', time() - 3600 );
	}
}

// TODO: move?
add_action( 'send_headers', 'Automattic\Vip\Cache\Vary_Cache::add_vary_headers' );
