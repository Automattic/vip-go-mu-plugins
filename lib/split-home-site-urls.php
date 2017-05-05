<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
 * Rewrite static asset URLs back to the home URL, as Core normally relies on site URL
 */
require_once __DIR__ . '/split-home-site-urls/asset-urls.php';

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
 * Redirect logged-out users to the canonical (home) URL
 */
function enforce_logged_out_canonical_redirect() {
	if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
		return;
	}

	if ( is_user_logged_in() ) {
		return;
	}

	$requested_proto = is_ssl() ? 'https://' : 'http://';
	$requested_host  = $_SERVER['HTTP_HOST'];
	$requested_uri   = $_SERVER['REQUEST_URI'];

	$home_url_host = parse_url( home_url( '/' ), PHP_URL_HOST );
	$site_url_host = parse_url( site_url( '/' ), PHP_URL_HOST );

	if ( $requested_host === $site_url_host ) {
		$redirect_url = $requested_proto . $home_url_host . $requested_uri;

		$status_code = apply_filters( 'wpcom_vip_split_url_canonical_redirect_status_code', 301 );

		wp_redirect( $redirect_url, $status_code );
		exit;
	}
}
add_action( 'parse_request', __NAMESPACE__ . '\enforce_logged_out_canonical_redirect' );

/**
 * Fix login redirect URLs that point to home URL instead of site URL
 */
function correct_login_redirect( $login_url, $redirect_to ) {
	// Nothing to correct if it's not a wp-admin link
	if ( empty( $redirect_to ) || false === stripos( $redirect_to, 'wp-admin' ) ) {
		return $login_url;
	}

	// Grab the path relative to wp-admin
	preg_match( '#/wp-admin(/)?(.+?)$#i', $redirect_to, $admin_paths );

	// Build a proper admin URL
	if ( is_array( $admin_paths ) && isset( $admin_paths[2] ) ) {
		$redirect_to = admin_url( $admin_paths[2] );
	} else {
		$redirect_to = admin_url( '/' );
	}

	// Replace the query string wp_login_url() produced, maintaining encoding
	$login_url = remove_query_arg( 'redirect_to', $login_url );
	$login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );

	return $login_url;
}
add_filter( 'login_url', __NAMESPACE__ . '\correct_login_redirect', 10, 2 );

/**
 * Ensure that both home and site URL are valid redirect hosts
 */
function filter_allowed_redirect_hosts( $hosts ) {
	$hosts[] = parse_url( site_url( '/' ), PHP_URL_HOST );

	// In case something else already added the site_url()
	// Core doesn't, but other plugins may
	$hosts = array_unique( $hosts );

	return $hosts;
}
add_filter( 'allowed_redirect_hosts', __NAMESPACE__ . '\filter_allowed_redirect_hosts' );
