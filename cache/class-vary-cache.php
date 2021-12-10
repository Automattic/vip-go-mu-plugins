<?php

namespace Automattic\VIP\Cache;

use WP_Error;

class Vary_Cache {
	const COOKIE_NOCACHE = 'vip-go-cb';
	const COOKIE_SEGMENT = 'vip-go-seg';
	const COOKIE_AUTH    = 'vip-go-auth';
	const HEADER_AUTH    = 'HTTP_X_VIP_GO_AUTH';

	// Allowed values in cookie are alphanumerics (A-Za-z0-9) and underscore (_) and hyphen (-).
	const GROUP_SEPARATOR = '---__';
	const VALUE_SEPARATOR = '_--_';
	const VERSION_PREFIX  = 'vc-v1__';

	/**
	 * Flag to indicate if this an encrypted group request
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     bool  true if encrypted
	 */
	private static $encryption_enabled = false;

	/**
	 * Flag to indicate if the send_headers action was triggered
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     bool  true if headers were sent
	 */
	private static $did_send_headers = false;

	/**
	 * Flag to indicate if we're in nocache mode.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     bool  true if nocache enabled
	 */
	private static $is_user_in_nocache = false;

	/**
	 * Flag to indicate if we should update the nocache cookie.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     bool
	 */
	private static $should_update_nocache_cookie = false;

	/**
	 * Flag to indicate if we should update the group/segment cookie
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     bool
	 */
	private static $should_update_group_cookie = false;

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

	/**
	 * Check if the user is in nocache mode.
	 *
	 * Should only be used after the `init` hook.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return boolean
	 */
	public static function is_user_in_nocache() {
		return (bool) self::$is_user_in_nocache;
	}

	/**
	 * Add nocache cookie for the user.
	 *
	 * This bypasses all requests from the VIP Cache.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return boolean|WP_Error
	 */
	public static function set_nocache_for_user() {
		if ( self::$did_send_headers ) {
			return new WP_Error( 'did_send_headers', 'Failed to set nocache cookie; cannot be called after the `send_headers` hook has fired.' );
		}

		self::$is_user_in_nocache           = true;
		self::$should_update_nocache_cookie = true;

		return true;
	}

	/**
	 * Clears the nocache cookie for the user.
	 *
	 * Restores caching behaviour for all future requests.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return boolean|WP_Error
	 */
	public static function remove_nocache_for_user() {
		if ( self::$did_send_headers ) {
			return new WP_Error( 'did_send_headers', 'Failed to remove nocache cookie; cannot be called after the `send_headers` hook has fired.' );
		}

		self::$is_user_in_nocache           = false;
		self::$should_update_nocache_cookie = true;

		return true;
	}

	/**
	 * Convenience function to init the class.
	 *
	 * @access private
	 */
	public static function load() {
		self::clear_groups();
		self::add_filters();
	}

	/**
	 * Convenience function to reset the class.
	 *
	 * Primarily used to unit tests.
	 *
	 * @access private
	 */
	public static function unload() {
		self::remove_filters();

		self::clear_groups();

		self::$did_send_headers             = false;
		self::$encryption_enabled           = false;
		self::$is_user_in_nocache           = false;
		self::$should_update_nocache_cookie = false;
		self::$should_update_group_cookie   = false;
		self::$cookie_expiry                = MONTH_IN_SECONDS;
	}

	/**
	 * Adds custom filters required at the beginning and end of the plugin lifecycle
	 *
	 * @access private
	 */
	protected static function add_filters() {
		add_action( 'init', [ self::class, 'parse_cookies' ] );
		add_action( 'send_headers', [ self::class, 'send_headers' ], PHP_INT_MAX ); // run late to catch any changes that may happen earlier in send_headers
	}

