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

	$options = get_debug_mode_cookie_options( time() + COOKIE_TTL );
	setcookie( 'vip-go-cb', '1', $options );    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	setcookie( 'a8c-debug', '1', $options );    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie

	send_pixel( [ 'vip-go-a8c-debug' => 'enable' ] );

	redirect_back();
}

function disable_debug_mode() {
	nocache_headers();

	$options = get_debug_mode_cookie_options( time() - COOKIE_TTL );
	setcookie( 'vip-go-cb', '', $options );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	setcookie( 'a8c-debug', '', $options );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie

	send_pixel( [ 'vip-go-a8c-debug' => 'disable' ] );

	redirect_back();
}

/**
 * Shared so enable and disable always target the same cookies.
 *
 * @param int $expires Unix timestamp; in the past to clear the cookies.
 * @return array
 */
function get_debug_mode_cookie_options( $expires ) {
	return [
		'expires'  => $expires,
		// Without a path, browsers scope the cookies to the current directory, so Debug Mode wouldn't follow you around the site.
		'path'     => '/',
		'secure'   => is_ssl(),
		// Only PHP and the edge cache read these cookies.
		'httponly' => true,
	];
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
		// The hidden text tells screen readers what clicking does; the visible label only says Debug Mode is on.
		'title'  => 'A8C<span class="a8c-debug-suffix"> Debug</span><span class="screen-reader-text">, turn off Debug Mode</span>',
		'href'   => get_disable_debug_mode_url(),
		'meta'   => [
			'title' => 'Turn off Debug Mode',
		],
	] );
}

function add_debug_admin_bar_styles() {
	$css = <<<'CSS'
	#wpadminbar #wp-admin-bar-a8c-debug > .ab-item {
		padding: 0 10px;
		background: rgb(194,156,105);
		/* Dark text keeps WCAG AA contrast (4.5:1) on both gold backgrounds; white doesn't. */
		color: #1d2327;
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
		color: #1d2327;
	}

	/* Core's admin bar outline is transparent, and the darker gold alone is too subtle a focus cue. */
	#wpadminbar #wp-admin-bar-a8c-debug > .ab-item:focus-visible {
		outline: 2px solid #1d2327;
		outline-offset: -4px;
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
		<a href="<?php echo esc_url( get_disable_debug_mode_url() ); ?>" title="Turn off Debug Mode">A8C <span class="a8c-debug-flag-label">Debug</span><span class="a8c-debug-flag-sr">, turn off Debug Mode</span></a>
	</div>
	<style>
	/* A small tab docked to the bottom-left corner, so it covers as little of the page as possible. */
	#a8c-debug-flag {
		position: fixed;
		bottom: 0;
		left: 0;
		z-index: 9991;
		margin: 0;
		padding: 0;
	}

	#a8c-debug-flag a {
		display: flex;
		align-items: center;
		box-sizing: border-box;
		/* min-height, not height, so the background grows with zoomed text. */
		min-height: 24px;
		padding: 0 10px;
		background: rgb(194,156,105);
		color: #1d2327;
		font: bold 12px/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
		letter-spacing: 0.05em;
		text-decoration: none;
		white-space: nowrap;
		border-top-right-radius: 6px;
	}

	#a8c-debug-flag a:hover,
	#a8c-debug-flag a:focus {
		background: rgb(168,132,84);
		color: #1d2327;
	}

	/* Drawn inside the tab: an outer ring would be clipped by the viewport edges, and themes often remove focus styles. */
	#a8c-debug-flag a:focus-visible {
		outline: 2px solid #1d2327;
		outline-offset: -4px;
	}

	/* Themes may not define .screen-reader-text, so use our own. */
	#a8c-debug-flag .a8c-debug-flag-sr {
		position: absolute;
		width: 1px;
		height: 1px;
		margin: -1px;
		padding: 0;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
		border: 0;
	}

	/*
	 * Collapsed to "A8C" until hovered or focused. Hidden with max-width, not display, so screen readers still hear the label.
	 * Uppercased in CSS so screen readers say "Debug" rather than spelling it out.
	 */
	#a8c-debug-flag .a8c-debug-flag-label {
		max-width: 0;
		margin-left: 0;
		overflow: hidden;
		text-transform: uppercase;
		transition: max-width 0.15s ease-out, margin-left 0.15s ease-out;
	}

	#a8c-debug-flag a:hover .a8c-debug-flag-label,
	#a8c-debug-flag a:focus .a8c-debug-flag-label {
		max-width: 10em;
		margin-left: 0.6em;
	}

	@media (prefers-reduced-motion: reduce) {
		#a8c-debug-flag .a8c-debug-flag-label {
			transition: none;
		}
	}

	@media print {
		#a8c-debug-flag {
			display: none;
		}
	}
	</style>
	<?php
}
