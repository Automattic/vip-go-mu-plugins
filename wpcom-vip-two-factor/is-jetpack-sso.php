<?php

namespace Automattic\VIP\TwoFactor;

// muplugins_loaded fires before cookie constants are set
wp_cookie_constants();

define( 'VIP_IS_JETPACK_SSO_COOKIE', AUTH_COOKIE . '_vip_jetpack_sso' );
define( 'VIP_IS_JETPACK_SSO_2SA_COOKIE', AUTH_COOKIE . '_vip_jetpack_sso_2sa' );

add_action( 'jetpack_sso_handle_login', function( $user, $user_data ) {
	add_action( 'set_auth_cookie', function( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ) use ( $user_data ) {
		$secure = is_ssl();

		$sso_cookie = create_cookie( $user_id, $expire, VIP_IS_JETPACK_SSO_COOKIE );
		setcookie( VIP_IS_JETPACK_SSO_COOKIE, $sso_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );

		if ( $user_data->two_step_enabled ) {
			$sso_2sa_cookie = create_cookie( $user_id, $expire, VIP_IS_JETPACK_SSO_2SA_COOKIE );
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

	$cookie = $_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ];
	return verify_cookie( $cookie, VIP_IS_JETPACK_SSO_COOKIE );
}

function is_jetpack_sso_two_step() {
	if ( ! is_jetpack_sso() ) {
		return false;
	}

	if ( ! isset( $_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] ) ) {
		return false;
	}

	$cookie = $_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ];
	return verify_cookie( $cookie, VIP_IS_JETPACK_SSO_2SA_COOKIE );
}

function create_cookie( $user_id, $expiration, $scheme ) {
	$user = get_userdata( $user_id );

	$key = wp_hash( $user->user_login . '|' . $expiration, $scheme );
	$hash = hash_hmac( 'md5', $user->user_login . '|' . $expiration, $key );

	$cookie = $user->user_login . '|' . $expiration . '|' . $hash;
	return $cookie;
}

function verify_cookie( $cookie, $scheme ) {
	global $current_user;

	// 0: user_login
	// 1: expiration
	// 2: hmac
	$elements = explode( '|', $cookie );

	// Expired
	if ( ! ctype_digit( $elements[ 1 ] ) || $elements[ 1 ] < time() ) {
		return false;
	}

	$user = get_user_by( 'login', $elements[0] );

	// Bad username
	if ( empty( $user ) ) {
		return false;
	}

	// Cookie user doesn't match current user
	if ( $user->ID !== $current_user->ID ) {
		return false;
	}

	$key = wp_hash( $user->user_login . '|' . $elements[1], $scheme );
	$hash = hash_hmac( 'md5', $user->user_login . '|' . $elements[1], $key );

	// Bad hash
	if ( ! hash_equals( $hash, $elements[2] ) ) {
		return false;
	}

	return true;
}
