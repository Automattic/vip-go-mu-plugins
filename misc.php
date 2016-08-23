<?php
/*
Plugin Name: VIP Hosting Miscellaneous
Description: Handles CSS and JS concatenation, Nginx compatibility, SSL verification
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

// Checking for VIP_GO_ENV allows this code to work outside VIP Go environments,
// albeit without concatenation of JS and CSS.
if ( defined( 'VIP_GO_ENV' ) && false !== VIP_GO_ENV ) {
	// Activate concatenation
	if ( ! isset( $_GET['concat_js'] ) || 'yes' === $_GET['concat_js'] ) {
		require __DIR__ .'/http-concat/jsconcat.php';
	}

	if ( ! isset( $_GET['concat_css'] ) || 'yes' === $_GET['concat_css'] ) {
		require __DIR__ .'/http-concat/cssconcat.php';
	}
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
 * Store a copy of the 'notoptions' cache value, so we know which ones we need
 * to clear the cache for after add|update_option
 *
 * This is because add|update_option removes the entry from the notoptions local
 * and memcached array, which means it's no longer available for testing via
 * isset( $notoptions[ $option ] ) to conditionally clear that cache entry
 */
$_wpcom_vip_notoptions_copy = array();

add_action( 'muplugins_loaded', function() {
	global $_wpcom_vip_notoptions_copy;

	$_wpcom_vip_notoptions_copy = wp_cache_get( 'notoptions', 'options' );
});

/**
 * Fix a race condition in alloptions caching
 *
 * See https://core.trac.wordpress.org/ticket/31245
 */
function _wpcom_vip_maybe_clear_alloptions_cache( $option ) {
	if ( ! wp_installing() ) {
		$alloptions = wp_load_alloptions(); //alloptions should be cached at this point

		if ( isset( $alloptions[ $option ] ) ) { //only if option is among alloptions
			wp_cache_delete( 'alloptions', 'options' );
		}

		// And we need to do the same for notoptions, as it suffers from the same bug
		// NOTE - we use the copy we stored earlier, not the current value, as WP has
		// changed it on us. We need to know if it _used to_ exist in the notoptions array
		global $_wpcom_vip_notoptions_copy;

		// only flush if option is among original notoptions
		if ( is_array( $_wpcom_vip_notoptions_copy ) && isset( $_wpcom_vip_notoptions_copy[ $option ] ) ) {
			wp_cache_delete( 'notoptions', 'options' );
		}
	}
}

add_action( 'added_option',   '_wpcom_vip_maybe_clear_alloptions_cache' );
add_action( 'updated_option', '_wpcom_vip_maybe_clear_alloptions_cache' );
add_action( 'deleted_option', '_wpcom_vip_maybe_clear_alloptions_cache' );

/**
 * Hooks pre_ping to stop any pinging from happening,
 * unless `VIP_DO_PINGS` is set to `true` (boolean).
 *
 * @param array $post_links The URLs to be pinged (passed by ref)
 */
function wpcom_vip_pre_ping( $post_links ) {
	$do_pings = ( defined( 'VIP_DO_PINGS' ) && true === VIP_DO_PINGS );
	if ( ! $do_pings ) {
		// Clear our the post links array, so we ping nothing
		$post_links = array();
		return;
	}
}
add_action( 'pre_ping', 'wpcom_vip_pre_ping' );
