<?php

add_action( 'jetpack_sso_handle_login', function( $user, $user_data ) {
	add_action( 'set_auth_cookie', function( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ) use ( $user_data ) {
		$secure = is_ssl();

		$sso_cookie = create_twostep_cookie( $user_id, $expire, 'sso_cookie' );
		setcookie( 'vip-is-jetpack-sso', $sso_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );

		if ( $user_data->two_step_enabled ) {
			$sso_2sa_cookie = create_twostep_cookie( $user_id, $expire, 'sso_2sa_cookie' );
			setcookie( 'vip-is-jetpack-sso-two-step', $sso_2sa_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );
		}
	}, 10, 6 );
}, 10, 2 );

add_action( 'clear_auth_cookie', function() {
	setcookie( 'vip-is-jetpack-sso', ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	setcookie( 'vip-is-jetpack-sso-two-step', ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
} );

function vip_is_jetpack_sso() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( ! isset( $_COOKIE[ 'vip-is-jetpack-sso' ] ) ) {
		return false;
	}

	$cookie = $_COOKIE[ 'vip-is-jetpack-sso' ];
	return verify_twostep_cookie( $cookie );
}

function vip_is_jetpack_sso_two_step() {
	if ( ! vip_is_jetpack_sso() ) {
		return false;
	}

	if ( ! isset( $_COOKIE[ 'vip-is-jetpack-sso-two-step' ] ) ) {
		return false;
	}

	$cookie = $_COOKIE[ 'vip-is-jetpack-sso-two-step' ];
	return verify_twostep_cookie( $cookie );
}

// If a user has two step enabled and is not logging in with an app password set the two step cookie
function create_twostep_cookie( $user_id, $expiration, $scheme ) {
	$user = get_userdata( $user_id );

	$secure = apply_filters( 'secure_auth_cookie', is_ssl(), $user_id );

	$key = wp_hash( $user->user_login . '|' . $expiration, $scheme );
	$hash = hash_hmac( 'md5', $user->user_login . '|' . $expiration, $key );

	$cookie = $user->user_login . '|' . $expiration . '|' . $hash;
	return $cookie;
}

function verify_twostep_cookie( $cookie ) {
	global $current_user;

	// 0: user_login
	// 1: expiration
	// 2: hmac
	$elements = explode( '|', $cookie );

	// Expired
	if ( ! ctype_digit( $elements[ 1 ] ) || $elements[ 1 ] < time() )
		return false;

	$user = get_user_by( 'login', $elements[0] );
	// Bad username
	if ( empty( $user ) )
		return false;

	// Cookie user doesn't match current user
	if ( $user->ID !== $current_user->ID )
		return false;

	$key = wp_hash( $user->user_login . '|' . $elements[1], $scheme );
	$hash = hash_hmac( 'md5', $user->user_login . '|' . $elements[1], $key );

	// Bad hash
	if ( ! hash_equals( $hash, $elements[2] ) ) {
		return false;
	}

	return true;
}
