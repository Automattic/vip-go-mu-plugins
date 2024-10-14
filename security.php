<?php

/*
Plugin Name: VIP Security
Description: Various security enhancements
Author: Automattic
Version: 1.2
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__

require_once __DIR__ . '/security/class-lockout.php';
require_once __DIR__ . '/security/machine-user.php';
require_once __DIR__ . '/security/class-private-sites.php';
require_once __DIR__ . '/security/login-error.php';
require_once __DIR__ . '/security/password.php';

if ( defined( 'VIP_SECURITY_INACTIVE_USERS_ACTION' ) ) {
	require_once __DIR__ . '/security/class-user-last-seen.php';

	$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
	$last_seen->init();
}

use Automattic\VIP\Utils\Context;

define( 'CACHE_GROUP_LOGIN_LIMIT', 'vip_login_limit' );
define( 'CACHE_GROUP_LOST_PASSWORD_LIMIT', 'vip_lost_password_limit' );
define( 'CACHE_KEY_LOCK_PREFIX', 'locked_' );
define( 'ERROR_CODE_LOGIN_LIMIT_EXCEEDED', 'login_limit_exceeded' );
define( 'ERROR_CODE_LOST_PASSWORD_LIMIT_EXCEEDED', 'lost_password_limit_exceeded' );

// If the site has any privacy restrictions (enabled by constant, ip restriction, http basic auth), initialize the Private_Sites module
if ( \Automattic\VIP\Security\Private_Sites::has_privacy_restrictions() ) {
	\Automattic\VIP\Security\Private_Sites::instance();
}

/**
 * Enforces strict username sanitization.
 *
 * @param string  $username
 * @return string
 */
function vip_strict_sanitize_username( $username ) {
	if ( ! is_scalar( $username ) ) {
		return '';
	}

	if ( is_email( $username ) ) {
		// We don't want to do this strict filter on email addresses. Sanitize the email and return.
		$username = sanitize_email( $username );
		return $username;
	}

	$username = sanitize_user( $username, true );

	return $username;
}

function wpcom_vip_is_restricted_username( $username ) {
	return 'admin' === $username
		|| WPCOM_VIP_MACHINE_USER_LOGIN === $username
		|| WPCOM_VIP_MACHINE_USER_EMAIL === $username;
}

/**
 * Return the defaults for login and password reset authorization limits, windows, and lockout periods.
 *
 * @internal The values are subject to change without notice.
 */
function _vip_get_auth_count_limit_defaults() {
	if ( Context::is_fedramp() ) {
		return [
			'ip_username_login'          => 3,
			'ip_login'                   => 5,
			'username_login'             => 10,
			'ip_username_password_reset' => 3,
			'ip_password_reset'          => 3,
			'username_password_reset'    => 15,

			'ip_username_window'         => MINUTE_IN_SECONDS * 15,
			'ip_window'                  => MINUTE_IN_SECONDS * 15,
			'username_window'            => MINUTE_IN_SECONDS * 15,

			'ip_username_lockout'        => MINUTE_IN_SECONDS * 30,
			'ip_lockout'                 => MINUTE_IN_SECONDS * 30,
			'username_lockout'           => MINUTE_IN_SECONDS * 30,
		];
	}


	return [
		'ip_username_login'          => 5,
		'ip_login'                   => 50,
		'username_login'             => 25,
		'ip_username_password_reset' => 3,
		'ip_password_reset'          => 3,
		'username_password_reset'    => 15,

		'ip_username_window'         => MINUTE_IN_SECONDS * 5,
		'ip_window'                  => MINUTE_IN_SECONDS * 60,
		'username_window'            => MINUTE_IN_SECONDS * 25,

		'ip_username_lockout'        => MINUTE_IN_SECONDS * 5,
		'ip_lockout'                 => MINUTE_IN_SECONDS * 60,
		'username_lockout'           => MINUTE_IN_SECONDS * 25,
	];
}

/**
 * Return the cache keys used for login and forgotten password rate limiting.
 *
 * @internal
 *
 * @param string $raw_username The username to use for the cache keys.
 * @return array Associative array of cache keys.
 */
