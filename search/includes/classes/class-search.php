<?php

namespace Automattic\VIP\Search;

use \WP_CLI;

class Search {
	public $healthcheck;
	private $current_host_index;

	/**
	 * Initialize the VIP Search plugin
	 */
	public function init() {
		$this->setup_constants();
		$this->setup_hooks();
		$this->load_dependencies();
		$this->load_commands();
		$this->setup_healthchecks();
	}

	protected function load_dependencies() {
		/**
		 * Load ES Health command class
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/commands/class-healthcommand.php';
		}

		// Load ElasticPress
		require_once __DIR__ . '/../../elasticpress/elasticpress.php';

		// Load health check cron job
		require_once __DIR__ . '/class-health-job.php';

		// Load our custom dashboard
		require_once __DIR__ . '/class-dashboard.php';
	}

	protected function setup_constants() {
		// Ensure we limit bulk indexing chunk size to a reasonable number (no limit by default)
		if ( ! defined( 'EP_SYNC_CHUNK_LIMIT' ) ) {
			define( 'EP_SYNC_CHUNK_LIMIT', 500 );
		}

		if ( ! defined( 'EP_HOST' ) && defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) && is_array( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			$host = $this->get_random_host( VIP_ELASTICSEARCH_ENDPOINTS );
			$this->current_host_index = array_search( $host, VIP_ELASTICSEARCH_ENDPOINTS );

			define( 'EP_HOST', $host );
		}

		if ( ! defined( 'ES_SHIELD' ) && ( defined( 'VIP_ELASTICSEARCH_USERNAME' ) && defined( 'VIP_ELASTICSEARCH_PASSWORD' ) ) ) {
			define( 'ES_SHIELD', sprintf( '%s:%s', VIP_ELASTICSEARCH_USERNAME, VIP_ELASTICSEARCH_PASSWORD ) );
		}

		// Do not allow sync via Dashboard (WP-CLI is preferred for indexing).
		// The Dashboard is hidden anyway but just in case.
		if ( ! defined( 'EP_DASHBOARD_SYNC' ) ) {
			define( 'EP_DASHBOARD_SYNC', false );
		}
	}

	protected function setup_hooks() {
		add_action( 'plugins_loaded', [ $this, 'action__plugins_loaded' ] );

		add_filter( 'ep_index_name', [ $this, 'filter__ep_index_name' ], PHP_INT_MAX, 3 ); // We want to enforce the naming, so run this really late.

		// Override default per page value set in elasticpress/includes/classes/Indexable.php
		add_filter( 'ep_bulk_items_per_page', [ $this, 'filter__ep_bulk_items_per_page' ], PHP_INT_MAX );

		// Network layer replacement to use VIP helpers (that handle slow/down upstream server)
		add_filter( 'ep_intercept_remote_request', '__return_true', 9999 );
		add_filter( 'ep_do_intercept_request', [ $this, 'filter__ep_do_intercept_request' ], 9999, 4 );

		// Disable query integration by default
		add_filter( 'ep_skip_query_integration', array( __CLASS__, 'ep_skip_query_integration' ), 5 );
		add_filter( 'ep_skip_user_query_integration', array( __CLASS__, 'ep_skip_query_integration' ), 5 );

		// Disable certain EP Features
		add_filter( 'ep_feature_active', array( $this, 'filter__ep_feature_active' ), PHP_INT_MAX, 3 );

		// Round-robin retry hosts if connection to a host fails
		add_filter( 'ep_pre_request_host', array( $this, 'filter__ep_pre_request_host' ), PHP_INT_MAX, 4 );
		
		add_filter( 'ep_valid_response', array( $this, 'filter__ep_valid_response' ), 10, 4 );

		// Allow querying while a bulk index is running
		add_filter( 'ep_enable_query_integration_during_indexing', '__return_true' );

		// Set facet taxonomies size. Shouldn't currently be used, but it makes sense to have it set to a sensible
		// default just in case it ends up in use so that the application doesn't error
		add_filter( 'ep_facet_taxonomies_size', array( $this, 'filter__ep_facet_taxonomies_size' ) );

		// Disable facet queries
		add_filter( 'ep_facet_include_taxonomies', '__return_empty_array' );
		
		add_filter( 'jetpack_search_should_handle_query', array( __CLASS__, 'jetpack_search_should_handle_query' ) );
	}

	protected function load_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'vip-search health', __NAMESPACE__ . '\Commands\HealthCommand' );
		}
	}

	protected function setup_healthchecks() {
		$this->healthcheck = new HealthJob();
	
		// Hook into init action to ensure cron-control has already been loaded
		add_action( 'init', [ $this->healthcheck, 'init' ] );
	}

	public function action__plugins_loaded() {
		// Conditionally load only if either/both Query Monitor and Debug Bar are loaded and enabled
		// NOTE - must hook in here b/c the wp_get_current_user function required for checking if debug bar is enabled isn't loaded earlier
		if ( apply_filters( 'debug_bar_enable', false ) || apply_filters( 'wpcom_vip_qm_enable', false ) ) {
			// Must be set to true to enable saving of queries in \ElasticPress\Elasticsearch
			if ( ! defined( 'WP_EP_DEBUG' ) ) {
				define( 'WP_EP_DEBUG', true );
			}

			// Load query log override function to remove Authorization header from requests
			require_once __DIR__ . '/../functions/ep-get-query-log.php';
			// Load ElasticPress Debug Bar
			require_once __DIR__ . '/../../debug-bar-elasticpress/debug-bar-elasticpress.php';
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

	/**
	 * Filter to set ep_bulk_items_per_page to 500
	 */
	public function filter__ep_bulk_items_per_page() {
		return 500;
	}

	public function filter__ep_do_intercept_request( $request, $query, $args, $failures ) {
		$fallback_error = new \WP_Error( 'vip-search-upstream-request-failed', 'There was an error connecting to the upstream search server' );

		$timeout = $this->get_http_timeout_for_query( $query );

		// Add custom headers to identify authorized traffic
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}
		$args['headers'] = array_merge( $args['headers'], array( 'X-Client-Site-ID' => FILES_CLIENT_SITE_ID, 'X-Client-Env' => VIP_GO_ENV ) );
		$request = vip_safe_wp_remote_request( $query['url'], $fallback_error, 3, $timeout, 20, $args );
	
		return $request;
	}

