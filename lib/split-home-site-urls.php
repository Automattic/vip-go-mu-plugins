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
