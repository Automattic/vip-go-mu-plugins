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

add_action( 'jetpack_sso_handle_login', function( $user, $user_data ) {
	add_action( 'set_auth_cookie', function( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ) use ( $user_data ) {
		$secure = is_ssl();

		$sso_cookie = wp_generate_auth_cookie( $user_id, $expire, 'secure_auth', $token );
		setcookie( VIP_IS_JETPACK_SSO_COOKIE, $sso_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );

		if ( $user_data->two_step_enabled ) {
			$sso_2sa_cookie = wp_generate_auth_cookie( $user_id, $expire, 'secure_auth', $token );
			setcookie( VIP_IS_JETPACK_SSO_2SA_COOKIE, $sso_2sa_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );
		}
	}, 10, 6 );
}, 10, 2 );

add_action( 'clear_auth_cookie', function() {
	setcookie( VIP_IS_JETPACK_SSO_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	setcookie( VIP_IS_JETPACK_SSO_2SA_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
} );

function is_jetpack_sso() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( ! isset( $_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ] ) ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- will be validated by wp_validate_auth_cookie()
	$cookie = $_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ];
	return wp_validate_auth_cookie( $cookie, 'secure_auth' );
}

function is_jetpack_sso_two_step() {
	if ( ! is_jetpack_sso() ) {
		return false;
	}

	if ( ! isset( $_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] ) ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- will be validated by wp_validate_auth_cookie()
	$cookie = $_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ];
	return wp_validate_auth_cookie( $cookie, 'secure_auth' );
}