	public function get_http_timeout_for_query( $query ) {
		$timeout = 2;

		// If query url ends with '_bulk'
		$query_path = wp_parse_url( $query[ 'url' ], PHP_URL_PATH );

		if ( wp_endswith( $query_path, '_bulk' ) ) {
			// Bulk index request so increase timeout
			$timeout = 5;
		}

		return $timeout;
	}

	public function filter__ep_feature_active( $active, $feature_settings, $feature ) {
		$disabled_features = array(
			'documents',
			'users',
		);

		if ( in_array( $feature->slug, $disabled_features, true ) ) {
			return false;
		}

		return $active;
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

	public function filter__jetpack_widgets_to_include( $widgets ) {
		if ( ! is_array( $widgets ) ) {
			return $widgets;
		}

		foreach( $widgets as $index => $file ) {
			// If the Search widget is included and it's active on a site, it will automatically re-enable the Search module,
			// even though we filtered it to off earlier, so we need to prevent it from loading
			if( wp_endswith( $file, '/jetpack/modules/widgets/search.php' ) ) {
				unset( $widgets[ $index ] );
			}
		}

		// Flatten the array back down now that may have removed values from the middle (to keep indexes correct)
		$widgets = array_values( $widgets );

		return $widgets;
	}

	/**
	 * Separate plugin enabled and querying the index
	 *
	 * The index can be tested at any time by setting an `es` query argument.
	 * When the index is ready to serve requests in production, the `VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION`
	 * constant should be set to `true`, which will enable query integration for all requests
	 */
	static function ep_skip_query_integration( $skip ) {
		if ( isset( $_GET[ 'es' ] ) ) {
			return false;
		}

		/**
		 * Honor filters that skip query integration
		 *
		 * It may be desirable to skip query integration for specific
		 * queries. We should honor those other filters. Since this
		 * defaults to false, it will only kick in if someone specifically
		 * wants to bypass ES in addition to what we're doing here.
		 */
		if ( $skip ) {
			return true;
		}

		// Legacy constant name
		$query_integration_enabled_legacy = defined( 'VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION' ) && true === VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION;

		$query_integration_enabled = defined( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION' ) && true === VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION;

		// The filter is checking if we should _skip_ query integration
		return ! ( $query_integration_enabled || $query_integration_enabled_legacy );
	}

	/**
	 * Filter for ep_pre_request_host
	 *
	 * Return the next host in our enpoint list if it's defined. Otherwise, return the last host.
	 */
	public function filter__ep_pre_request_host( $host, $failures, $path, $args ) {
		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) { 
			return $host;
		}

		if ( ! is_array( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			return $host;
		}

		if ( 0 === count( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			return $host;
		}

		return $this->get_next_host( VIP_ELASTICSEARCH_ENDPOINTS, $failures );
	}

	/**
	 * Return the next host in the list based on the current host index
	 */
	public function get_next_host( $hosts, $failures ) {
		$this->current_host_index = ( $this->current_host_index + $failures ) % count( $hosts );
		
		return $hosts[ $this->current_host_index ];
	} 

	/**
	 * Given a list of hosts, randomly select one for load balancing purposes.
	 */
	public function get_random_host( $hosts ) {
		if ( ! is_array( $hosts ) ) {
			return $hosts;
		}

		return $hosts[ array_rand( $hosts ) ];
	}

	public function filter__ep_valid_response( $response, $query, $query_args, $query_object ) {
		if ( ! headers_sent() ) {
			/**
			 * Manually set a header to indicate the search results are from elasticSearch
			 */
			if ( isset( $_GET['ep_debug'] ) ) {
				header( 'X-ElasticPress-Search-Valid-Response: true' );
			}
		}
		return $response;
	}

	/*
	 * Given the current facet taxonomies size and a taxonomy, determine the facet taxonomy size
	 */
	public function filter__ep_facet_taxonomies_size( $size, $taxonomy ) {
		return 5;
	}
	
	/**
	 * Disable Jetpack Search if ElasticPress is enabled
	 *
	 * When we enable the ElasticPress query integration, we want to simultaneously
	 * ensure that queries are not being offloaded to Jetpack Search.
	 *
	 * This also means that any testing of ElasticPress will not have queries offloaded
	 * to Jetpack Search.
	 */
	protected static function jetpack_search_should_handle_query( $should_handle_query ) {
		if ( false === self::ep_skip_query_integration( true ) ) {
			return false;
		}
		
		return $should_handle_query;
	}
}
