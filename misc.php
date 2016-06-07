<?php
/*
Plugin Name: VIP Hosting Miscellaneous
Description: Handles CSS and JS concatenation, Nginx compatibility, SSL verification, alloptions cache fix
Author: Automattic
Version: 1.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// Ensure we do not send the cache headers through to Varnish,
// so responses obey the cache settings we have configured.
function wpcom_vip_check_for_404_and_remove_cache_headers( $headers ) {
	if ( is_404() ) {
		unset( $headers['Expires'] );
		unset( $headers['Cache-Control'] );
		unset( $headers['Pragma'] );
	}
	return $headers;
}
add_filter( 'nocache_headers', 'wpcom_vip_check_for_404_and_remove_cache_headers' );

// Cleaner permalink options
add_filter( 'got_url_rewrite', '__return_true' );

// Activate concatenation
if ( ! isset( $_GET['concat_js'] ) || 'yes' === $_GET['concat_js'] ) {
	require __DIR__ .'/http-concat/jsconcat.php';
}

if ( ! isset( $_GET['concat_css'] ) || 'yes' === $_GET['concat_css'] ) {
	require __DIR__ .'/http-concat/cssconcat.php';
}

/**
 * This function uses the VIP_VERIFY_STRING and VIP_VERIFY_PATH
 * constants to respond with a verification string at a particular
 * path. So if you have a VIP_VERIFY_STRING of `Hello` and a
 * VIP_VERIFY_PATH of `whatever.html`, then the URL
 * yourdomain.com/whatever.html will return `Hello`.
 *
 * We suggest adding these constants in your `vip-config.php`
 *
 * @return void
 */
function action_wpcom_vip_verify_string() {
	if ( ! defined( 'VIP_VERIFY_PATH' ) || ! defined( 'VIP_VERIFY_STRING' ) ) {
		return;
	}
	$verification_path = '/' . VIP_VERIFY_PATH;
	if ( $verification_path === $_SERVER['REQUEST_URI'] ) {
		status_header( 200 );
		echo VIP_VERIFY_STRING;
		exit;
	}
}
add_action( 'template_redirect', 'action_wpcom_vip_verify_string' );

/**
 * Disable New Relic browser monitoring on AMP pages, as the JS isn't AMP-compatible
 */
add_action( 'pre_amp_render_post', 'wpcom_vip_disable_new_relic_js' );


/**
 * Fix a race condition in alloptions caching
 */

add_action( 'update_option', function( $option ) {
    if ( ! wp_installing() ) {
        wp_cache_delete( '<span class="highlight">alloptions</span>', 'options' );
    }
}, 10, 1 );
 
add_action( 'updated_option', function( $option ) {
    if ( ! wp_installing() ) {
        wp_cache_delete( '<span class="highlight">alloptions</span>', 'options' );
        wp_load_<span class="highlight">alloptions</span>();
    }
}, 10, 1 );
