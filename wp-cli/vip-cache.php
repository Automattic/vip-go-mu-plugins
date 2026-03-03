<?php

class VIP_Cache_CLI extends WPCOM_VIP_CLI_Command {
	/**
	 * Purges the VIP Page Cache.
	 *
	 * Warning: on large and high traffic sites, this can severely impact site performance and stability. Use with caution.
	 *
	 * ## OPTIONS
	 *
	 * [--scope=<scope>]
	 * : Limit the purge to a specific cache scope. Defaults to site.
	 * ---
	 * default: site
	 * options:
	 *   - site
	 *   - origin
	 *   - uploads
	 *   - static
	 *   - private
	 * ---
	 *
	 * [--skip-confirm] force purge without confirmation (site scope only).
	 */
	public function purge( $args, $assoc_args ) {
		if ( isset( $args[0] ) && wp_http_validate_url( $args[0] ) ) {
			$this->purge_url( $args, $assoc_args );
			return;
		}

		$scope  = isset( $assoc_args['scope'] ) ? $assoc_args['scope'] : 'site';
		$scopes = $this->get_scope_actions();

		if ( ! isset( $scopes[ $scope ] ) ) {
			WP_CLI::error( sprintf( 'Invalid scope "%s". Allowed scopes: %s', $scope, implode( ', ', array_keys( $scopes ) ) ) );
		}

		if ( 'site' === $scope && ! isset( $assoc_args['skip-confirm'] ) ) {
			WP_CLI::confirm(
				sprintf(
					"⚠️ You're about to invalidate Page Cache for (%s). This can impact performance and stability for large sites. Are you sure?",
					trailingslashit( home_url() )
				)
			);
		}

		call_user_func( $scopes[ $scope ]['callback'] );

		WP_CLI::success( sprintf( 'Queued %s purge.', $scopes[ $scope ]['label'] ) );
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
		wpvip_purge_edge_cache_for_url( $args[0] );

		WP_CLI::success( sprintf( 'Queued purge for URL: %s', $args[0] ) );
	}

	private function get_scope_actions(): array {
		return [
			'site'    => [
				'label'    => 'entire site cache',
				'callback' => [ WPCOM_VIP_Cache_Manager::instance(), 'purge_site_cache' ],
			],
			'origin'  => [
				'label'    => 'origin content cache',
				'callback' => 'wpvip_purge_edge_cache_for_origin_content',
			],
			'uploads' => [
				'label'    => 'uploads cache',
				'callback' => 'wpvip_purge_edge_cache_for_uploads',
			],
			'static'  => [
				'label'    => 'static files cache',
				'callback' => 'wpvip_purge_edge_cache_for_static_files',
			],
			'private' => [
				'label'    => 'private file visibility cache',
				'callback' => 'wpvip_purge_edge_cache_for_private_files',
			],
		];
	}
}

WP_CLI::add_command( 'vip cache', 'VIP_Cache_CLI' );