function _vip_login_cache_keys( $raw_username ) {
	$username = vip_strict_sanitize_username( $raw_username );

	// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
	$ip = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP, [ 'options' => [ 'default' => '' ] ] );

	return [
		'ip_username_cache_key' => $ip . '|' . $username,
		'ip_cache_key'          => $ip,
		'username_cache_key'    => $username,
	];
}

/**
 * Check if counts for the given username and IP address are above the given thresholds.
 * If so, the account is locked for a period of time.
 *
 * @internal
 *
 * @param string $username The username to check.
 * @param string $cache_group The cache group to use for the rate limiting.
 */
function _vip_maybe_temporary_lock_account( $username, $cache_group ) {
	$cache_keys = _vip_login_cache_keys( $username );
	$defaults   = _vip_get_auth_count_limit_defaults();

	$ip_username_count = wp_cache_get( $cache_keys['ip_username_cache_key'], $cache_group );
	$ip_count          = wp_cache_get( $cache_keys['ip_cache_key'], $cache_group );
	$username_count    = wp_cache_get( $cache_keys['username_cache_key'], $cache_group );
	$ip                = $cache_keys['ip_cache_key'];
	$event_type        = CACHE_GROUP_LOST_PASSWORD_LIMIT === $cache_group ? 'password_reset' : 'login';

	/**
	 * Filters the threshold for limiting logins by IP + username combination.
	 *
	 * @param int    $threshold IP + Username combination login threshold.
	 * @param string $ip        IP address of the login request.
	 * @param string $username  Username of the login request.
	 */
	$ip_username_threshold = apply_filters( "wpcom_vip_ip_username_{$event_type}_threshold", $defaults[ "ip_username_{$event_type}" ], $ip, $username );

	/**
	 * Filters the threshold for limiting logins by IP.
	 *
	 * @param int    $threshold IP login threshold.
	 * @param string $ip        IP address of the login request.
	 */
	$ip_threshold = apply_filters( "wpcom_vip_ip_{$event_type}_threshold", $defaults[ "ip_{$event_type}" ], $ip );

	/**
	 * Filters the threshold for limiting logins by username.
	 *
	 * @param int    $threshold Username login threshold.
	 * @param string $username  Username of the login request.
	 */
	$username_threshold = apply_filters( "wpcom_vip_username_{$event_type}_threshold", $defaults[ "username_{$event_type}" ], $username );

	$is_restricted_username = wpcom_vip_is_restricted_username( $username );
	if ( $is_restricted_username ) {
		$ip_username_threshold = 2;
	}

	if ( $ip_username_count >= $ip_username_threshold || $ip_count >= $ip_threshold || $username_count >= $username_threshold ) {
		/**
		 * Fires when a login limit or password reset is exceeded.
		 *
		 * @param string $username Username of the request.
		 */
		do_action( "{$event_type}_limit_exceeded", $username );

		$lock_reason = 'username';
		if ( $ip_username_count >= $ip_username_threshold ) {
			$lock_reason = 'ip_username';
		} elseif ( $ip_count >= $ip_threshold ) {
			$lock_reason = 'ip';
		}

		$default_lockout = $defaults[ "{$lock_reason}_lockout" ];
		/**
		 * Filters the lenght of locked out time.
		 * vip_<login|password_reset>_<ip|ip_username|username>_lockout
		 *
		 * @param int    $lock_interval Seconds count of the lockout.
		 * @param string $username      Username of the request.
		 */
		$lock_interval = apply_filters( "vip_{$event_type}_{$lock_reason}_lockout", $default_lockout, $username );


		wp_cache_set( CACHE_KEY_LOCK_PREFIX . $cache_keys[ "{$lock_reason}_cache_key" ], true, $cache_group, $lock_interval ); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
	}
}


/**
 * Tracks and caches IP, IP|Username events, and Username events.
 * We're tracking IP, and IP|Username events for both login attempts and
 * password resets but only tracking Username events for password resets.
 *
 * @param string $username The username to track.
 * @param string $cache_group The cache group to track the $username to.
 */
