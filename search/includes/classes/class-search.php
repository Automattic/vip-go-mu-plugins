<?php

namespace Automattic\VIP\Search;

use \WP_CLI;

class Search {
	public $healthcheck;
	public $queue;
	private $mirrored_wp_query_queue = array();
	private $current_host_index = 0;

	public const QUERY_COUNT_CACHE_KEY = 'query_count';
	public const QUERY_COUNT_CACHE_GROUP = 'vip_search';
	public static $max_query_count = 50000 + 1; // 10 requests per second plus one for cleanliness of comparing with Search::query_count_incr
	public static $query_db_fallback_value = 5; // Value to compare >= against rand( 1, 10 ). 5 should result in roughly half being true.
	private const QUERY_COUNT_TTL = 300; // 5 minutes in seconds 

	private const MAX_SEARCH_LENGTH = 255;

	private static $_instance;

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

	public static function instance() {
		if ( ! ( static::$_instance instanceof Search ) ) {
			static::$_instance = new Search();
			static::$_instance->init();
		}

		return static::$_instance;
	}

	protected function load_dependencies() {
		/**
		 * Load ES Health command class
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/commands/class-healthcommand.php';
			require_once __DIR__ . '/commands/class-queuecommand.php';
		}

		// Load ElasticPress
		require_once __DIR__ . '/../../elasticpress/elasticpress.php';

		// Load health check cron job
		require_once __DIR__ . '/class-health-job.php';

		// Load our custom dashboard
		require_once __DIR__ . '/class-dashboard.php';

		require_once __DIR__ . '/class-queue.php';

		$this->queue = new Queue();
		$this->queue->init();
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
		add_filter( 'ep_skip_query_integration', array( __CLASS__, 'ep_skip_query_integration' ), 5, 2 );
		add_filter( 'ep_skip_user_query_integration', array( __CLASS__, 'ep_skip_query_integration' ), 5 );
		// Rate limit query integration
		add_filter( 'ep_skip_query_integration', array( __CLASS__, 'rate_limit_ep_query_integration' ), PHP_INT_MAX );

		// Disable certain EP Features
		add_filter( 'ep_feature_active', array( $this, 'filter__ep_feature_active' ), PHP_INT_MAX, 3 );

		// Round-robin retry hosts if connection to a host fails
		add_filter( 'ep_pre_request_host', array( $this, 'filter__ep_pre_request_host' ), PHP_INT_MAX, 4 );
		
		add_filter( 'ep_valid_response', array( $this, 'filter__ep_valid_response' ), 10, 4 );

		// Allow querying while a bulk index is running
		add_filter( 'ep_enable_query_integration_during_indexing', '__return_true' );

		// Set facet taxonomies size. Shouldn't currently be used, but it makes sense to have it set to a sensible
		// default just in case it ends up in use so that the application doesn't error
		add_filter( 'ep_facet_taxonomies_size', array( $this, 'filter__ep_facet_taxonomies_size' ), 10, 2 );

		// Disable facet queries
		add_filter( 'ep_facet_include_taxonomies', '__return_empty_array' );

		// Enable track_total_hits for all queries for proper result sets if track_total_hits isn't already set
		add_filter( 'ep_post_formatted_args', array( $this, 'filter__ep_post_formatted_args' ), 10, 3 );

		// Early hook for modifying behavior of main query
		add_action( 'wp', array( $this, 'action__wp' ) );

		// Disable query fuzziness by default
		add_filter( 'ep_fuzziness_arg', '__return_zero', 0 );

		// Replace base 'should' with 'must' in Elasticsearch query if formatted args structure matches what's expected
		add_filter( 'ep_formatted_args', array( $this, 'filter__ep_formatted_args' ), 0, 2 );

		// Disable indexing of filtered content by default, as it's not searched by default
		add_filter( 'ep_allow_post_content_filtered_index', '__return_false' );

		// Better shard counts
		add_filter( 'ep_default_index_number_of_shards', array( $this, 'filter__ep_default_index_number_of_shards' ) );

		// Better replica counts
		add_filter( 'ep_default_index_number_of_replicas', array( $this, 'filter__ep_default_index_number_of_replicas' ) );
		
		// Date relevancy defaults. Taken from Jetpack Search.
		// Set to 'gauss'
		add_filter( 'epwr_decay_function', array( $this, 'filter__epwr_decay_function' ), 0, 3 );
		// Set to '360d'	
		add_filter( 'epwr_scale', array( $this, 'filter__epwr_scale' ), 0, 3 );
		// Set to .9 
		add_filter( 'epwr_decay', array( $this, 'filter__epwr_decay' ), 0, 3 );
		// Set to '0d'
		add_filter( 'epwr_offset', array( $this, 'filter__epwr_offset' ), 0, 3 );
		// Set to 'multiply'	
		add_filter( 'epwr_score_mode', array( $this, 'filter__epwr_score_mode' ), 0, 3 );
		// Set to 'multiply'
		add_filter( 'epwr_boost_mode', array( $this, 'filter__epwr_boost_mode' ), 0, 3 );

		// For testing, mirror certain WP_Query's on certain sites
		if ( $this->is_query_mirroring_enabled() ) {
			add_filter( 'the_posts', array( $this, 'filter__the_posts' ), 10, 2 );
			add_action( 'shutdown', array( $this, 'action__shutdown_do_mirrored_wp_queries' ) );
		}

		// Truncate search strings to a reasonable length
		add_action( 'parse_query', array( $this, 'truncate_search_string_length' ), PHP_INT_MAX );

	}

	protected function load_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'vip-search health', __NAMESPACE__ . '\Commands\HealthCommand' );
			WP_CLI::add_command( 'vip-search queue', __NAMESPACE__ . '\Commands\QueueCommand' );
		}
	}

	protected function setup_healthchecks() {
		$this->healthcheck = new HealthJob();
	
		// Hook into init action to ensure cron-control has already been loaded
		add_action( 'init', [ $this->healthcheck, 'init' ] );
	}

	public function query_es( $type, $es_args = array(), $wp_query_args = array(), $index_name = null ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return new \WP_Error( 'indexable-not-found', 'Invalid query type specified. Must be a valid Indexable from ElasticPress' );
		}

		return $indexable->query_es( $es_args, $wp_query_args, $index_name );
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

		// Load es-wp-query, if not already loaded. This is done during plugins_loaded so we don't conflict
		// with sites that have included this plugin themselves
		if ( ! class_exists( '\\ES_WP_Query' ) ) {
			require_once __DIR__ . '/../../es-wp-query/es-wp-query.php';

			if ( function_exists( 'es_wp_query_load_adapter' ) ) {
				es_wp_query_load_adapter( 'vip-search' );
			}
		}
	}

	public function action__wp() {
		global $wp_query;

		// Avoid infinite loops because our requests load the URL with this param.
		if ( isset( $_GET['es'] ) ) {
			return;
		}

		// Temp functionality for testing phase.
		// If this was a regular search page and VIP Search was _not_ used, and if the site is configured to do so,
		// re-run the same query, but with `es=true`, via JS to test both systems in parallel
		if ( is_search() && ! isset( $wp_query->elasticsearch_success ) ) {
			$is_mirroring_enabled = $this->is_query_mirroring_enabled();

			if ( $is_mirroring_enabled ) {
				add_action( 'shutdown', [ $this, 'do_mirror_search_request' ] );
			}
		}
	}

	public function is_query_mirroring_enabled() {
		$is_enabled_by_constant = defined( 'VIP_ENABLE_SEARCH_QUERY_MIRRORING' ) && true === VIP_ENABLE_SEARCH_QUERY_MIRRORING;

		$option_value = get_option( 'vip_enable_search_query_mirroring' );
		$is_enabled_by_option = in_array( $option_value, array( true, 'true', 'yes', 1, '1' ), true );

		$is_mirroring_enabled = $is_enabled_by_constant || $is_enabled_by_option;

		return $is_mirroring_enabled;
	}

	public function do_mirror_search_request() {
		fastcgi_finish_request();

		$vip_search_url = home_url( add_query_arg( 'es', 'true' ) );

		wp_remote_request( $vip_search_url, [
			'user-agent' => sprintf( 'VIP Search Query Mirror; %s', home_url() ),
			'blocking' => false,
			// Shouldn't take this long but give it some breathing room.
			// Also not necessary with blocking=>false, but just in case.
			'timeout' => 3,
		] );
	}

	/**
	 * Filter that runs at the end of WP_Query, used to transparently mirror certain WP_Query's on certain sites
	 * for testing / evaluation
	 */
	public function filter__the_posts( $posts, $query ) {
		// If this query is one that should be transparently mirrored, run the mirroring
		$should_mirror = $this->should_mirror_wp_query( $query );

		if ( $should_mirror ) {
			$this->queue_mirrored_wp_query( $query );
		}

		return $posts;
	}

	public function should_mirror_wp_query( $query ) {
		// If this was already an ES query, don't mirror
		if ( isset( $query->query_vars['es'] ) || isset( $query->query_vars['ep_integrate'] ) || isset( $query->query_vars['vip_search_mirrored'] ) || isset( $query->elasticsearch_success ) ) {
			return false;
		}

		// If mirroring is not enabled at all, skip
		if ( ! $this->is_query_mirroring_enabled() ) {
			return false;
		}

		// Is this one of the targeted queries?
		if ( defined( 'VIP_GO_APP_ID' ) ) {
			if ( 2160 === VIP_GO_APP_ID && $query->is_main_query() && ( $query->is_category() || $query->is_archive() ) ) {
				return true;
			}

			$offload_main_tax_site_ids = array(
				929,
			);

			if ( in_array( VIP_GO_APP_ID, $offload_main_tax_site_ids, true ) && $query->is_main_query() && ( $query->is_category() || $query->is_tax() || $query->is_tag() ) ) {
				return true;
			}
		}

		return false;
	}

	public function queue_mirrored_wp_query( $query ) {
		$this->mirrored_wp_query_queue[] = $query;
	}

	public function do_mirror_wp_query( $query ) {
		$mirrored_query = $this->get_mirrored_wp_query( $query );

		$statsd_mode = 'mirrored_wp_query';

		// Pull index name using the indexable slug from the EP indexable singleton
		$statsd_index_name = \ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();

		$url = $this->get_current_host();
		$stat = $this->get_statsd_prefix( $url, $statsd_mode );
		$per_site_stat = $this->get_statsd_prefix( $url, $statsd_mode, FILES_CLIENT_SITE_ID, $statsd_index_name );

		$statsd = new \Automattic\VIP\StatsD();

		$statsd->increment( $stat );
		$statsd->increment( $per_site_stat );

		$diff = $this->diff_mirrored_wp_query_results( $query->posts, $mirrored_query->posts );

		if ( ! empty( $diff ) ) {
			$this->log_mirrored_wp_query_diff( $query, $diff );

			// Record a stat for the diff
			$statsd_mode = 'mirrored_wp_query_inconsistent';

			$stat = $this->get_statsd_prefix( $url, $statsd_mode );
			$per_site_stat = $this->get_statsd_prefix( $url, $statsd_mode, FILES_CLIENT_SITE_ID, $statsd_index_name );

			$statsd->increment( $stat );
			$statsd->increment( $per_site_stat );
		}
	}

	public function action__shutdown_do_mirrored_wp_queries() {
		if ( ! $this->is_query_mirroring_enabled() ) {
			return;
		}

		if ( empty( $this->mirrored_wp_query_queue ) || ! is_array( $this->mirrored_wp_query_queue ) ) {
			return;
		}

		fastcgi_finish_request();

		foreach ( $this->mirrored_wp_query_queue as $query ) {
			$this->do_mirror_wp_query( $query );
		}
	}

	public function get_mirrored_wp_query( $query ) {
		$mirrored_vars = $query->query_vars; // Arrays are passed by value so won't be affecting originals

		// Enable ES integration
		$mirrored_vars['es'] = true;

		// Mark this as a mirrored query (passes check in filter__ep_skip_query_integration() when query integration is otherwise disabled)
		$mirrored_vars['vip_search_mirrored'] = true;

		$mirrored_query = new \WP_Query( $mirrored_vars );

		return $mirrored_query;
	}

	public function diff_mirrored_wp_query_results( $original_posts, $mirrored_posts ) {
		// Normalize
		if ( ! is_array( $original_posts ) ) {
			$original_posts = array();
		}

		if ( ! is_array( $mirrored_posts ) ) {
			$mirrored_posts = array();
		}
		
		$original_post_ids = wp_list_pluck( $original_posts, 'ID' );
		$mirrored_post_ids = wp_list_pluck( $mirrored_posts, 'ID' );

		$missing = array_diff( $original_post_ids, $mirrored_post_ids );
		$extra = array_diff( $mirrored_post_ids, $original_post_ids );

		if ( empty( $missing ) && empty( $extra ) ) {
			return null;
		}

		return array(
			'missing' => array_values( $missing ),
			'extra' => array_values( $extra ),
		);
	}

	public function log_mirrored_wp_query_diff( $query, $diff ) {
		\Automattic\VIP\Logstash\log2logstash( array(
			'severity' => 'warning',
			'feature' => 'vip_search_wp_query_mirroring',
			'message' => 'Inconsistent mirrored offloaded WP_Query results detected',
			'extra' => array(
				'query_vars' => $query->query_vars,
				'diff' => $diff,
				'uri' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null,
			),
		) );
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

		// Add custom headers to identify authorized traffic
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}

		$args['headers'] = array_merge( $args['headers'], array( 'X-Client-Site-ID' => FILES_CLIENT_SITE_ID, 'X-Client-Env' => VIP_GO_ENV ) );

		$statsd = new \Automattic\VIP\StatsD();

		$statsd_mode = $this->get_statsd_request_mode_for_request( $query['url'], $args );
		$statsd_index_name = $this->get_statsd_index_name_for_url( $query['url'] );

		$statsd_prefix = $this->get_statsd_prefix( $query['url'], $statsd_mode );
		$statsd_per_site_prefix = $this->get_statsd_prefix( $query['url'], $statsd_mode, FILES_CLIENT_SITE_ID, $statsd_index_name );

		$start_time = microtime( true );

		$timeout = $this->get_http_timeout_for_query( $query, $args );

		$request = vip_safe_wp_remote_request( $query['url'], $fallback_error, 3, $timeout, 20, $args );

		$end_time = microtime( true );

		$duration = ( $end_time - $start_time ) * 1000;

		if ( is_wp_error( $request ) ) {
			$error_messages = $request->get_error_messages();
			
			foreach ( $error_messages as $error_message ) {
				// Default stat for errors is 'error'
				$stat = '.error';
				// If curl error 28(timeout), the stat should be 'timeout'	
				if ( $this->is_curl_timeout( $error_message ) ) {
					$stat = '.timeout';
				}

				$statsd->increment( $statsd_prefix . $stat );
				$statsd->increment( $statsd_per_site_prefix . $stat );
			}
		} else {
			// Record engine time (have to parse JSON to get it)
			$response_body = wp_remote_retrieve_body( $request );
			$response = json_decode( $response_body, true );

			if ( $response && isset( $response['took'] ) && is_int( $response['took'] ) ) {
				$statsd->timing( $statsd_prefix . '.engine', $response['took'] );
				$statsd->timing( $statsd_per_site_prefix . '.engine', $response['took'] );
			}

			$statsd->timing( $statsd_prefix . '.total', $duration );
			$statsd->timing( $statsd_per_site_prefix . '.total', $duration );
		}
	
		return $request;
	}

