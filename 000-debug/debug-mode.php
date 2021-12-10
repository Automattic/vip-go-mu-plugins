<?php

use function Automattic\VIP\Stats\send_pixel;

/**
 * Logged out Debug Mode for VIP
 *
 * Allows the VIP team to enter into Debug Mode when logged out
 * to allow for faster, easier debugging of production-level issues.
 *
 * To enter: `?a8c-debug=true`
 * To leave: `?a8c-debug=false` (or click on the debug flag)
 *
 * When entering Debug Mode, the VIP nocache cookie (vip-go-cb) is set
 * as well as a a8c-debug cookie. Proxy must be enabled at all times.
 *
 * When in Debug Mode, Query Monitor is accessible for examining details
 * about the request.
 */

// How long should we enable Debug mode for?
const COOKIE_TTL = 2 * HOUR_IN_SECONDS;

// Wait till our VIP context is loaded so we can use some internal functions.
add_action( 'muplugins_loaded', __NAMESPACE__ . '\init_debug_mode' );

function init_debug_mode() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$enable = $_GET['a8c-debug'] ?? '';
	if ( in_array( $enable, [ 'true', 'false' ], true ) ) {
		set_debug_mode( 'true' === $enable );
	}

	if ( is_debug_mode_enabled() ) {
		enable_debug_tools();
	}
}

function is_debug_mode_enabled() {
	$is_nocache = isset( $_COOKIE['vip-go-cb'] ) && '1' === $_COOKIE['vip-go-cb'];  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
	$is_debug   = isset( $_COOKIE['a8c-debug'] ) && '1' === $_COOKIE['a8c-debug'];  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
	$is_proxied = \is_proxied_request();
	$is_local   = defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE;

	if ( ( $is_nocache && $is_debug && $is_proxied ) || $is_local ) {
		return true;
	}

	return false;
}

function redirect_back() {
	$redirect_to = add_query_arg( [
		// Redirect to the same page without the activation handler.
		'a8c-debug'    => false,

		// Redirect with a cache buster on the URL to avoid browser-based caches.
		'_cachebuster' => time(),
	] );

	// Note: this is called early so we can't use wp_safe_redirect
	header( sprintf( 'Location: %s', esc_url_raw( $redirect_to ) ) );
	exit;
}

function enable_debug_mode() {
	nocache_headers();

	if ( ! \is_proxied_request() ) {
		send_pixel( [ 'vip-go-a8c-debug' => 'fail-noproxy' ] );

		wp_die( 'A8C: Please proxy to enable Debug Mode.', 'Proxy Required', [ 'response' => 403 ] );
	}

	$ttl = time() + COOKIE_TTL;
	setcookie( 'vip-go-cb', '1', $ttl );    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	setcookie( 'a8c-debug', '1', $ttl );    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie

	send_pixel( [ 'vip-go-a8c-debug' => 'enable' ] );

	redirect_back();
}

function disable_debug_mode() {
	nocache_headers();

	$ttl = time() - COOKIE_TTL;
	setcookie( 'vip-go-cb', '', $ttl );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	setcookie( 'a8c-debug', '', $ttl );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie

	send_pixel( [ 'vip-go-a8c-debug' => 'disable' ] );

	redirect_back();
}

/**
 * Turns the debug mode on or off
 * 
 * @param bool $set     Whether to turn on (true) or off (false) the debug mode
 * @return void 
 */
function set_debug_mode( bool $set ) {
	if ( $set ) {
		enable_debug_mode();
	} else {
		disable_debug_mode();
	}
}

function enable_debug_tools() {
	add_filter( 'user_has_cap', function( $user_caps, $caps, $args ) {
		if ( 'view_query_monitor' === $args[0] ) {
			$user_caps['view_query_monitor'] = true;
		}

		return $user_caps;
	}, 10, 3 );

	add_action( 'wp_footer', __NAMESPACE__ . '\show_debug_flag', 9999 ); // output later in the page
	add_action( 'login_footer', __NAMESPACE__ . '\show_debug_flag', 9999 ); // output later in the page
}

function show_debug_flag() {
	$disable_url = add_query_arg( [
		'a8c-debug' => 'false',
		// Remove the cache-buster, if set.
		'random'    => false,
	] );

	?>
	<div id="a8c-debug-flag">
		<a href="<?php echo esc_url( $disable_url ); ?>" title="Click to disable Debug Mode">A8C Debug</a>
	</div>
	<style>
	#a8c-debug-flag {
		z-index: 9991;
		font-family: 'Helvetica Neue',Arial,Helvetica,sans-serif;
		background: rgb(194,156,105);
		bottom: 50px;
		left: 20px;
		position: fixed;
		width: 93px;
		height: 28px;
		line-height: 28px;
	}

	#a8c-debug-flag a {
		text-transform: uppercase;
		color: #fff;
		letter-spacing: 0.2em;
		font-size: 9px;
		font-weight: bold;
		text-align: center;
		width: 100%;
		display: block;
	}

	#a8c-debug-flag a:hover {
		text-decoration: none;
	}
	</style>
	<?php
}
