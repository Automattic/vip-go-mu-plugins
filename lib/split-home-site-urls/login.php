<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
 * Rewrite any admin URLs to use the site URL, not the home URL
 */
function rewrite_admin_url_to_site_url( $redirect_to ) {
	// Nothing to correct if it's not a wp-admin link
	if ( empty( $redirect_to ) || false === stripos( $redirect_to, 'wp-admin' ) ) {
		return $redirect_to;
	}

	// Grab the path relative to wp-admin
	preg_match( '#/wp-admin(/)?(.+?)$#i', $redirect_to, $admin_paths );

	// Build a proper admin URL
	if ( is_array( $admin_paths ) && isset( $admin_paths[2] ) ) {
		$redirect_to = admin_url( $admin_paths[2] );
	} else {
		$redirect_to = admin_url( '/' );
	}

	return $redirect_to;
}

/**
 * Fix admin-related wp_login_url() redirect to use site URL instead of home URL
 */
function correct_login_url_redirect( $login_url, $redirect_to ) {
	// Nothing to correct if it's not a wp-admin link
	if ( empty( $redirect_to ) || false === stripos( $redirect_to, 'wp-admin' ) ) {
		return $login_url;
	}

	$redirect_to = rewrite_admin_url_to_site_url( $redirect_to );

	// Replace the query string wp_login_url() produced, maintaining encoding
	$login_url = remove_query_arg( 'redirect_to', $login_url );
	$login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );

	return $login_url;
}
add_filter( 'login_url', __NAMESPACE__ . '\correct_login_url_redirect', 10, 2 );

/**
 * Fix admin-related login redirects to use site URL instead of home URL
 */
add_filter( 'login_redirect',        __NAMESPACE__ . '\rewrite_admin_url_to_site_url' );
add_filter( 'logout_redirect',       __NAMESPACE__ . '\rewrite_admin_url_to_site_url' );
add_filter( 'lostpassword_redirect', __NAMESPACE__ . '\rewrite_admin_url_to_site_url' );
add_filter( 'registration_redirect', __NAMESPACE__ . '\rewrite_admin_url_to_site_url' );
