<?php

namespace Automattic\VIP\Utils;

class Context {
	/**
	 * Begin: VIP-specific contexts
	 */
	public static function is_vip_env() {
		// VIP_GO_ENV will have a string value with the environment on Go servers.
		// Will be `false` or undefined otherwise.
		return defined( 'VIP_GO_ENV' ) && false !== VIP_GO_ENV;
	}

	public static function is_healthcheck() {
		// phpcs:disable WordPress.VIP.SuperGlobalInputUsage.AccessDetected
		return ! empty( $_SERVER['REQUEST_URI'] )
			&& '/cache-healthcheck?' === $_SERVER['REQUEST_URI'];
		// phpcs:enable WordPress.VIP.SuperGlobalInputUsage.AccessDetected
	}

	/**
	 * Begin: Core-specific contexts
	 */

	// A non-API, non-CLI, non-system request
	public static function is_web_request() {
		return false === Context::is_wp_cli()
			&& false === Context::is_rest_api()
			&& false === Context::is_cron()
			&& false === Context::is_xml_rpc();
	}

	public static function is_wp_cli() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	public static function is_rest_api() {
		return defined( 'REST_REQUEST' ) && true === REST_REQUEST;
	}

	public static function is_cron() {
		return defined( 'DOING_CRON' ) && true === DOING_CRON;
	}

	public static function is_xmlrpc() {
		return defined( 'XMLRPC_REQUEST' ) && true === XMLRPC_REQUEST;
	}
}
