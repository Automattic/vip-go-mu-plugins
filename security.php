<?php

/*
Plugin Name: VIP Security
Description: Various security enhancements
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

function wpcom_vip_is_restricted_username( $username ) {
	return 'admin' === $username
		|| WPCOM_VIP_MACHINE_USER_LOGIN === $username
		|| WPCOM_VIP_MACHINE_USER_EMAIL === $username;
}

/**
 * Tracks and caches IP and IP|Username events.
 *
 * @param string $username The username to track.
 * @param string $cache_group The cache group to track the $username to.
 */
function wpcom_vip_limiter( $username, $cache_group ) {
	$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
	$key1 = $ip . '|' . $username; // IP + username
	$key2 = $ip; // IP only

	// Longer TTL when logging in as admin, which we don't allow on WP.com
	$is_restricted_username = wpcom_vip_is_restricted_username( $username );
	wp_cache_add( $key1, 0, $cache_group, $is_restricted_username ? HOUR_IN_SECONDS : ( MINUTE_IN_SECONDS * 5 ) );
	wp_cache_add( $key2, 0, $cache_group,  HOUR_IN_SECONDS );
	wp_cache_incr( $key1, 1, $cache_group );
	wp_cache_incr( $key2, 1, $cache_group );
}

function wpcom_vip_login_limiter( $username ) {

	wpcom_vip_limiter( $username, 'login_limit' );

}
add_action( 'wp_login_failed', 'wpcom_vip_login_limiter' );

function wpcom_vip_login_limiter_on_success( $username, $user ) {
	$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
	$key1 = $ip . '|' . $username; // IP + username
	$key2 = $ip; // IP only

	wp_cache_decr( $key1, 1, 'login_limit' );
	wp_cache_decr( $key2, 1, 'login_limit' );
}
add_action( 'wp_login', 'wpcom_vip_login_limiter_on_success', 10, 2 );

function wpcom_vip_limit_logins_for_restricted_usernames( $user, $username, $password ) {
	$is_restricted_username = wpcom_vip_is_restricted_username( $username );
	if ( $is_restricted_username ) {
		return new WP_Error( 'restricted-login', 'Logins are restricted for that user. Please try a different user account.' );
	}

	return $user;
}
add_filter( 'authenticate', 'wpcom_vip_limit_logins_for_restricted_usernames', 30, 3 ); // core authenticates on 20

function wpcom_vip_login_limiter_authenticate( $user, $username, $password ) {
	if ( empty( $username ) && empty( $password ) )
		return $user;

	$is_login_limited = wpcom_vip_username_is_limited( $username );
	if ( is_wp_error( $is_login_limited ) ) {
		return $is_login_limited;
	}

	return $user;
}
add_filter( 'authenticate', 'wpcom_vip_login_limiter_authenticate', 30, 3 ); // core authenticates on 20

function wpcom_vip_login_limit_dont_show_login_form() {
	if ( 'post' != strtolower( $_SERVER['REQUEST_METHOD'] ) || !isset( $_POST['log'] ) ) {
		return;
	}

	$username = sanitize_user( $_POST['log'] );
	if ( $error = wpcom_vip_username_is_limited( $username ) ) {
		login_header( __( 'Error' ), '', $error );
		login_footer();
		exit;
	}
}
add_action( 'login_form_login', 'wpcom_vip_login_limit_dont_show_login_form' );

function wpcom_vip_login_limit_xmlrpc_error( $error, $user ) {
	if ( is_wp_error( $user ) && 'login_limit_exceeded' == $user->get_error_code() )
		return new IXR_Error( 503, $user->get_error_message() );

	return $error;
}
add_filter( 'xmlrpc_login_error', 'wpcom_vip_login_limit_xmlrpc_error', 10, 2 );

function wpcom_vip_lost_password_limit( $errors ) {
	// Don't bother checking if we're already error-ing out
	if ( $errors->get_error_code() ) {
		return $errors;
	}

	$cache_group = 'lost_password_limit';

	$username = trim( wp_unslash( $_POST['user_login'] ) );
	if ( is_email( $username ) ) {
		$username = sanitize_email( $username );
	} else {
		$username = sanitize_user( $username );
	}
	$is_login_limited = wpcom_vip_username_is_limited( $username, $cache_group );

	if ( is_wp_error( $is_login_limited ) ) {
		$errors->add( $is_login_limited->get_error_code(), $is_login_limited->get_error_message() );
	} else {
		wpcom_vip_limiter( $username, $cache_group );
	}

	return $errors;
}
add_action( 'lostpassword_post', 'wpcom_vip_lost_password_limit' );

function wpcom_vip_username_is_limited( $username, $cache_group = 'login_limit' ) {
	$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );

	$key1 = $ip . '|' . $username;
	$key2 = $ip;
	$count1 = wp_cache_get( $key1, $cache_group );

	$is_restricted_username = wpcom_vip_is_restricted_username( $username );
	if ( $is_restricted_username ) {
		$threshold1 = 2;
	} else {
		$threshold1 = 5;
	}

	$count2 = wp_cache_get( $key2, $cache_group );
	$threshold2 = 50;

	if ( $count1 >= $threshold1 || $count2 >= $threshold2 ) {

		switch( $cache_group ) {

			case 'lost_password_limit':
				return new WP_Error( 'lost_password_limit_exceeded', __( 'You have exceeded the password reset limit.  Please wait a few minutes and try again.' ) );
				break;
			case 'login_limit':
				do_action( 'login_limit_exceeded', $username );
				return new WP_Error( 'login_limit_exceeded', __( 'You have exceeded the login limit.  Please wait a few minutes and try again.' ) );
				break;

		}
	}

	return false;
}
