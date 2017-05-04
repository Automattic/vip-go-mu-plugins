<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
 * Ensure preview URLs are served over SSL
 */
function fix_preview_link_host( $link ) {
	return str_replace( home_url( '/' ), site_url( '/' ), $link );
}
add_filter( 'preview_post_link', __NAMESPACE__ . '\fix_preview_link_host' );

/**
 * Parse home URL into pieces needed by setcookie()
 */
function parse_home_url_for_cookie() {
	$url    = home_url( '/' );
	$domain = parse_url( $url, PHP_URL_HOST );
	$path   = parse_url( $url, PHP_URL_PATH );
	$secure = 'https' === parse_url( $url, PHP_URL_SCHEME );

	return compact( 'domain', 'path', 'secure' );
}

/**
 * Set auth cookie for front-end requests
 */
function add_home_auth_cookie( $auth_cookie, $expire, $expiration, $user_id, $scheme ) {
	if ( 'secure_auth' === $scheme ) {
		$auth_cookie_name = \SECURE_AUTH_COOKIE;
	} elseif ( 'auth' === $scheme ) {
		$auth_cookie_name = \AUTH_COOKIE;
	} else {
		return;
	}

	$cookie_url_parts = parse_home_url_for_cookie();

	// Override the cookie's secure flag following `wp_set_auth_cookie()`'s logic
	if ( 'secure_auth' !== $scheme ) {
		$cookie_url_parts['secure'] = false;
	}

	setcookie( $auth_cookie_name, $auth_cookie, $expire, $cookie_url_parts['path'], $cookie_url_parts['domain'], $cookie_url_parts['secure'], true );
}
add_action( 'set_auth_cookie', __NAMESPACE__ . '\add_home_auth_cookie', 10, 5 );

/**
 * Set logged-in cookie for front-end requests
 */
function add_home_logged_in_cookie( $logged_in_cookie, $expire, $expiration, $user_id, $scheme ) {
	if ( 'logged_in' !== $scheme ) {
		return;
	}

	$cookie_url_parts = parse_home_url_for_cookie();

	setcookie( \LOGGED_IN_COOKIE, $logged_in_cookie, $expire, $cookie_url_parts['path'], $cookie_url_parts['domain'], $cookie_url_parts['secure'], true );
}
add_action( 'set_logged_in_cookie', __NAMESPACE__ . '\add_home_logged_in_cookie', 10, 5 );

// TODO: clear custom cookies on clear_auth_cookie
