<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
* Redirect logged-out users to the canonical (home) URL
*/
function enforce_logged_out_canonical_redirect() {
	// Redirects are domain-based, so we can't do anything
	if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
		return;
	}

	// Intended for visitors
	if ( is_user_logged_in() ) {
		return;
	}

	// Alias request
	$requested_host = $_SERVER['HTTP_HOST'];
	$requested_uri  = $_SERVER['REQUEST_URI'];

	// Defaults for parse_url()
	$defaults = [ 'scheme' => 'http', 'host' => $requested_host, 'path' => '/' ];

	// Need schemes straight from the option, otherwise is_ssl() gets in the way
	$home_url_scheme = parse_url( get_option( 'home' ), PHP_URL_SCHEME );
	$site_url_scheme = parse_url( get_option( 'siteurl' ), PHP_URL_SCHEME );

	// Parse home URLs, bypassing is_ssl() by setting the scheme from the start
	$home_url_parsed = wp_parse_args( parse_url( get_home_url( null, '/', $home_url_scheme ) ), $defaults );
	$site_url_parsed = wp_parse_args( parse_url( get_site_url( null, '/', $site_url_scheme ) ), $defaults );

	// Have we something to do?
	if ( $requested_host === $site_url_parsed['host'] ) {
		// Munge the URL
		$redirect_url = $requested_host . $requested_uri;

		$search       = $site_url_parsed['host'] . $site_url_parsed['path'];
		$replace      = $home_url_parsed['host'] . $home_url_parsed['path'];
		$redirect_url = str_replace( $search, $replace, $redirect_url );
		$redirect_url = 'http://' . $redirect_url;

		$redirect_url = set_url_scheme( $redirect_url, $home_url_parsed['scheme'] );

		// Redirect type
		$status_code = apply_filters( 'wpcom_vip_split_url_canonical_redirect_status_code', 301 );

		wp_safe_redirect( $redirect_url, $status_code );
		exit;
	}
}
add_action( 'parse_request', __NAMESPACE__ . '\enforce_logged_out_canonical_redirect' );

/**
* Ensure that both home and site URL are valid redirect hosts
*/
function filter_allowed_redirect_hosts( $hosts ) {
	$hosts[] = parse_url( home_url( '/' ), PHP_URL_HOST );
	$hosts[] = parse_url( site_url( '/' ), PHP_URL_HOST );

	// In case something else already added the site_url()
	// Core doesn't, but other plugins may
	$hosts = array_unique( $hosts );

	return $hosts;
}
add_filter( 'allowed_redirect_hosts', __NAMESPACE__ . '\filter_allowed_redirect_hosts' );
