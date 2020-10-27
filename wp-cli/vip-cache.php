<?php

class VIP_Cache_CLI extends WPCOM_VIP_CLI_Command {
	/**
	 * Purges the site's Varnish cache.
	 */
	public function purge( $args, $assoc_args ) {
		// This sets up the URL/regex to be banned.  There's no need to manually
		// execute a purge since it is handled automatically on the `shutdown` hook.
		WPCOM_VIP_Cache_Manager::instance()->purge_site_cache();
		if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
			WP_CLI::line( 'Because you are sandboxed this has only purged the Varnish cache on your sandbox host, un-sandbox and use the button in the site admin area if you need to purge the VIP Go edge cache.' );
		}
		WP_CLI::success( 'Varnish cache purged!' );
	}

	/**
	 * Purges a specific URL from Varnish
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : URL to purge
	 *
	 * ## EXAMPLES
	 *
	 * wp vip cache purge-url https://example.com/full-url/?with-query-args
	 * @subcommand purge-url
	 */
	public function purge_url( $args, $assoc_args ) {
		if ( ! wp_http_validate_url( $args[0] ) ) {
			WP_CLI::error( 'Invalid URL:' . $args[0] );
		}

		// Queue a specific URL purge
		wpcom_vip_purge_edge_cache_for_url( $args[0] );
		if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
			WP_CLI::line( 'This has only purged the Varnish cache on your sandbox. If you need to purge the production caches, please un-sandbox first and then run this command again..' );
		}
		WP_CLI::success( 'URL purged from Varnish!' );
	}
}

WP_CLI::add_command( 'vip cache', 'VIP_Cache_CLI' );