	/**
	 * Removes the custom filters
	 *
	 * @access private
	 */
	protected static function remove_filters() {
		remove_action( 'init', [ self::class, 'parse_cookies' ] );
		remove_action( 'send_headers', [ self::class, 'send_headers' ], PHP_INT_MAX );
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
		if ( self::$did_send_headers ) {
			trigger_error( sprintf( 'Failed to register_groups (%s); cannot be called after the `send_headers` hook has fired.', esc_html( implode( ', ', $groups ) ) ), E_USER_WARNING );
			return false;
		}

		foreach ( $groups as $group ) {
			$validate_result = self::validate_cookie_value( $group );
			if ( is_wp_error( $validate_result ) ) {
				trigger_error( sprintf( 'Failed to register group (%s); %s', esc_html( $group ), esc_html( $validate_result->get_error_message() ) ), E_USER_WARNING );
				continue;
			}

			self::$groups[ $group ] = '';
		}

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
	 * @param  string $group A group to vary on.
	 * @return boolean
	 */
	public static function register_group( string $group ) {
		return self::register_groups( [ $group ] );
	}

	/**
	 * Clears out the groups and values.
	 */
	private static function clear_groups() {
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
		if ( self::$did_send_headers ) {
			return new WP_Error( 'did_send_headers', sprintf( 'Failed to set group (%s => %s) for user; cannot be called after the `send_headers` hook has fired.', $group, $value ) );
		}

		$validate_group_result = self::validate_cookie_value( $group );
		if ( is_wp_error( $validate_group_result ) ) {
			return new WP_Error( 'invalid_vary_group_name', sprintf( 'Failed to set group (%s): %s', $group, $validate_group_result->get_error_message() ) );
		}

		$validate_value_result = self::validate_cookie_value( $value );
		if ( is_wp_error( $validate_value_result ) ) {
			return new WP_Error( 'invalid_vary_group_segment', sprintf( 'Failed to set group segment (%s): %s', $group, $validate_value_result->get_error_message() ) );
		}

		if ( ! array_key_exists( $group, self::$groups ) ) {
			return new WP_Error( 'invalid_vary_group_notregistered', sprintf( 'Failed to set group (%s): Must register the group with register_group( <groupname> ) first. ', $group ) );
		}

		self::$groups[ $group ] = $value;

		self::$should_update_group_cookie = true;

		return true;
	}

	/**
	 * Checks if the request has a group cookie matching a given group, regardless of segment value.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param  string $group Group name.
	 *
	 * @return bool   True on success. False on failure.
	 */
	public static function is_user_in_group( $group ) {
		// The group isn't defined, or the user isn't in it.
		if ( ! array_key_exists( $group, self::$groups ) || '' === trim( self::$groups[ $group ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if the request has a group cookie matching a given group and segment. e.g. 'dev-group', 'yes'
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param  string $group Group name.
	 * @param  string $segment Which segment within the group to check.
	 *
	 * @return bool   True on success. False on failure.
	 */
	public static function is_user_in_group_segment( $group, $segment ) {
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
		if ( ! defined( 'VIP_GO_AUTH_COOKIE_KEY' ) || ! defined( 'VIP_GO_AUTH_COOKIE_IV' ) ||
			empty( constant( 'VIP_GO_AUTH_COOKIE_KEY' ) ) || empty( constant( 'VIP_GO_AUTH_COOKIE_IV' ) ) ) {
			trigger_error( 'Vary_Cache: Cannot enable encryption because the required constants (VIP_GO_AUTH_COOKIE_KEY and VIP_GO_AUTH_COOKIE_IV) are not defined correctly. Please contact VIP Support for assistance.', E_USER_ERROR );
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
	 * @param string $value cookie text value.
	 * @throws string If credentials ENV Variables aren't defined.
	 * @return string encrypted version of string
	 */
	private static function encrypt_cookie_value( $value ) {
		$client_key    = constant( 'VIP_GO_AUTH_COOKIE_KEY' );
		$client_iv     = constant( 'VIP_GO_AUTH_COOKIE_IV' );
		$cookie_value  = random_int( 0, PHP_INT_MAX ) . '|' . $value . '|' . ( time() + self::$cookie_expiry );
		$cipher_cookie = openssl_encrypt( $cookie_value, 'aes-128-cbc', $client_key, 0, $client_iv );

		return $cipher_cookie;
	}

	/**
	 * Decrypts a string using the auth credentials for the site.
	 *
	 * @param string $cookie_value the encrypted string.
	 * @throws string If credentials ENV Variables aren't defined.
	 * @return string decrypted version of string
	 */
	private static function decrypt_cookie_value( $cookie_value ) {
		$client_key    = constant( 'VIP_GO_AUTH_COOKIE_KEY' );
		$client_iv     = constant( 'VIP_GO_AUTH_COOKIE_IV' );
		$cipher_cookie = openssl_decrypt( $cookie_value, 'aes-128-cbc', $client_key, 0, $client_iv );
		$cookie_array  = explode( '|', $cipher_cookie );
		// Parse out the group payload (2nd item).
		if ( count( $cookie_array ) < 2 ) {
			return null;
		}
		return $cookie_array [1];
	}

	/**
	 * Parses our nocache and group cookies.
	 *
	 * @since   1.0.0
	 * @access  private
	 */
	public static function parse_cookies() {
		self::parse_nocache_cookie();
		self::parse_group_cookie();
	}

	/**
	 * Parses the nocache cookie to see if nocache mode is enabled.
	 */
	private static function parse_nocache_cookie() {
		if ( isset( $_COOKIE[ self::COOKIE_NOCACHE ] ) ) {
			self::$is_user_in_nocache = true;
		} else {
			self::$is_user_in_nocache = false;
		}
	}

	/**
	 * Parses the group/segment cookie into the local groups array of key-values.
	 */
	private static function parse_group_cookie() {
		// If the cache layer supplies a decrypted segmentation header, use that instead of decrypting it again.
		if ( self::is_encryption_enabled() && ! empty( $_SERVER[ self::HEADER_AUTH ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$cookie_value = $_SERVER[ self::HEADER_AUTH ];
		} elseif ( self::is_encryption_enabled() && ! empty( $_COOKIE[ self::COOKIE_AUTH ] ) && isset( $_SERVER['HTTP_COOKIE'] ) ) {
			// If the header auth isn't set (in case of a logged-in user), fall back to decrypting the cookie itself.
			$auth_cookie = null;
			// $_COOKIE is automatically urldecoded, so we need to search through the $_SERVER version to get the unencoded one.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( explode( '; ', $_SERVER['HTTP_COOKIE'] ) as $rawcookie ) {
				list( $k, $v ) = explode( '=', $rawcookie, 2 );
				if ( self::COOKIE_AUTH === $k ) {
					$auth_cookie = $v;
					break;
				}
			}

			// Remove the site prefix at the beginning
			$prefix = constant( 'VIP_GO_APP_ID' ) . '.';
			if ( 0 === strpos( $auth_cookie, $prefix ) ) {
				$value = substr( $auth_cookie, strlen( $prefix ) );
			}
			$cookie_value = self::decrypt_cookie_value( $value );

		} elseif ( ! empty( $_COOKIE[ self::COOKIE_SEGMENT ] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$cookie_value = $_COOKIE[ self::COOKIE_SEGMENT ];
		}

		if ( empty( $cookie_value ) ) {
			return;
		}

		$cookie_value = str_replace( self::VERSION_PREFIX, '', $cookie_value );
		$groups       = explode( self::GROUP_SEPARATOR, $cookie_value );
		foreach ( $groups as $group ) {
			if ( empty( $group ) ) {
				continue;
			}
			list( $group_name, $group_value ) = explode( self::VALUE_SEPARATOR, $group );
			self::$groups[ $group_name ]      = $group_value ?? '';
		}
	}

	/**
	 * Flattens the 2D array into a serialized string compatible with the cookie format.
	 *
	 * @return string A string representation of the groups
	 */
	private static function stringify_groups() {
		if ( empty( self::$groups ) ) {
			return '';
		}

		ksort( self::$groups ); // make sure the string order is the same every time.
		$flattened = [];
		foreach ( self::$groups as $key => $value ) {
			if ( '' === trim( $value ) ) {
				continue;
			}
			$flattened[] = $key . self::VALUE_SEPARATOR . $value;
		}

		if ( empty( $flattened ) ) {
			return '';
		}

		return self::VERSION_PREFIX . implode( self::GROUP_SEPARATOR, $flattened );
	}

	/**
	 * Adjust the default cookie expiry.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param int $expiry Seconds in the future when the cookie should expire (e.g. MONTH_IN_SECONDS). Must be more than 1 hour.
	 */
	public static function set_cookie_expiry( int $expiry ) {
		if ( $expiry < HOUR_IN_SECONDS ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HOUR_IN_SECONDS is safe as it is an integer value
			trigger_error( sprintf( '%s: cookie expiry must be greater than or equal to 1 hour (%d)', __METHOD__, HOUR_IN_SECONDS ), E_USER_WARNING );
			return;
		}

		self::$cookie_expiry = $expiry;
	}

	/**
	 * Sends headers (if needed).
	 *
	 * @since   1.0.0
	 * @access  private
	 */
	public static function send_headers() {
		if ( ! self::$did_send_headers ) {
			self::$did_send_headers = true;

			$sent_vary   = self::send_vary_headers();
			$sent_cookie = self::set_cookies();

			if ( $sent_vary || $sent_cookie ) {
				/**
				 * Vary or Set-Cookie header were sent.
				 *
				 * Can be used to handle things like early redirects after a user action and group assignment.
				 *
				 * @since 1.0.0
				 *
				 * @param boolean $sent_vary Was a Vary header sent?
				 * @param boolean $sent_cookie Was a Set-Cookie header sent?
				 */
				do_action( 'vip_vary_cache_did_send_headers', $sent_vary, $sent_cookie );
			}
		}
	}

	/**
	 * Determines if cookies need to be set and then sets them.
	 *
	 * @return boolean Was at least one cookie set?
	 */
	private static function set_cookies() {
		$sent_cookie = false;

		if ( self::$should_update_group_cookie ) {
			$sent_cookie = true;

			self::set_group_cookie();
		}

		if ( self::$should_update_nocache_cookie ) {
			$sent_cookie = true;

			self::set_nocache_cookie();
		}

		return $sent_cookie;
	}

	/**
	 * Sets the group/segment cookie based on the user's current groupings.
	 */
	private static function set_group_cookie() {
		$group_string = self::stringify_groups();
		if ( empty( $group_string ) ) {
			return;
		}

		if ( self::is_encryption_enabled() ) {
			$cookie_value = self::encrypt_cookie_value( $group_string );
			self::set_cookie( self::COOKIE_AUTH, VIP_GO_APP_ID . '.' . $cookie_value );
		} else {
			self::set_cookie( self::COOKIE_SEGMENT, $group_string );
		}
	}

	/**
	 * Sets (or unsets) the group/segment cookie.
	 */
	private static function set_nocache_cookie() {
		if ( self::$is_user_in_nocache ) {
			self::set_cookie( self::COOKIE_NOCACHE, 1 );
		} else {
			self::unset_cookie( self::COOKIE_NOCACHE );
		}
	}

	/**
	 * Add the vary cache headers to indicate that the response should be cached
	 *
	 * @return boolean Was at least one cookie set?
	 */
	private static function send_vary_headers() {
		$sent_vary = false;

		if ( ! empty( self::$groups ) ) {
			$sent_vary = true;

			if ( self::is_encryption_enabled() ) {
				header( 'Vary: X-VIP-Go-Auth', false );
			} else {
				header( 'Vary: X-VIP-Go-Segmentation', false );
			}

			if ( defined( 'WP_DEBUG' ) && true === constant( 'WP_DEBUG' ) ) {
				header( 'X-VIP-Go-Segmentation-Debug: ' . self::stringify_groups() );
			}
		}

		return $sent_vary;
	}

	/**
	 * Wrapper for the set cookie function to control the TTL
	 *
	 * @param string $name  Cookie Name.
	 * @param string $value Cookie Value.
	 */
	private static function set_cookie( $name, $value ) {
		$expiry = time() + self::$cookie_expiry;
		// Need to use setrawcookie() here to prevent PHP from URLEncoding the base-64 terminator (==) on encrypted payloads
		setrawcookie( $name, $value, $expiry, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Wrapper for the set cookie function to clear out the cookie
	 *
	 * @param string $name  Cookie Name.
	 */
	private static function unset_cookie( $name ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie( $name, '', time() - 3600 );
	}
	/**
	 * Only allow alphanumerics, dash and underscore
	 *
	 * @param string $value The string you want to test on.
	 * @return WP_Error|boolean
	 */
	private static function validate_cookie_value( $value ) {
		if ( preg_match( '/[^_0-9a-zA-Z-]+/', $value ) > 0 ) {
			return new WP_Error( 'vary_cache_group_invalid_chars', 'Invalid character(s). Can only use alphanumerics, dash and underscore' );
		}

		if ( strpos( $value, self::VALUE_SEPARATOR ) !== false || strpos( $value, self::GROUP_SEPARATOR ) !== false ) {
			return new WP_Error( 'vary_cache_group_cannot_use_delimiter', sprintf( 'Cannot use the delimiter values (`%s` or `%s`)', self::GROUP_SEPARATOR, self::VALUE_SEPARATOR ) );
		}

		return true;
	}
}

Vary_Cache::load();
