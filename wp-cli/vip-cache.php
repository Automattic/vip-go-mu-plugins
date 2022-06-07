<?php

class VIP_Cache_CLI extends WPCOM_VIP_CLI_Command {
	/**
	 * Purges the VIP Page Cache.
	 *
	 * Warning: on large and high traffic sites, this can severely impact site performance and stability. Use with caution.
	 *
	 * ## OPTIONS
	 *
	 * [--skip-confirm] force purge without confirmation.
	 */
	public function purge( $args, $assoc_args ) {
		if ( isset( $args[0] ) && wp_http_validate_url( $args[0] ) ) {
			$this->purge_url( $args, $assoc_args );
			return;
		}

		if ( ! isset( $assoc_args['skip-confirm'] ) ) {
			WP_CLI::confirm( "⚠️ You're about to invalidate Page Cache for the whole site, this can severely impact performance and stability for large sites. Are you sure?" );
		}

		// This sets up the URL/regex to be banned.  There's no need to manually
		// execute a purge since it is handled automatically on the `shutdown` hook.
		WPCOM_VIP_Cache_Manager::instance()->purge_site_cache();
		if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
			WP_CLI::line( 'Because you are sandboxed this has only purged the Page Cache on your sandbox host, un-sandbox and use the button in the site admin area if you need to purge the VIP Go edge cache.' );
		}
		WP_CLI::success( 'VIP Page Cache purged!' );
	}

	/**
	 * Purges a specific URL from the VIP Page Cache
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : URL to purge
	 *
	 * ## EXAMPLES
	 *
	 * wp vip cache purge-url https://example.com/full-url/?with-query-args
	 *
	 * @subcommand purge-url
	 */
	public function purge_url( $args, $assoc_args ) {
		if ( ! wp_http_validate_url( $args[0] ) ) {
			WP_CLI::error( 'Invalid URL:' . $args[0] );
		}

		// Queue a specific URL purge.
		wpcom_vip_purge_edge_cache_for_url( $args[0] );
		if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
			WP_CLI::line( 'This has only purged the Page Cache on your sandbox. If you need to purge the production caches, please un-sandbox first and then run this command again..' );
		}
		WP_CLI::success( 'URL purged from the VIP page cache.' );
	}
}

WP_CLI::add_command( 'vip cache', 'VIP_Cache_CLI' );
