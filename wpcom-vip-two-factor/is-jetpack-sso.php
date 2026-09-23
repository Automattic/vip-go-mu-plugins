<?php

// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE

namespace Automattic\VIP\TwoFactor;

// muplugins_loaded fires before cookie constants are set
if ( is_multisite() ) {
	ms_cookie_constants();
}

wp_cookie_constants();

define( 'VIP_IS_JETPACK_SSO_COOKIE', AUTH_COOKIE . '_vip_jetpack_sso' );
define( 'VIP_IS_JETPACK_SSO_2SA_COOKIE', AUTH_COOKIE . '_vip_jetpack_sso_2sa' );

/** Sign an SSO assertion for one WordPress login session and one purpose. */
function generate_sso_assertion( $user_id, $expiration, $token, $purpose ) {
	$claim     = '1|' . (int) $user_id . '|' . (int) $expiration;
	$signature = hash_hmac( 'sha256', $purpose . '|' . $claim . '|' . $token, wp_salt( 'auth' ) );

	return $claim . '|' . $signature;
}

/** Get the token from a cookie that actually authenticates the current user. */
function current_auth_session_token( $user_id ) {
	foreach ( [ 'secure_auth', 'auth', 'logged_in' ] as $scheme ) {
		$cookie = wp_parse_auth_cookie( '', $scheme );
		if ( ! $cookie || empty( $cookie['token'] ) ) {
			continue;
		}

		if ( (int) wp_validate_auth_cookie( '', $scheme ) === $user_id ) {
			return $cookie['token'];
		}
	}

	return false;
}

/** Validate a dedicated assertion against the current user and login session. */
function validate_sso_assertion( $cookie_name, $purpose ) {
	$user_id = get_current_user_id();
	if ( ! $user_id || ! isset( $_COOKIE[ $cookie_name ] ) || ! is_string( $_COOKIE[ $cookie_name ] ) ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The complete signed value is verified below.
	$parts = explode( '|', $_COOKIE[ $cookie_name ] );
	if ( 4 !== count( $parts ) || '1' !== $parts[0] || (string) $user_id !== $parts[1]
		|| ! ctype_digit( $parts[2] ) || (int) $parts[2] <= time() || ! preg_match( '/^[a-f0-9]{64}$/D', $parts[3] ) ) {
		return false;
	}

	$token = current_auth_session_token( $user_id );
	if ( ! $token ) {
		return false;
	}

	$expected = generate_sso_assertion( $user_id, (int) $parts[2], $token, $purpose );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The complete signed value is compared here.
	return hash_equals( $expected, $_COOKIE[ $cookie_name ] ) ? $user_id : false;
}

add_action( 'jetpack_sso_handle_login', function ( $user, $user_data ) {
	if ( ! $user instanceof \WP_User ) {
		return;
	}

	add_action( 'set_auth_cookie', function ( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ) use ( $user, $user_data ) {
		if ( (int) $user->ID !== (int) $user_id || ! $token ) {
			return;
		}

		$secure = is_ssl();

		$sso_cookie = generate_sso_assertion( $user_id, $expiration, $token, 'jetpack_sso' );
		setcookie( VIP_IS_JETPACK_SSO_COOKIE, $sso_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );

		if ( ! empty( $user_data->two_step_enabled ) ) {
			$sso_2sa_cookie = generate_sso_assertion( $user_id, $expiration, $token, 'jetpack_sso_2sa' );
			setcookie( VIP_IS_JETPACK_SSO_2SA_COOKIE, $sso_2sa_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );
		} else {
			setcookie( VIP_IS_JETPACK_SSO_2SA_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $secure, true );
		}
	}, 10, 6 );
}, 10, 2 );

add_action( 'clear_auth_cookie', function () {
	if ( ! headers_sent() ) {
		setcookie( VIP_IS_JETPACK_SSO_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( VIP_IS_JETPACK_SSO_2SA_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	}
} );

function is_jetpack_sso() {
	return validate_sso_assertion( VIP_IS_JETPACK_SSO_COOKIE, 'jetpack_sso' );
}

function is_jetpack_sso_two_step() {
	if ( ! is_jetpack_sso() ) {
		return false;
	}

	return validate_sso_assertion( VIP_IS_JETPACK_SSO_2SA_COOKIE, 'jetpack_sso_2sa' );
}