function wpcom_vip_track_auth_attempt( $username, $cache_group ) {
	$cache_keys = _vip_login_cache_keys( $username );
	$defaults   = _vip_get_auth_count_limit_defaults();

	foreach ( [ 'ip', 'ip_username', 'username' ] as $type ) {
		$event_type = CACHE_GROUP_LOST_PASSWORD_LIMIT === $cache_group ? 'password_reset' : 'login';
		$default    = $defaults[ "{$type}_window" ];

		/**
		 * Filters the window for tracking login attempts.
		 * vip_<login|password_reset>_<ip|ip_username|username>_window
		 *
		 * @param int    $window   Seconds count in the window.
		 * @param string $username Username of the request.
		 */
		$event_window = apply_filters( "vip_{$event_type}_{$type}_window", $default, $username );

		wp_cache_add( $cache_keys[ "{$type}_cache_key" ], 0, $cache_group, $event_window ); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_incr( $cache_keys[ "{$type}_cache_key" ], 1, $cache_group );
	}

	_vip_maybe_temporary_lock_account( $username, $cache_group );
}

add_filter( 'vip_login_ip_username_window', function ( $window, $username ) {
	if ( wpcom_vip_is_restricted_username( $username ) ) {
		// Longer, more-strict interval when logging in as admin
		return HOUR_IN_SECONDS + $window;
	}

	return $window;
}, 10, 2 );

function wpcom_vip_login_limiter( $username ) {
	wpcom_vip_track_auth_attempt( $username, CACHE_GROUP_LOGIN_LIMIT );
}
add_action( 'wp_login_failed', 'wpcom_vip_login_limiter' );

function wpcom_vip_login_limiter_on_success( $username ) {
	$cache_keys = _vip_login_cache_keys( $username );

	wp_cache_decr( $cache_keys['ip_username_cache_key'], 1, CACHE_GROUP_LOGIN_LIMIT );
	wp_cache_decr( $cache_keys['ip_cache_key'], 1, CACHE_GROUP_LOGIN_LIMIT );
}
add_action( 'wp_login', 'wpcom_vip_login_limiter_on_success' );

function wpcom_vip_limit_logins_for_restricted_usernames( $user, $username ) {
	$is_restricted_username = wpcom_vip_is_restricted_username( $username );
	if ( $is_restricted_username ) {
		return new WP_Error( 'restricted-login', 'Logins are restricted for that user. Please try a different user account.' );
	}

	return $user;
}
add_filter( 'authenticate', 'wpcom_vip_limit_logins_for_restricted_usernames', 30, 2 ); // core authenticates on 20

function wpcom_vip_login_limiter_authenticate( $user, $username, $password ) {
	if ( empty( $username ) && empty( $password ) ) {
		return $user;
	}

	// Do some extra sanitization on the username.
	$username = vip_strict_sanitize_username( $username );

	$is_login_limited = wpcom_vip_username_is_limited( $username, CACHE_GROUP_LOGIN_LIMIT );
	if ( is_wp_error( $is_login_limited ) ) {
		return $is_login_limited;
	}

	return $user;
}
add_filter( 'authenticate', 'wpcom_vip_login_limiter_authenticate', 30, 3 ); // core authenticates on 20

