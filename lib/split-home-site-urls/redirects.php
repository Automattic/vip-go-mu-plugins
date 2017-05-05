<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

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