	/*
	 * Given an error message, determine if it's from curl error 28(timeout)
	 */
	private function is_curl_timeout( $error_message ) {
		return false !== strpos( strtolower( $error_message ), 'curl error 28' ); 
	}

	public function get_http_timeout_for_query( $query, $args ) {
		$timeout = 2;

		$query_path = wp_parse_url( $query[ 'url' ], PHP_URL_PATH );
		$is_post_request = false;

		if ( isset( $args['method'] ) && 0 === strcasecmp( 'POST', $args['method'] ) ) {
			$is_post_request = true;
		}

		// Bulk index request so increase timeout
		if ( wp_endswith( $query_path, '_bulk' ) ) {
			$timeout = 5;

			if ( defined( 'WP_CLI' ) && WP_CLI && $is_post_request ) {
				$timeout = 30;
			} elseif ( \is_admin() && $is_post_request ) {
				$timeout = 15;
			}
		}

		return $timeout;
	}

	public function filter__ep_feature_active( $active, $feature_settings, $feature ) {
		$disabled_features = array(
			'documents',
		);

		if ( in_array( $feature->slug, $disabled_features, true ) ) {
			return false;
		}

		return $active;
	}

	/**
	 * Separate plugin enabled and querying the index
	 *
	 * The index can be tested at any time by setting an `es` query argument.
	 * When the index is ready to serve requests in production, the `VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION`
	 * constant should be set to `true`, which will enable query integration for all requests
	 */
	public static function ep_skip_query_integration( $skip, $query = null ) {
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
		
		if ( isset( $_GET['es'] ) ) {
			return false;
		}

		// Bypass a bug in EP Facets that causes aggregations to be run on the main query
		// This is intended to be a temporary workaround until a better fix is made
		$bypassed_on_main_query_site_ids = [
			1284,
			1286,
		];

		if ( defined( 'VIP_GO_APP_ID' ) ) {
			if ( in_array( VIP_GO_APP_ID, $bypassed_on_main_query_site_ids, true ) ) {
				// Prevent integration on non-search main queries (Facets can wrongly enable itself here)
				if ( $query && $query->is_main_query() && ! $query->is_search() ) {
					return true;
				}
			}
		}

		// If query is marked for mirroring (for evaluation phase), allow it
		if ( isset( $query->query_vars['vip_search_mirrored'] ) && $query->query_vars['vip_search_mirrored'] ) {
			return false;
		}

		// Legacy constant name
		$query_integration_enabled_legacy = defined( 'VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION' ) && true === VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION;

		$query_integration_enabled = defined( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION' ) && true === VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION;

		$enabled_by_constant = ( $query_integration_enabled || $query_integration_enabled_legacy );

		$option_value = get_option( 'vip_enable_vip_search_query_integration' );

		$enabled_by_option = in_array( $option_value, array( true, 'true', 'yes', 1, '1' ), true );

		// The filter is checking if we should _skip_ query integration...so if it's _not_ enabled
		$skipped = ! ( $enabled_by_constant || $enabled_by_option );
	
		return $skipped;
	}

	/**
	 * Filter for ep_skip_query_integration that enabled rate limiting. Should be run last
	 *
	 * Honor any previous filters that skip query integration. If query integration is
	 * continuing, check if the query is past the ratelimiting threshold. If it is, send
	 * roughly half of the queries received to the database and half through ElasticPress.
	 *
	 * @param $skip current ep_skip_query_integration value
	 * @return bool new value of ep_skip_query_integration
	 */
	public static function rate_limit_ep_query_integration( $skip ) {
		// Honor previous filters that skip query integration
		if ( $skip ) {
			return true;
		}

		// If the query count has exceeded the maximum
		// only allow half of the queries to use VIP Search
		if ( self::query_count_incr() > self::$max_query_count ) {
			// Should be roughly half over time
			if ( self::$query_db_fallback_value >= rand( 1, 10 ) ) {
				return true;
			}
		}

		return false;
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

		return $this->get_next_host( $failures );
	}

	/**
	 * Return the next host in the list based on the current host index
	 */
	public function get_next_host( $failures ) {
		$this->current_host_index += $failures;
		
		return $this->get_current_host();
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

	/*
	 * Remove the search module from active Jetpack modules
	 */
	public function filter__jetpack_active_modules( $modules ) {
		// Flatten the array back down now that may have removed values from the middle (to keep indexes correct)
		return array_values( array_filter( $modules, function( $module ) {
			if ( 'search' === $module ) {
				return false;
			}
			return true;
		} ) ); // phpcs:ignore WordPress.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
	}

	/*
	 * Remove the search widget from Jetpack widget include list
	 */
	public function filter__jetpack_widgets_to_include( $widgets ) {
		if ( ! is_array( $widgets ) ) {
			return $widgets;
		}

		foreach ( $widgets as $index => $file ) {
			// If the Search widget is included and it's active on a site, it will automatically re-enable the Search module,
			// even though we filtered it to off earlier, so we need to prevent it from loading
			if ( wp_endswith( $file, '/jetpack/modules/widgets/search.php' ) ) {
				unset( $widgets[ $index ] );
			}
		}

		// Flatten the array back down now that may have removed values from the middle (to keep indexes correct)
		return array_values( $widgets );
	}

	/*
	 * Filter for formatted_args in post queries
	 */ // phpcs:ignore WordPress.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
	public function filter__ep_post_formatted_args( $formatted_args, $query_vars, $query ) {
		// Check if track_total_hits is set
		// Don't override it if it is
		if ( ! array_key_exists( 'track_total_hits', $formatted_args ) ) {
			$formatted_args['track_total_hits'] = true;
		}

		return $formatted_args;
	}

	/**
	 * Set the number of shards in the index settings
	 * 
	 * NOTE - this can only be changed during index creation, not on an existing index
	 */
	public function filter__ep_default_index_number_of_shards( $shards ) {
		$shards = 1;

		$posts_count = wp_count_posts();

		if ( $posts_count->publish > 1000000 ) {
			$shards = 4;
		}

		return $shards;
	}

	/**
	 * Set the number of replicas for the index
	 */
	public function filter__ep_default_index_number_of_replicas( $replicas ) {
		return 2;
	}

	/**
	 * Given an ES url, determine the "mode" of the request for stats purposes
	 * 
	 * Possible modes (matching wp.com) are manage|analyze|status|langdetect|index|delete_query|get|scroll|search
	 */
	public function get_statsd_request_mode_for_request( $url, $args ) {
		$parsed = parse_url( $url );

		$path = explode( '/', $parsed['path'] );
		$method = strtolower( $args['method'] ) ?? 'post';

		// NOTE - Not doing a switch() b/c the meaningful part of URI is not always in same spot

		if ( '_search' === end( $path ) ) {
			return 'search';
		}

		// Individual documents
		if ( '_doc' === $path[ count( $path ) - 2 ] ) {
			if ( 'delete' === $method ) {
				return 'delete';
			}

			if ( 'get' === $method ) {
				return 'get';
			}

			if ( 'put' === $method ) {
				return 'index';
			}
		}

		// Multi-get
		if ( '_mget' === end( $path ) ) {
			return 'get';
		}

		// Creating new docs
		if ( '_create' === $path[ count( $path ) - 2 ] ) {
			if ( 'put' === $method || 'post' === $method ) {
				return 'index';
			}
		}

		if ( '_doc' === end( $path ) && 'post' === $method ) {
			return 'index';
		}

		// Updating existing doc (supports partial update)
		if ( '_update' === $path[ count( $path ) - 2 ] ) {
			return 'index';
		}

		// Bulk indexing
		if ( '_bulk' === end( $path ) ) {
			return 'index';
		}

		// Unknown
		return 'other';
	}

	/**
	 * Given an ES url, determine the index name of the request for stats purposes
	 */
	public function get_statsd_index_name_for_url( $url ) {
		$parsed = parse_url( $url );

		$path = explode( '/', trim( $parsed['path'], '/' ) );

		// Index name is _usually_ the first part of the path
		$index_name = $path[0];

		// If it starts with underscore but isn't "_all", then we didn't detect the index name
		// and should return null
		if ( wp_startswith( $index_name, '_' ) && '_all' !== $index_name ) {
			return null;
		}

		return $index_name;
	}

	/**
	 * Get the statsd stat prefix for a given "mode"
	 */
	public function get_statsd_prefix( $url, $mode = 'other', $app_id = null, $index_name = null ) {
		$key_parts = array(
			'com.wordpress', // Global prefix
			'elasticsearch', // Service name
		);

		$host = parse_url( $url, \PHP_URL_HOST );
		$port = parse_url( $url, \PHP_URL_PORT );

		// Assume all host names are in the format es-ha-$dc.vipv2.net
		$matches = array();
		if ( preg_match( '/^es-ha-(.*)\.vipv2\.net$/', $host, $matches ) ) {
			$key_parts[] = $matches[1]; // DC of ES node
			$key_parts[] = 'ha' . $port . '_vipgo'; // HA endpoint e.g. ha9235_vipgo
		} else {
			$key_parts[] = 'unknown';
			$key_parts[] = 'unknown';
		}

		// Break up tracking based on mode
		$key_parts[] = $mode;

		// If app id / index name passed, include those too
		if ( is_int( $app_id ) ) {
			$key_parts[] = $app_id;
		}

		if ( is_string( $index_name ) && ! empty( $index_name ) ) {
			$key_parts[] = $index_name;
		}

		// returns prefix only e.g. 'com.wordpress.elasticsearch.bur.9235_vipgo.search'
		return implode( '.', $key_parts );
	}

	/*
	 * Filter for formatted_args in queries
	 */
	public function filter__ep_formatted_args( $formatted_args, $args ) {
		// Check for expected structure, ie: this filters first
		if ( ! isset( $formatted_args['query']['bool']['should'][0]['multi_match'] ) ) {
			return $formatted_args;
		}

		if ( defined( 'VIP_GO_APP_ID' ) ) {
			$allow_exact_search_site_ids = array(
				1284,
			);

			// Only allow exact search for whitelisted site ids
			if ( ! in_array( VIP_GO_APP_ID, $allow_exact_search_site_ids, true ) ) {
				return $formatted_args;
			}
		}

		// Replace base 'should' with 'must' and then remove the 'should' from formatted args
		$formatted_args['query']['bool']['must'] = $formatted_args['query']['bool']['should'];
		$formatted_args['query']['bool']['must'][0]['multi_match']['operator'] = 'AND';
		unset( $formatted_args['query']['bool']['should'] );

		return $formatted_args;
	}

	public function truncate_search_string_length( $query_object ) {
		if ( $query_object->is_search() ) {
			$search = $query_object->query['s'];

			$truncated_search = substr( $search, 0, self::MAX_SEARCH_LENGTH );
			
			$query_object->set( 's', $truncated_search );
		}
	}

	/*
	 * Filter for setting decay function for date relevancy in ElasticPress
	 */
	public function filter__epwr_decay_function( $decay_function, $formatted_args, $args ) {
		return 'gauss';
	}

	/*
	 * Filter for setting scale for date relevancy in ElasticPress
	 */
	public function filter__epwr_scale( $scale, $formatted_args, $args ) {
		return '360d';
	}
	
	/*
	 * Filter for setting decay for date relevancy in ElasticPress
	 */
	public function filter__epwr_decay( $decay, $formatted_args, $args ) {
		return .9;
	}

	/*
	 * Filter for setting offset for date relevancy in ElasticPress
	 */
	public function filter__epwr_offset( $offset, $formatted_args, $args ) {
		return '0d';
	}

	/*
	 * Filter for setting score mode for date relevancy in ElasticPress
	 */
	public function filter__epwr_score_mode( $score_mode, $formatted_args, $args ) {
		return 'multiply';
	}

	/*
	 * Filter for setting boost mode for date relevancy in ElasticPress
	 */
	public function filter__epwr_boost_mode( $boost_mode, $formatted_args, $args ) {
		return 'multiply';
	}

	/**
	 * Get current Elasticsearch host
	 *
	 * @return {string|WP_Error} Returns the host on success or a WP_Error on failure
	 */
	public function get_current_host() {
		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) {
			if ( defined( 'EP_HOST' ) ) {
				return EP_HOST;
			}

			return new \WP_Error( 'vip-search-no-host-found', 'No Elasticsearch hosts found' );
		}

		if ( ! is_array( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			return VIP_ELASTICSEARCH_ENDPOINTS;
		}

		if ( ! is_int( $this->current_host_index ) ) {
			$this->current_host_index = 0;
		}

		$this->current_host_index = $this->current_host_index % count( VIP_ELASTICSEARCH_ENDPOINTS );

		return VIP_ELASTICSEARCH_ENDPOINTS[ $this->current_host_index ];
	}

	/*
	 * Increment the number of queries that have been passed through VIP Search
	 */
	private static function query_count_incr() {
		if ( false === wp_cache_get( self::QUERY_COUNT_CACHE_KEY, self::QUERY_COUNT_CACHE_GROUP ) ) {
			wp_cache_set( self::QUERY_COUNT_CACHE_KEY, 0, self::QUERY_COUNT_CACHE_GROUP, self::QUERY_COUNT_TTL );
		}

		return wp_cache_incr( self::QUERY_COUNT_CACHE_KEY, 1, self::QUERY_COUNT_CACHE_GROUP );
	}
}
