<?php

namespace Automattic\VIP\Cache;

use WP_Error;

class Vary_Cache {
	private const COOKIE_NO_CACHE = 'vip-go-cb';
	private const COOKIE_SEGMENT = 'vip-go-seg';
	private const COOKIE_AUTH = 'vip-go-auth';

	// Allowed values in cookie are alphanumerics (A-Za-z0-9) and underscore (_) and hyphen (-).
	private const GROUP_SEPARATOR = '---__';
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
	 * Set request to indicate the request will vary on one or more groups.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param  array $groups  One or more groups to vary on.
	 * @return boolean
	 */
	public static function register_groups( array $groups ) {
		foreach ( $groups as $group ) {
			$validate_result = self::validate_cookie_values( $group );
			if ( is_wp_error( $validate_result ) ) {
				trigger_error( sprintf( 'Failed to register group (%s) ; ', $group, $validate_result->get_error_message() ), E_USER_WARNING );
				continue;
			}

			self::$groups[ $group ] = '';
		}

		self::parse_group_cookie();

		return true;
	}

	/**
	 * Set request to indicate the request will vary on a group.
	 *
	 * Convenience version of `register_groups`.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param  string $groups A group to vary on.
	 * @return boolean
	 */
	public static function register_group( string $group ) {
		return self::register_groups( [ $group ] );
	}

	/**
	 * Clears out the groups and values
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public static function clear_groups() {
		self::$groups = [];
	}

	/**
	 * Assigns the user to given group and optionally a value for that group. E.g. location=US
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param  string $group  Group name to vary the request on.
	 * @param  string $value A value for the group.
	 * @return WP_Error|boolean
	 */
	public static function set_group_for_user( $group, $value ) {
		// TODO: make sure headers aren't already sent
		// TODO: only send header if we added or changed things
		// TODO: don't set the cookie if was already set on the request
		$validate_group_result = self::validate_cookie_values( $group );
		if ( is_wp_error( $validate_group_result ) ) {
			return new WP_Error( 'invalid_vary_group_name', sprintf( 'Failed to register group (%s): %s', $group, $validate_group_result->get_error_message() ) );
		}
		$validate_value_result = self::validate_cookie_values( $value );
		if ( is_wp_error( $validate_value_result ) ) {
			return new WP_Error( 'invalid_vary_group_segment', sprintf( 'Failed to register group segment (%s); %s ', $group, $validate_value_result->get_error_message() ) );
		}
		self::$groups[ $group ] = $value;
		if ( self::is_encryption_enabled() ) {
			$cookie_value = self::encrypt_cookie_value( self::stringify_groups() );
			self::set_cookie( self::COOKIE_AUTH, $cookie_value );
		} else {
			self::set_cookie( self::COOKIE_SEGMENT, self::stringify_groups() );
		}
		return true;
	}

	/**
	 * Checks if the request has a group cookie matching a given group, regardless of segment value.
	 *
	 * @param  string $group Group name.
	 *
	 * @return bool   True on success. False on failure.
	 */
	public static function is_user_in_group( $group ) {
		self::parse_group_cookie();
		// The group isn't defined, or the user isn't in it.
		if ( ! array_key_exists( $group, self::$groups ) || '' === trim( self::$groups[ $group ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if the request has a group cookie matching a given group and segment. e.g. 'dev-group', 'yes'
	 *
	 * @param  string $group Group name.
	 * @param  string $segment Which segment within the group to check.
	 *
	 * @return bool   True on success. False on failure.
	 */
	public static function is_user_in_group_segment( $group, $segment ) {
		self::parse_group_cookie();

		if ( ! self::is_user_in_group( $group ) ) {
			return false;
		}

		// Check for a specific group segment.
		return self::$groups[ $group ] === $segment;
	}


	/**
	 * Returns the associated groups for the request.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return array  user's group-value pairs
	 */
	public static function get_groups() {
		self::parse_group_cookie();
		return self::$groups;
	}

	/**
	 * Sets the context of the the group segmentation to be encrypted or not.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return WP_Error|null
	 */
	public static function enable_encryption() {

		// Validate that we have the secret values.
		if ( ( ! defined( 'VIP_GO_AUTH_COOKIE_KEY' ) || ! defined( 'VIP_GO_AUTH_COOKIE_IV' ) ||
			empty( constant( 'VIP_GO_AUTH_COOKIE_KEY' ) ) || empty( constant( 'VIP_GO_AUTH_COOKIE_IV' ) ) ) ) {
			return new WP_Error( 'vary-cache-secrets-not-defined', sprintf( 'Constants not defined for encrypted vary cache cookies (%s and %s)', 'VIP_GO_AUTH_COOKIE_KEY', 'VIP_GO_AUTH_COOKIE_IV' ) );
		}

		static::$encryption_enabled = true;
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
		if ( self::is_encryption_enabled() && ! empty( $_COOKIE[ self::COOKIE_AUTH ] ) ) {
			$cookie_value = self::decrypt_cookie_value( $_COOKIE[ self::COOKIE_AUTH ] );
		} elseif ( ! empty( $_COOKIE[ self::COOKIE_SEGMENT ] ) ) {
			$cookie_value = $_COOKIE[ self::COOKIE_SEGMENT ];
		}

		if ( empty( $cookie_value ) ) {
			return;
		}

		$cookie_value = str_replace( self::VERSION_PREFIX, '', $cookie_value );
		$groups = explode( self::GROUP_SEPARATOR, $cookie_value );
		foreach ( $groups as $group ) {
			list( $group_name, $group_value ) = explode( self::VALUE_SEPARATOR, $group );
			self::$groups[ $group_name ] = $group_value ?? '';
		}

	}


	/**
	 * Flattens the 2D array into a serialized string compatible with the cookie format.
	 *
	 * @since   1.0.0
	 * @access  private
	 *
	 * @return string A string representation of the groups
	 */
	private static function stringify_groups() {
		ksort( self::$groups ); // make sure the string order is the same every time.
		$flatten = function ( $key, $value ) {
			return $key . self::VALUE_SEPARATOR . $value;
		};
		$flattened = array_map( $flatten, array_keys( self::$groups ), self::$groups );

		return self::VERSION_PREFIX . implode( self::GROUP_SEPARATOR, $flattened );
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

			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				header( 'X-VIP-Go-Segmentation-Debug: ' . self::stringify_groups() );
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
		setcookie( $name, $value, $expiry, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Wrapper for the set cookie function to clear out the cookie
	 *
	 * @param string $name  Cookie Name.
	 */
	private static function unset_cookie( $name ) {
		setcookie( $name, '', time() - 3600 );
	}
	/**
	 * Only allow alphanumerics, dash and underscore
	 *
	 * @param string $value The string you want to test on.
	 * @return WP_Error|boolean
	 */
	private static function validate_cookie_values( $value ) {
		if ( preg_match( '/[^_0-9a-zA-Z-]+/', $value ) > 0 ) {
			return new WP_Error( 'vary_cache_group_invalid_chars', 'Invalid character(s). Can only use alphanumerics, dash and underscore' );
		}
		if ( strpos( $value, self::VALUE_SEPARATOR ) !== false || strpos( $value, self::GROUP_SEPARATOR ) !== false ) {
			return new WP_Error( 'vary_cache_group_cannot_use_delimiter', sprintf( 'Cannot use the delimiter values (`%s` or `%s`)', self::GROUP_SEPARATOR, self::VALUE_SEPARATOR ) );
		}
		return true;
	}
}

// TODO: move?
add_action( 'send_headers', 'Automattic\Vip\Cache\Vary_Cache::add_vary_headers' );
