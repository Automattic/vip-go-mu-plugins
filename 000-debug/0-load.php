<?php

namespace Automattic\VIP\Debug;

require_once( __DIR__ . '/logger.php' );

// How long should we enable Debug mode for?
const COOKIE_TTL = 2 * HOUR_IN_SECONDS;

// Wait till our VIP context is loaded so we can use som internal functions.
add_action( 'vip_loaded', __NAMESPACE__ . '\init_debug_mode' );

function init_debug_mode() {
	if ( isset( $_GET['a8c-debug'] ) ) {
		toggle_debug_mode();
		return;
	}

	if ( is_debug_mode_enabled() ) {
		add_action( 'muplugins_loaded', __NAMESPACE__ . '\enable_debug_tools' );
	}
}

function is_debug_mode_enabled() {
	$is_nocache = isset( $_COOKIE['vip-go-cb'] ) && '1' === $_COOKIE['vip-go-cb'];
	$is_debug = isset( $_COOKIE['a8c-debug'] ) && '1' === $_COOKIE['a8c-debug'];
	$is_proxied = \is_proxied_request();

	if ( $is_nocache && $is_debug && $is_proxied ) {
		return true;
	}

	return false;
}

function redirect_back() {
	// Redirect to the same page without the activation handler.
	$redirect_to = remove_query_arg( 'a8c-debug' );
	header( sprintf( 'Location: %s', $redirect_to ) );
	exit;
}

function enable_debug_mode() {
	if ( ! \is_proxied_request() ) {
		return;
	}

	nocache_headers();

	$ttl = time() + COOKIE_TTL;
	setcookie( 'vip-go-cb', '1', $ttl );
	setcookie( 'a8c-debug', '1', $ttl );

	redirect_back();
}

function disable_debug_mode() {
	nocache_headers();

	$ttl = time() - COOKIE_TTL;
	setcookie( 'vip-go-cb', '', $ttl );
	setcookie( 'a8c-debug', '', $ttl );

	redirect_back();
}

function toggle_debug_mode() {
	if ( 'true' === $_GET['a8c-debug'] ) {
		return enable_debug_mode();
    } elseif ( 'false' === $_GET['a8c-debug'] ) {
		return disable_debug_mode();
	}
}

function enable_debug_tools() {
	add_filter( 'user_has_cap', function( $user_caps, $caps, $args, $user ) {
		if ( 'view_query_monitor' === $args[ 0 ] ) {
			$user_caps['view_query_monitor'] = true;
		}

		return $user_caps;
	}, 10, 4 );

	add_action( 'wp_footer', __NAMESPACE__ . '\show_debug_flag', 9999 ); // output later in the page
	add_action( 'login_footer', __NAMESPACE__ . '\show_debug_flag', 9999 ); // output later in the page
}

function show_debug_flag() {
	?>
	<div id="a8c-debug-flag">
		<a href="<?php echo esc_url( add_query_arg( 'a8c-debug', 'false' ) ); ?>">A8C Debug</a>
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
