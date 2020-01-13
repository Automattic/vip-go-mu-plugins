<?php

namespace Automattic\VIP\Elasticsearch;

use \WP_CLI;

class Elasticsearch {

	/**
	 * Initialize the VIP Elasticsearch plugin
	 */
	public function init() {
		$this->load_dependencies();
		$this->setup_constants();
		$this->setup_hooks();
		$this->load_commands();
	}

	protected function load_dependencies() {
		/**
		 * Load ES Health command class
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/commands/class-health-command.php';
		}
	}

	protected function setup_constants() {
		// Ensure we limit bulk indexing chunk size to a reasonable number (no limit by default)
		if ( ! defined( 'EP_SYNC_CHUNK_LIMIT' ) ) {
			define( 'EP_SYNC_CHUNK_LIMIT', 250 );
		}
	}

	protected function setup_hooks() {
		add_filter( 'ep_index_name', [ $this, 'filter__ep_index_name' ], PHP_INT_MAX, 3 ); // We want to enforce the naming, so run this really late.

		// Network layer replacement to use VIP helpers (that handle slow/down upstream server)
		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter( 'ep_do_intercept_request', [ $this, 'filter__ep_do_intercept_request' ], PHP_INT_MAX, 4 );
	}

	protected function load_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'vip-es health', __NAMESPACE__ . '\Health_Command' );
		}
	}

	/**
	 * Filter ElasticPress index name if using VIP ES infrastructure
	 */
	public function filter__ep_index_name( $index_name, $blog_id, $indexables ) {
		// TODO: Use FILES_CLIENT_SITE_ID for now as VIP_GO_ENV_ID is not ready yet. Should replace once it is.
		$index_name = sprintf( 'vip-%s-%s', FILES_CLIENT_SITE_ID, $indexables->slug );

		// $blog_id won't be present on global indexes (such as users)
		if ( $blog_id ) {
			$index_name .= sprintf( '-%s', $blog_id );
		}

		return $index_name;
	}

	public function filter__ep_do_intercept_request( $request, $query, $args, $failures ) {
		$fallback_error = new WP_Error( 'vip-elasticsearch-upstream-request-failed', 'There was an error connecting to the upstream Elasticsearch server' );

		$request = vip_safe_wp_remote_request( $query['url'], $fallback_error, 3, 1, 20, $args );
	
		return $request;
	}
}
