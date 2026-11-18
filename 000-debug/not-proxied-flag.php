<?php

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

namespace Automattic\VIP\NotProxiedFlag;

use Automattic\VIP\Helpers\Environment_Badges;

add_action( 'muplugins_loaded', __NAMESPACE__ . '\enable_not_proxied_flag' );

function enable_not_proxied_flag() {
	add_action( 'init', __NAMESPACE__ . '\maybe_add_not_proxied_badge' );
}

function maybe_add_not_proxied_badge() {
	if ( ! is_admin() || ! class_exists( Environment_Badges::class ) ) {
		return;
	}

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

	Environment_Badges::add( 'not-proxied', [
		'label' => 'Not proxied',
		'short' => 'NP',
		'title' => 'This request did not come through the A8C proxy',
		'order' => 30,
	] );
}
