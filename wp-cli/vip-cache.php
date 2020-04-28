<?php

class VIP_Cache_CLI extends WPCOM_VIP_CLI_Command {
	/**
	 * Purges the site's varnish cache.
	 */
	function purge( $args, $assoc_args ) {
		// This sets up the URL/regex to be banned.  There's no need to manually
		// execute a purge since it is handled automatically on the `shutdown` hook.
		WPCOM_VIP_Cache_Manager::instance()->purge_site_cache();
		if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
			WP_CLI::line( 'Because you are sandboxed this has only purged the Varnish cache on your sandbox host, un-sandbox and use the button in the site admin area if you need to purge the VIP Go edge cache.' );
		}
		WP_CLI::success( 'Varnish cache purged!' );
	}
}

WP_CLI::add_command( 'vip cache', 'VIP_Cache_CLI' );
