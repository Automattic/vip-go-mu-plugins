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

define( 'CACHE_GROUP_LOGIN_LIMIT', 'login_limit' );
define( 'CACHE_GROUP_LOST_PASSWORD_LIMIT', 'lost_password_limit' );
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
 * Tracks and caches IP, IP|Username events, and Username events.
 * We're tracking IP, and IP|Username events for both login attempts and
 * password resets but only tracking Username events for password resets.
 *
 * @param string $username The username to track.
 * @param string $cache_group The cache group to track the $username to.
 * @param int $cache_expiry The number in seconds of the cache expiry.
 */
function wpcom_vip_track_auth_attempt( $username, $cache_group, $cache_expiry ) {
	// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
	$ip                    = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP, [ 'options' => [ 'default' => '' ] ] );
	$ip_username_cache_key = $ip . '|' . $username; // IP + username
	$ip_cache_key          = $ip; // IP only
	$username_cache_key    = $username; // Username only

	// Longer TTL when logging in as admin, which we don't allow on WP.com
	$is_restricted_username = wpcom_vip_is_restricted_username( $username );

	if ( $is_restricted_username ) {
		$cache_expiry = HOUR_IN_SECONDS + $cache_expiry;
	}

	wp_cache_add( $ip_username_cache_key, 0, $cache_group, $cache_expiry ); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
	wp_cache_add( $ip_cache_key, 0, $cache_group, HOUR_IN_SECONDS );
	wp_cache_add( $username_cache_key, 0, $cache_group, MINUTE_IN_SECONDS * 15 );
	wp_cache_incr( $ip_username_cache_key, 1, $cache_group );
	wp_cache_incr( $ip_cache_key, 1, $cache_group );
	wp_cache_incr( $username_cache_key, 1, $cache_group );
}

function wpcom_vip_login_limiter( $username ) {
	// Do some extra sanitization on the username.
	$username = vip_strict_sanitize_username( $username );

	wpcom_vip_track_auth_attempt( $username, CACHE_GROUP_LOGIN_LIMIT, MINUTE_IN_SECONDS * 5 );
}
add_action( 'wp_login_failed', 'wpcom_vip_login_limiter' );

function wpcom_vip_login_limiter_on_success( $username ) {
	// Do some extra sanitization on the username.
	$username = vip_strict_sanitize_username( $username );

	// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
	$ip                    = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP, [ 'options' => [ 'default' => '' ] ] );
	$ip_username_cache_key = $ip . '|' . $username; // IP + username
	$ip_cache_key          = $ip; // IP only

	wp_cache_decr( $ip_username_cache_key, 1, CACHE_GROUP_LOGIN_LIMIT );
	wp_cache_decr( $ip_cache_key, 1, CACHE_GROUP_LOGIN_LIMIT );
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
	header( "X-XMLRPC-Error-Code: {$error->code}" );
	return $error;
}
add_action( 'xmlrpc_login_error', 'wpcom_set_status_header_on_xmlrpc_failed_login_requests' );

function wpcom_vip_lost_password_limit( $errors ) {
	// Don't bother checking if we're already error-ing out
	if ( $errors->get_error_code() ) {
		return $errors;
	}

	// Do some sanitization on the username.
	// ignoring WordPress.Security.NonceVerification.Missing and WordPress.Security.ValidatedSanitizedInput.InputNotValidated below
	$username         = vip_strict_sanitize_username( $_POST['user_login'] ); // phpcs:ignore
	$is_login_limited = wpcom_vip_username_is_limited( $username, CACHE_GROUP_LOST_PASSWORD_LIMIT );

	if ( is_wp_error( $is_login_limited ) ) {
		$errors->add( $is_login_limited->get_error_code(), $is_login_limited->get_error_message() );
		return $errors;
	}

	wpcom_vip_track_auth_attempt( $username, CACHE_GROUP_LOST_PASSWORD_LIMIT, MINUTE_IN_SECONDS * 30 );

	return $errors;
}
add_action( 'lostpassword_post', 'wpcom_vip_lost_password_limit' );

function wpcom_vip_username_is_limited( $username, $cache_group ) {
	// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
	$ip = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP, [ 'options' => [ 'default' => '' ] ] );

	$ip_username_cache_key = $ip . '|' . $username;
	$ip_username_count     = wp_cache_get( $ip_username_cache_key, $cache_group );

	$ip_cache_key = $ip;
	$ip_count     = wp_cache_get( $ip_cache_key, $cache_group );

	$username_cache_key = $username;
	$username_count     = wp_cache_get( $username_cache_key, $cache_group );

	$is_restricted_username = wpcom_vip_is_restricted_username( $username );

	/**
	 * Login Limiting IP Username Threshold
	 *
	 * @param string $ip IP address of the login request
	 * @param string $username Username of the login request
	 */
	$ip_username_threshold = apply_filters( 'wpcom_vip_ip_username_login_threshold', 5, $ip, $username );

	/**
	 * Login Limiting IP Threshold
	 *
	 * @param string $ip IP address of the login request
	 */
	$ip_threshold = apply_filters( 'wpcom_vip_ip_login_threshold', 50, $ip );

	/**
	 * Login Limiting Username Threshold
	 * 
	 * @param string $username Username of the login request
	 */
	$username_threshold = 5 * $ip_username_threshold; // Default to 5 times the IP + username threshold
	$username_threshold = apply_filters( 'wpcom_vip_username_login_threshold', $username_threshold, $username );

	/**
	 * Change the thresholds for Password Resets
	 */
	if ( 'lost_password_limit' === $cache_group ) {
		/**
		 * Password Reset Limiting IP Username Threshold
		 *
		 * @param string $ip IP address of the password reset request
		 * @param string $username Username of the password reset request
		 */
		$ip_username_threshold = apply_filters( 'wpcom_vip_ip_username_password_reset_threshold', 3, $ip, $username );

		/**
		 * Password Reset IP Threshold
		 *
		 * @param string $ip IP address of the password reset request
		 */
		$ip_threshold = apply_filters( 'wpcom_vip_ip_password_reset_threshold', 3, $ip );

		/**
		 * Password Reset Username Threshold
		 * 
		 * @param string $username Username of the password reset request
		 */
		$username_threshold = 5 * $ip_username_threshold; // Default to 5 times the IP + username threshold
		$username_threshold = apply_filters( 'wpcom_vip_username_password_reset_threshold', $username_threshold, $username );
	} elseif ( $is_restricted_username ) {
		$ip_username_threshold = 2;
	}

	if ( $ip_username_count >= $ip_username_threshold || $ip_count >= $ip_threshold || $username_count >= $username_threshold ) {

		switch ( $cache_group ) {

			case CACHE_GROUP_LOST_PASSWORD_LIMIT:
				do_action( 'password_reset_limit_exceeded', $username );
				return new WP_Error( ERROR_CODE_LOST_PASSWORD_LIMIT_EXCEEDED, __( 'You have exceeded the password reset limit.  Please wait a few minutes and try again.' ) );

			case CACHE_GROUP_LOGIN_LIMIT:
				do_action( 'login_limit_exceeded', $username );
				return new WP_Error( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, __( 'You have exceeded the login limit.  Please wait a few minutes and try again.' ) );

		}
	}

	return false;
}

