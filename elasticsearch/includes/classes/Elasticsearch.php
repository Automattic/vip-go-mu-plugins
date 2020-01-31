<?php

namespace Automattic\VIP\Elasticsearch;

use \WP_CLI;

class Elasticsearch {
	/**
	 * Initialize the VIP Elasticsearch plugin
	 */
	public function init() {
		$this->setup_constants();
		$this->setup_hooks();
		$this->load_dependencies();
		$this->load_commands();
	}

	protected function load_dependencies() {
		/**
		 * Load ES Health command class
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/commands/HealthCommand.php';
		}
		// Load ElasticPress
		require_once __DIR__ . '/../../elasticpress/elasticpress.php';
	}

	protected function setup_constants() {
		// Ensure we limit bulk indexing chunk size to a reasonable number (no limit by default)
		if ( ! defined( 'EP_SYNC_CHUNK_LIMIT' ) ) {
			define( 'EP_SYNC_CHUNK_LIMIT', 250 );
		}

		if ( ! defined( 'EP_HOST' ) && defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) && is_array( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			$host = VIP_ELASTICSEARCH_ENDPOINTS[ 0 ];

			define( 'EP_HOST', $host );
		}

		if ( ! defined( 'ES_SHIELD' ) && ( defined( 'VIP_ELASTICSEARCH_USERNAME' ) && defined( 'VIP_ELASTICSEARCH_PASSWORD' ) ) ) {
			define( 'ES_SHIELD', sprintf( '%s:%s', VIP_ELASTICSEARCH_USERNAME, VIP_ELASTICSEARCH_PASSWORD ) );
		}
	}

	protected function setup_hooks() {
		add_filter( 'ep_index_name', [ $this, 'filter__ep_index_name' ], PHP_INT_MAX, 3 ); // We want to enforce the naming, so run this really late.

		// Network layer replacement to use VIP helpers (that handle slow/down upstream server)
		add_filter( 'ep_intercept_remote_request', '__return_true', 9999 );
		add_filter( 'ep_do_intercept_request', [ $this, 'filter__ep_do_intercept_request' ], 9999, 4 );
		add_filter( 'jetpack_active_modules', [ $this, 'filter__jetpack_active_modules' ], 9999 );
	}

	protected function load_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'vip-es health', __NAMESPACE__ . '\HealthCommand' );
		}
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
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
		$fallback_error = new \WP_Error( 'vip-elasticsearch-upstream-request-failed', 'There was an error connecting to the upstream Elasticsearch server' );

		// TEMP - currently ES server is using a self signed cert during the testing phase...that'll be changed in the near
		// future, at which time we can remove this
		$args['sslverify'] = false;
	
		$request = vip_safe_wp_remote_request( $query['url'], $fallback_error, 3, 1, 20, $args );
	
		return $request;
	}

	public function filter__jetpack_active_modules( $modules ) {
		// Filter out 'search' from the active modules. We use array_filter() to get _all_ instances, as it could be present multiple times
		$filtered = array_filter ( $modules, function( $module ) {
			if ( 'search' === $module ) {
				return false;
			}
			return true;
		} );

		// array_filter() preserves keys, so to get a clean / flat array we must pass it through array_values()
		return array_values( $filtered );
	}
}
