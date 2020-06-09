<?php

namespace Automattic\VIP\NotProxiedFlag;

/**
 * Display 'Not Proxied' flag when not proxied.
 *
 * It can be hard to see if one is really proxied
 * when accessing wp-admin, especially when sites
 * are set up with a frontend proxy. Using
 * such proxies will cause the calling user being
 * considered not proxied by the backend servers,
 * even if the caller is using the A8C proxy.
 *
 * This mu-plugin will make wp-admin display a small
 * banner when not proxied and logged in as Automattican,
 * indicating that the caller is not proxied. It is not to
 * be displayed for other users.
 */

add_action( 'muplugins_loaded', __NAMESPACE__ . '\enable_not_proxied_flag' );

function enable_not_proxied_flag() {
	add_action( 'admin_footer', __NAMESPACE__ . '\maybe_show_not_proxied_flag', 9999 ); // output later in the page
}

function maybe_show_not_proxied_flag() {
	/*
	 * Display only if is an automattician.
	 */ 
	$is_automattician = function_exists( '\is_automattician' ) && \is_automattician();

	if ( ! $is_automattician ) {
		return;
	}

	/*
	 * Do not display when proxied.
	 */
	$is_proxied = function_exists( '\is_proxied_request' ) && \is_proxied_request();

	if ( $is_proxied ) {
		return;
	}

	?>
	<div id="a8c-not-proxied-flag">
		Not Proxied
	</div>

	<style>
	#a8c-not-proxied-flag {
		z-index: 9991;
		background-color: rgb(0, 124, 186);
		color: rgb(221, 221, 221);
		text-align: center;
		bottom: 50px;
		left: 20px;
		position: fixed;
		height: 28px;
		line-height: 28px;
		letter-spacing: 0.2em;
		text-shadow: none;
		font-family: 'Helvetica Neue',Arial,Helvetica,sans-serif;
		font-size: 9px;
		font-weight: bold;
		text-transform: uppercase;
		padding: 0 10px;
	}
	</style>
	<?php
}