function wpcom_vip_login_limit_dont_show_login_form() {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'post' != strtolower( $_SERVER['REQUEST_METHOD'] ) || ! isset( $_POST['log'] ) ) {
		return;
	}

	// Do some sanitization on the username.
	$username = vip_strict_sanitize_username( $_POST['log'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$error    = wpcom_vip_username_is_limited( $username, CACHE_GROUP_LOGIN_LIMIT );
	if ( is_wp_error( $error ) ) {
		login_header( __( 'Error' ), '', $error );
		login_footer();
		exit;
	}
}
add_action( 'login_form_login', 'wpcom_vip_login_limit_dont_show_login_form' );


function wpcom_vip_login_limit_xmlrpc_error( $error, $user ) {
	static $login_limit_error;

	if ( is_wp_error( $user ) && ERROR_CODE_LOGIN_LIMIT_EXCEEDED === $user->get_error_code() ) {
		// We need to set a persistent error here, as once there is an auth error in a system.multicall, core will no longer trigger any of the rate limit filters for further login attempts in the set.
		$login_limit_error = $user;
	}

	if ( is_wp_error( $login_limit_error ) ) {
		return new IXR_Error( 429, $login_limit_error->get_error_message() );
	}

	return $error;
}
add_filter( 'xmlrpc_login_error', 'wpcom_vip_login_limit_xmlrpc_error', 10, 2 );

function wpcom_set_status_header_on_xmlrpc_failed_login_requests( $error ) {
	if ( ! headers_sent() ) {
		header( "X-XMLRPC-Error-Code: {$error->code}" );
	}

	return $error;
}
add_action( 'xmlrpc_login_error', 'wpcom_set_status_header_on_xmlrpc_failed_login_requests' );

/**
 * @param WP_Error $errors
 * @param WP_User|false $user_data
 */
function wpcom_vip_lost_password_limit( $errors, $user_data ) {
	// Don't bother checking if we're already error-ing out
	if ( $errors->has_errors() ) {
		return $errors;
	}

	if ( false !== $user_data ) {
		$username = $user_data->user_login;
	} elseif ( ! empty( $_POST['user_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- vip_strict_sanitize_username does sanitization
		$username = vip_strict_sanitize_username( wp_unslash( $_POST['user_login'] ) );
	} else {
		return $errors;
	}

	$is_login_limited = wpcom_vip_username_is_limited( $username, CACHE_GROUP_LOST_PASSWORD_LIMIT );

	if ( is_wp_error( $is_login_limited ) ) {
		$errors->add( $is_login_limited->get_error_code(), $is_login_limited->get_error_message() );
		return $errors;
	}

	wpcom_vip_track_auth_attempt( $username, CACHE_GROUP_LOST_PASSWORD_LIMIT );

	return $errors;
}
add_action( 'lostpassword_post', 'wpcom_vip_lost_password_limit', 10, 2 );

function wpcom_vip_username_is_limited( $username, $cache_group ) {
	$cache_keys = _vip_login_cache_keys( $username );

	$is_ip_username_locked = wp_cache_get( CACHE_KEY_LOCK_PREFIX . $cache_keys['ip_username_cache_key'], $cache_group );
	$is_ip_locked          = wp_cache_get( CACHE_KEY_LOCK_PREFIX . $cache_keys['ip_cache_key'], $cache_group );
	$is_username_locked    = wp_cache_get( CACHE_KEY_LOCK_PREFIX . $cache_keys['username_cache_key'], $cache_group );
	if ( $is_ip_username_locked || $is_ip_locked || $is_username_locked ) {

		switch ( $cache_group ) {

			case CACHE_GROUP_LOST_PASSWORD_LIMIT:
				return new WP_Error( ERROR_CODE_LOST_PASSWORD_LIMIT_EXCEEDED, __( 'You have exceeded the password reset limit.  Please wait a few minutes and try again.' ) );

			case CACHE_GROUP_LOGIN_LIMIT:
				return new WP_Error( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, __( 'You have exceeded the login limit.  Please wait a few minutes and try again.' ) );

		}
	}

	return false;
}

/**
 * Sends a header with the username of the current user for our logs.
 *
 * @param int|false $user User ID
 * @return int|false $user User ID
 */
function vip_send_wplogin_header( $user ) {
	if ( $user && isset( $_SERVER['HTTP_X_WPLOGIN'] ) && 'yes' === $_SERVER['HTTP_X_WPLOGIN'] && class_exists( 'WP_User' ) ) {
		$_user = new WP_User( $user );
		if ( isset( $_user->user_login ) ) {
			header( 'X-wplogin: ' . $_user->user_login );
		}
		unset( $_SERVER['HTTP_X_WPLOGIN'] );
	}
	return $user;
}
add_filter( 'determine_current_user', 'vip_send_wplogin_header', 10000 );
