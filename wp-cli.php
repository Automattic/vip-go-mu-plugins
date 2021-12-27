<?php

/**
 * Plugin Name: WP-CLI for VIP Go
 * Description: Scripts for VIP Go
 * Author: Automattic
 */

namespace Automattic\VIP\WP_CLI;

function init_is_ssl_toggle() {
	maybe_toggle_is_ssl();

	if ( is_multisite() ) {
		init_is_ssl_toggle_for_multisite();
	}
}

// Any time a blog is switched, we should toggle is_ssl() based on their preferred scheme.
function init_is_ssl_toggle_for_multisite() {
	add_action( 'switch_blog', function( $new_blog_id, $prev_blog_id ) {
		// Not a strict equality check to match core
		if ( ! wp_is_site_initialized( $new_blog_id ) || $new_blog_id == $prev_blog_id ) {
			return;
		}

		maybe_toggle_is_ssl();
	}, 0, 2 ); // run early since this could impact other filters
}

/**
 * Fixes is_ssl() for wp-cli requests.
 *
 * `get_site_url()` will force the scheme to `http` when `$_SEVER['HTTPS']` is not set.
 * This can be problematic if the site is always using SSL (i.e. home/siteurl have `https` URLs).
 * This function toggles the setting so we get correct URLs generated in the wp-cli context.
 */
function maybe_toggle_is_ssl() {
	$is_ssl_siteurl = wp_startswith( get_option( 'siteurl' ), 'https:' );

	if ( $is_ssl_siteurl && ! is_ssl() ) {
		$_SERVER['HTTPS'] = 'on';
	} elseif ( ! $is_ssl_siteurl && is_ssl() ) {
		unset( $_SERVER['HTTPS'] );
	}
}

/**
 * Disable `display_errors` for all wp-cli interactions on production servers.
 *
 * Warnings and notices can break things like JSON output,
 * especially for critical plugins like cron-control.
 *
 * Only do this on production servers to allow local and sandbox debugging.
 */
function disable_display_errors() {
	if ( true !== WPCOM_IS_VIP_ENV ) {
		return;
	}

	if ( true === WPCOM_SANDBOXED ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.IniSet.display_errors_Blacklisted
	ini_set( 'display_errors', 0 );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	disable_display_errors();

	init_is_ssl_toggle();

	foreach ( glob( __DIR__ . '/wp-cli/*.php' ) as $command ) {
		require $command;
	}

	/**
	 * Register the Async Command Scheduler Runner hook.
	 */
	add_action( \Automattic\VIP\Commands\Async_Scheduler_Command::COMMAND_CRON_EVENT_KEY, [ '\Automattic\VIP\Commands\Async_Scheduler_Command', 'runner' ] );
}
