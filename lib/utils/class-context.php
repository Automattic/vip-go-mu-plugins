<?php

namespace Automattic\VIP\Utils;

/**
 * WARNING: This class cannot use WordPress-specific functions
 *
 * It's loaded and used very early and should rely only on constants and server context.
 */
class Context {
	/**
	 * Begin: VIP-specific contexts
	 */
	public static function is_vip_env() {
		// VIP_GO_ENV will have a string value with the environment on Go servers.
		// Will be `false` or undefined on non-Go servers.
		return defined( 'VIP_GO_ENV' ) && false !== constant( 'VIP_GO_ENV' );
	}

	public static function is_maintenance_mode() {
		return defined( 'WPCOM_VIP_SITE_MAINTENANCE_MODE' )
			&& true === constant( 'WPCOM_VIP_SITE_MAINTENANCE_MODE' );
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
		return false === self::is_wp_cli()
			&& false === self::is_ajax()
			&& false === self::is_installing()
			&& false === self::is_rest_api()
			&& false === self::is_xmlrpc_api()
			&& false === self::is_cron();
	}

	public static function is_wp_cli() {
		return defined( 'WP_CLI' ) && constant( 'WP_CLI' );
	}

	public static function is_rest_api() {
		return defined( 'REST_REQUEST' ) && true === constant( 'REST_REQUEST' );
	}

	public static function is_cron() {
		return defined( 'DOING_CRON' ) && true === constant( 'DOING_CRON' );
	}

	public static function is_xmlrpc_api() {
		return defined( 'XMLRPC_REQUEST' ) && true === constant( 'XMLRPC_REQUEST' );
	}

	public static function is_ajax() {
		return defined( 'DOING_AJAX' ) && true === constant( 'DOING_AJAX' );
	}

	public static function is_installing() {
		return defined( 'WP_INSTALLING' ) && true === constant( 'WP_INSTALLING' );
	}
}
