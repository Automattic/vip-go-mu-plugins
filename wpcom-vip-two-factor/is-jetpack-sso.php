<?php

namespace Automattic\VIP\TwoFactor;

// muplugins_loaded fires before cookie constants are set
if ( is_multisite() ) {
	ms_cookie_constants();
}

wp_cookie_constants();

// Retain the legacy names for integrations; these cookies no longer grant SSO status.
define( 'VIP_IS_JETPACK_SSO_COOKIE', AUTH_COOKIE . '_vip_jetpack_sso' );
define( 'VIP_IS_JETPACK_SSO_2SA_COOKIE', AUTH_COOKIE . '_vip_jetpack_sso_2sa' );

/**
 * Get the token from a cookie that actually authenticates the current user.
 *
 * @param int $user_id Current user ID.
 * @return string|false Valid session token, or false.
 */
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

/**
 * Get the metadata for the current user's authenticated WordPress session.
 *
 * @return array Session information, or an empty array when unauthenticated.
 */
function current_sso_session() {
	$user_id = get_current_user_id();
	$token   = $user_id ? current_auth_session_token( $user_id ) : false;

	return $token ? ( \WP_Session_Tokens::get_instance( $user_id )->get( $token ) ?? [] ) : [];
}

add_action( 'jetpack_sso_handle_login', function ( $user, $user_data ) {
	if ( ! $user instanceof \WP_User ) {
		return;
	}

	$attach_sso_information = null;
	$attach_sso_information = function ( $session, $user_id ) use ( $user, $user_data, &$attach_sso_information ) {
		if ( (int) $user->ID !== (int) $user_id ) {
			return $session;
		}

		// Only the session created for this SSO login receives the flags.
		remove_filter( 'attach_session_information', $attach_sso_information, 10 );
		$session['vip_jetpack_sso']          = true;
		$session['vip_jetpack_sso_two_step'] = ! empty( $user_data->two_step_enabled );

		return $session;
	};
	add_filter( 'attach_session_information', $attach_sso_information, 10, 2 );
}, 10, 2 );

function is_jetpack_sso() {
	$session = current_sso_session();

	return true === ( $session['vip_jetpack_sso'] ?? false ) ? get_current_user_id() : false;
}

function is_jetpack_sso_two_step() {
	$session = current_sso_session();
	$is_sso  = true === ( $session['vip_jetpack_sso'] ?? false );

	return $is_sso && true === ( $session['vip_jetpack_sso_two_step'] ?? false ) ? get_current_user_id() : false;
}
