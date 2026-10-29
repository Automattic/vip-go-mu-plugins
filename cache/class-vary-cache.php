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
	 * Flag to indicate if a group or nocache setter was called while serving a REST request.
	 *
	 * @access  private
	 * @var     bool
	 */
	private static $should_send_rest_headers = false;

	/**
	 * Member variable to store the parsed group values.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     array  Key - Group,  Value - group value
	 */
	private static $groups = [];

	/**
	 * Plaintext group string carried by the request's group cookie, if the cache layer read the same value.
	 *
	 * @access  private
	 * @var     array  Key - Cookie name, Value - group string
	 */
	private static $request_group_cookie = [];

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
		self::note_rest_write();

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
		self::note_rest_write();

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

		self::$request_group_cookie         = [];
		self::$did_send_headers             = false;
		self::$encryption_enabled           = false;
		self::$is_user_in_nocache           = false;
		self::$should_update_nocache_cookie = false;
		self::$should_update_group_cookie   = false;
		self::$should_send_rest_headers     = false;
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
		add_filter( 'rest_pre_serve_request', [ self::class, 'send_rest_headers' ], PHP_INT_MAX ); // REST requests exit before send_headers
	}

	/**
	 * Removes the custom filters
	 *
	 * @access private
	 */
	protected static function remove_filters() {
		remove_action( 'init', [ self::class, 'parse_cookies' ] );
		remove_action( 'send_headers', [ self::class, 'send_headers' ], PHP_INT_MAX );
		remove_filter( 'rest_pre_serve_request', [ self::class, 'send_rest_headers' ], PHP_INT_MAX );
	}

	/**
	 * Set request to indicate the request will vary on one or more groups.
	 *
	 * Registering a group resets its value for the current request. Register groups before `init` priority 10,
	 * when the visitor's cookie is parsed, so the parsed value isn't discarded.
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
	 * The group cookie is only sent if the user's groups differ from the request's group cookie.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param  string     $group  Group name to vary the request on.
	 * @param  string|int $value A value for the group.
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

		// Store what later requests will parse from the cookie, so a segment check gives the same answer on both.
		self::$groups[ $group ] = (string) $value;

		self::$should_update_group_cookie = true;
		self::note_rest_write();

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
	 * @param  string     $group Group name.
	 * @param  string|int $segment Which segment within the group to check.
	 *
	 * @return bool   True on success. False on failure.
	 */
	public static function is_user_in_group_segment( $group, $segment ) {
		if ( ! self::is_user_in_group( $group ) ) {
			return false;
		}

		// Check for a specific group segment. Group values are strings, so `1` matches the `1` parsed from the cookie.
		return is_scalar( $segment ) && self::$groups[ $group ] === (string) $segment;
	}

	/**
	 * Returns the user's value for a given group. e.g. 'yes' for 'dev-group'
	 *
	 * The value may come from the request's group cookie, so treat it as untrusted input.
	 *
	 * @access  public
	 *
	 * @param  string $group Group name.
	 *
	 * @return string|null  The group's value, or null if the user isn't in the group.
	 */
	public static function get_group_value( $group ): ?string {
		return static::is_user_in_group( $group ) ? self::$groups[ $group ] : null;
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
	 * Like the cache layer, rejects values that aren't plain base64, and payloads that aren't `random|groups|expiry`
	 * or whose expiry has passed.
	 *
	 * @param string $cookie_value the encrypted string.
	 * @throws string If credentials ENV Variables aren't defined.
	 * @return string|null The group payload, or null if the payload is malformed or expired.
	 */
	private static function decrypt_cookie_value( $cookie_value ) {
		// openssl_decrypt() skips characters outside the base64 alphabet, which the cache layer rejects.
		if ( ! preg_match( '#^[A-Za-z0-9+/]+={0,2}\z#', $cookie_value ) ) {
			return null;
		}

		$client_key    = constant( 'VIP_GO_AUTH_COOKIE_KEY' );
		$client_iv     = constant( 'VIP_GO_AUTH_COOKIE_IV' );
		$cipher_cookie = openssl_decrypt( $cookie_value, 'aes-128-cbc', $client_key, 0, $client_iv );
		$cookie_array  = explode( '|', $cipher_cookie );
		if ( 3 !== count( $cookie_array ) || ! is_numeric( $cookie_array[2] ) || (float) $cookie_array[2] <= time() ) {
			return null;
		}
		// Parse out the group payload (2nd item).
		return $cookie_array[1];
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
		self::$request_group_cookie = [];
		$cookie_name                = null;

		// If the cache layer supplies a decrypted segmentation header, use that instead of decrypting it again.
		if ( self::is_encryption_enabled() && ! empty( $_SERVER[ self::HEADER_AUTH ] ) ) {
			$cookie_name = self::COOKIE_AUTH;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$cookie_value = $_SERVER[ self::HEADER_AUTH ];
		} elseif ( self::is_encryption_enabled() && ! empty( $_COOKIE[ self::COOKIE_AUTH ] ) && isset( $_SERVER['HTTP_COOKIE'] ) ) {
			// If the cache layer didn't supply the header, decrypt the cookie here, applying the same checks.
			// The cache layer only sets the header for a cookie it can decrypt and whose embedded expiry hasn't passed,
			// and there's no cache layer in local development. Leave $cookie_name unset: a cookie the cache layer
			// didn't vouch for must not stop a fresh one from being sent.
			// $_COOKIE is automatically urldecoded, so we need to search through the $_SERVER version to get the unencoded one.
			$auth_cookie = (string) self::get_raw_cookie( self::COOKIE_AUTH );

			// Remove the site prefix at the beginning
			$prefix = constant( 'VIP_GO_APP_ID' ) . '.';
			if ( 0 === strpos( $auth_cookie, $prefix ) ) {
				$cookie_value = self::decrypt_cookie_value( substr( $auth_cookie, strlen( $prefix ) ) );
			}
		} elseif ( ! static::is_encryption_enabled() && ! empty( $_COOKIE[ self::COOKIE_SEGMENT ] ) ) {
			// The plaintext cookie is only read when encryption is off; encrypted requests ignore it.
			$cookie_name = self::COOKIE_SEGMENT;
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$cookie_value = $_COOKIE[ self::COOKIE_SEGMENT ];

			// PHP lets `vip-go-seg[]=x` replace a real `vip-go-seg` with an array. Read the real one, as the cache layer does.
			if ( ! is_string( $cookie_value ) ) {
				$cookie_value = self::get_raw_cookie( self::COOKIE_SEGMENT );
			}
		}

		if ( empty( $cookie_value ) ) {
			return;
		}

		if ( null !== $cookie_name ) {
			self::$request_group_cookie = [ $cookie_name => $cookie_value ];
		}

		$cookie_value = str_replace( self::VERSION_PREFIX, '', $cookie_value );
		$groups       = explode( self::GROUP_SEPARATOR, $cookie_value );
		foreach ( $groups as $group ) {
			if ( empty( $group ) || false === strpos( $group, self::VALUE_SEPARATOR ) ) {
				continue;
			}
			list( $group_name, $group_value ) = explode( self::VALUE_SEPARATOR, $group );
			self::$groups[ $group_name ]      = $group_value ?? '';
		}
	}

	/**
	 * Gets the first cookie with exactly this name from the raw Cookie header, without urldecoding it.
	 *
	 * @param string $name Cookie name.
	 * @return string|null The cookie value, or null if the request doesn't have the cookie.
	 */
	private static function get_raw_cookie( $name ) {
		if ( ! isset( $_SERVER['HTTP_COOKIE'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( explode( ';', $_SERVER['HTTP_COOKIE'] ) as $rawcookie ) {
			$parts = explode( '=', ltrim( $rawcookie ), 2 );
			if ( 2 === count( $parts ) && $name === $parts[0] ) {
				return $parts[1];
			}
		}

		return null;
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
	 * Sends headers on REST requests if REST code called a group or nocache setter.
	 *
	 * Setter calls made before the REST request started, such as on `init`, don't count: sites often make them on every
	 * request, and they have never been sent on REST requests. GET and HEAD requests are skipped, as the cache layer
	 * stores their responses, and so are OPTIONS requests, as browsers ignore cookies on preflight responses. The HTTP
	 * method decides, not the REST request's, which a `_method` override can change.
	 *
	 * @access  private
	 *
	 * @param mixed $served Whether the request has already been served.
	 * @return mixed Unchanged.
	 */
	public static function send_rest_headers( $served ) {
		$is_skipped_method = in_array( $_SERVER['REQUEST_METHOD'] ?? '', [ 'GET', 'HEAD', 'OPTIONS' ], true );

		if ( ! $is_skipped_method && self::$should_send_rest_headers ) {
			self::send_headers();
		}

		return $served;
	}

	/**
	 * Records a setter call made by REST code, i.e. after WordPress started serving a REST request.
	 */
	private static function note_rest_write() {
		if ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) ) {
			self::$should_send_rest_headers = true;
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
			$sent_cookie = self::set_group_cookie();
		}

		if ( self::$should_update_nocache_cookie ) {
			$sent_cookie = self::set_nocache_cookie() || $sent_cookie;
		}

		return $sent_cookie;
	}

	/**
	 * Checks whether the request has the active group cookie.
	 *
	 * Any value counts: PHP lets `vip-go-seg[]=x` replace a real `vip-go-seg` with an array, and
	 * parse_group_cookie() still reads the real one from the Cookie header, so it may need clearing.
	 *
	 * @return boolean Whether the request has the cookie.
	 */
	private static function has_group_cookie() {
		if ( self::is_encryption_enabled() ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			return isset( $_COOKIE[ self::COOKIE_AUTH ] ) || ! empty( $_SERVER[ self::HEADER_AUTH ] );
		}

		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		return isset( $_COOKIE[ self::COOKIE_SEGMENT ] );
	}

	/**
	 * Sets the group/segment cookie based on the user's current groupings.
	 *
	 * @return boolean Was a Set-Cookie header sent?
	 */
	private static function set_group_cookie() {
		$group_string = self::stringify_groups();
		$cookie_name  = self::is_encryption_enabled() ? self::COOKIE_AUTH : self::COOKIE_SEGMENT;
		if ( empty( $group_string ) ) {
			if ( ! self::has_group_cookie() ) {
				return false;
			}

			self::unset_cookie( $cookie_name );
			return true;
		}

		// Re-sending the cookie the request already carries would only make the response uncacheable.
		// Compare plaintext group strings: encrypted values embed a random nonce and an expiry.
		if ( ( self::$request_group_cookie[ $cookie_name ] ?? null ) === $group_string ) {
			/**
			 * Filters whether to re-send a group cookie whose groups are unchanged.
			 *
			 * Re-sending slides the cookie expiry forward, but the response won't be cached.
			 *
			 * @param bool  $refresh Whether to re-send the unchanged group cookie. Default false.
			 * @param array $groups  The user's group-value pairs.
			 */
			if ( ! apply_filters( 'vip_vary_cache_refresh_group_cookie', false, self::$groups ) ) {
				return false;
			}
		}

		if ( self::is_encryption_enabled() ) {
			$cookie_value = self::encrypt_cookie_value( $group_string );
			self::set_cookie( self::COOKIE_AUTH, VIP_GO_APP_ID . '.' . $cookie_value );
		} else {
			self::set_cookie( self::COOKIE_SEGMENT, $group_string );
		}

		return true;
	}

	/**
	 * Checks whether the request has the nocache cookie.
	 *
	 * @return boolean Whether the request has the cookie.
	 */
	private static function has_nocache_cookie() {
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		return isset( $_COOKIE[ self::COOKIE_NOCACHE ] );
	}

	/**
	 * Sets (or unsets) the nocache cookie.
	 *
	 * @return boolean Was a Set-Cookie header sent?
	 */
	private static function set_nocache_cookie() {
		if ( self::$is_user_in_nocache ) {
			self::set_cookie( self::COOKIE_NOCACHE, 1 );
			return true;
		}

		if ( ! self::has_nocache_cookie() ) {
			return false;
		}

		self::unset_cookie( self::COOKIE_NOCACHE );
		return true;
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
		$options = self::get_cookie_options( $name, time() + self::$cookie_expiry );
		// Need to use setrawcookie() here to prevent PHP from URLEncoding the base-64 terminator (==) on encrypted payloads
		setrawcookie( $name, $value, $options );
	}

	/**
	 * Wrapper for the set cookie function to clear out the cookie
	 *
	 * @param string $name Cookie Name.
	 */
	private static function unset_cookie( $name ) {
		$options = self::get_cookie_options( $name, time() - 3600 );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie( $name, '', $options );
	}

	/**
	 * Shared so a cookie is always cleared with the attributes it was set with.
	 *
	 * @param string $name    Cookie Name.
	 * @param int    $expires Unix timestamp; in the past to clear the cookie.
	 * @return array
	 */
	private static function get_cookie_options( $name, $expires ) {
		// Like core's logged_in cookie: a Secure cookie set over HTTPS on a site with an http:// home URL
		// would not be sent on its own links, and browsers would refuse to overwrite it from http://.
		$secure = is_ssl() && 'https' === wp_parse_url( get_option( 'home' ), PHP_URL_SCHEME );

		/**
		 * Whether to send the cookie with the Secure attribute.
		 *
		 * Browsers drop a Secure cookie sent over plain HTTP, so returning true there means the cookie is never set or cleared.
		 *
		 * @param bool   $secure Whether to set the Secure attribute. Defaults to true on HTTPS requests to a site with an HTTPS home URL.
		 * @param string $name   Cookie Name.
		 */
		$secure = (bool) apply_filters( 'vip_vary_cache_cookie_secure', $secure, $name );

		// No HttpOnly: sites may read or write the segmentation cookie from client-side code.
		return [
			'expires' => $expires,
			'path'    => (string) apply_filters( 'vip_vary_cache_cookie_path', COOKIEPATH ),
			'domain'  => (string) apply_filters( 'vip_vary_cache_cookie_domain', COOKIE_DOMAIN ),
			'secure'  => $secure,
		];
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
