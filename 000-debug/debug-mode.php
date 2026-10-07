<?php

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

use function Automattic\VIP\Stats\send_pixel;

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

function has_debug_mode_cookies() {
	$is_nocache = isset( $_COOKIE['vip-go-cb'] ) && '1' === $_COOKIE['vip-go-cb'];  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
	$is_debug   = isset( $_COOKIE['a8c-debug'] ) && '1' === $_COOKIE['a8c-debug'];  // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE

	return $is_nocache && $is_debug;
}

function is_debug_mode_enabled() {
	$is_proxied = \is_proxied_request();
	$is_local   = function_exists( 'is_local_env' ) && \is_local_env();

	if ( ( has_debug_mode_cookies() && $is_proxied ) || $is_local ) {
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

	$is_local = function_exists( 'is_local_env' ) && \is_local_env();
	if ( ! \is_proxied_request() && ! $is_local ) {
		send_pixel( [ 'vip-go-a8c-debug' => 'fail-noproxy' ] );

		wp_die( 'A8C: Please proxy to enable Debug Mode.', 'Proxy Required', [ 'response' => 403 ] );
	}

	// Without a path, browsers scope the cookies to the current directory, so Debug Mode wouldn't follow you around the site.
	$ttl = time() + COOKIE_TTL;
	setcookie( 'vip-go-cb', '1', $ttl, '/' );    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	setcookie( 'a8c-debug', '1', $ttl, '/' );    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie

	send_pixel( [ 'vip-go-a8c-debug' => 'enable' ] );

	redirect_back();
}

function disable_debug_mode() {
	nocache_headers();

	// Use the same path as enable_debug_mode(), or the browser keeps the original cookies.
	$ttl = time() - COOKIE_TTL;
	setcookie( 'vip-go-cb', '', $ttl, '/' );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	setcookie( 'a8c-debug', '', $ttl, '/' );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie

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
	add_filter( 'user_has_cap', function ( $user_caps, $caps, $args ) {
		if ( 'view_query_monitor' === $args[0] ) {
			$user_caps['view_query_monitor'] = true;
		}

		return $user_caps;
	}, 10, 3 );

	add_action( 'init', function () {
		add_filter( 'show_admin_bar', '__return_true', PHP_INT_MAX );
	}, 9999 );

	// Local environments get the debug tools without entering Debug Mode, so only flag it when someone did.
	if ( ! has_debug_mode_cookies() ) {
		return;
	}

	// Show the flag in the admin bar so it doesn't cover page content.
	add_action( 'admin_bar_init', __NAMESPACE__ . '\add_debug_admin_bar_styles' );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\add_debug_admin_bar_node' );

	// Fall back to a floating flag where there's no admin bar (e.g. wp-login.php).
	add_action( 'wp_footer', __NAMESPACE__ . '\show_debug_flag', 9999 ); // output later in the page
	add_action( 'login_footer', __NAMESPACE__ . '\show_debug_flag', 9999 ); // output later in the page
}

function get_disable_debug_mode_url() {
	return add_query_arg( [
		'a8c-debug' => 'false',
		// Remove the cache-buster, if set.
		'random'    => false,
	] );
}

/**
 * @param WP_Admin_Bar $wp_admin_bar
 */
function add_debug_admin_bar_node( $wp_admin_bar ) {
	$wp_admin_bar->add_node( [
		'id'     => 'a8c-debug',
		'parent' => 'top-secondary',
		'title'  => 'A8C<span class="a8c-debug-suffix"> Debug</span>',
		'href'   => get_disable_debug_mode_url(),
		'meta'   => [
			'title' => 'Click to disable Debug Mode',
		],
	] );
}

function add_debug_admin_bar_styles() {
	$css = <<<'CSS'
	#wpadminbar #wp-admin-bar-a8c-debug > .ab-item {
		padding: 0 10px;
		background: rgb(194,156,105);
		color: #fff;
		font-size: 11px;
		font-weight: 600;
		/* Core's line-height is relative to its 13px font; match the 32px item height instead. */
		line-height: 32px;
		letter-spacing: 0.1em;
		text-transform: uppercase;
	}

	#wpadminbar #wp-admin-bar-a8c-debug > .ab-item:hover,
	#wpadminbar #wp-admin-bar-a8c-debug > .ab-item:focus {
		background: rgb(168,132,84);
		color: #fff;
	}

	/* Core's `#wpadminbar *` reset would otherwise restyle the suffix. */
	#wpadminbar #wp-admin-bar-a8c-debug .a8c-debug-suffix {
		font: inherit;
		letter-spacing: inherit;
		text-transform: inherit;
	}

	@media screen and (max-width: 782px) {
		/* Core hides non-default top-level items on small screens. */
		#wpadminbar li#wp-admin-bar-a8c-debug {
			display: block;
		}

		#wpadminbar #wp-admin-bar-a8c-debug > .ab-item {
			line-height: 46px;
		}

		/* Keep the toolbar on one row next to core's 52px icons. */
		#wpadminbar #wp-admin-bar-a8c-debug .a8c-debug-suffix {
			display: none;
		}
	}
	CSS;

	wp_add_inline_style( 'admin-bar', $css );
}

function show_debug_flag() {
	// The admin bar already shows the flag.
	if ( did_action( 'wp_after_admin_bar_render' ) ) {
		return;
	}

	?>
	<div id="a8c-debug-flag">
		<a href="<?php echo esc_url( get_disable_debug_mode_url() ); ?>" title="Click to disable Debug Mode">A8C Debug</a>
	</div>
	<style>
	#a8c-debug-flag {
		z-index: 9991;
		font: 14px/28px 'Helvetica Neue',Arial,Helvetica,sans-serif;
		background: rgb(194,156,105);
		bottom: 145px;
		left: 20px;
		position: fixed;
		width: 93px;
		height: 28px;
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
		text-decoration: none;
	}
	</style>
	<?php
}
