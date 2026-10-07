<?php
/**
 * Plugin Name: Search Dev Tools
 * Description: Developer tools for Enterprise Search
 * Version:     1.0.0
 * Author:      WordPress VIP
 * Author URI:  https://wpvip.com
 * License:     GPLv2 or later
 * Text Domain: vip-search
 * Domain Path: /lang/
 *
 * @package Automattic\VIP\Search
 */
// phpcs:disable WordPress.PHP.DisallowShortTernary.Found
namespace Automattic\VIP\Search\Dev_Tools;

use Automattic\VIP\Search\Search;

add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );
add_action( 'admin_bar_menu', __NAMESPACE__ . '\admin_bar_node', PHP_INT_MAX );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets', 11 );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets', 11 );
add_filter( 'js_do_concat', __NAMESPACE__ . '\skip_js_do_concat', 10, 2 );
add_action( 'wp_footer', __NAMESPACE__ . '\print_data', 5 ); // Must be below 20 (`wp_print_footer_scripts`)
add_action( 'admin_footer', __NAMESPACE__ . '\print_data', 5 );
add_action( 'ep_add_query_log', __NAMESPACE__ . '\record_cross_site' );

/**
 * Register Dev Tools Endpoint.
 *
 * @return void
 */
function register_rest_routes() {
	register_rest_route(
		'vip/v1',
		'search/dev-tools',
		[
			'methods'             => [
				'POST',
			],
			'callback'            => __NAMESPACE__ . '\rest_callback',
			'permission_callback' => __NAMESPACE__ . '\should_enable_search_dev_tools',
			'args'                => [
				'url'   => [
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => __NAMESPACE__ . '\rest_endpoint_url_validate_callback',
				],
				'query' => [
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => function ( $value, $request, $param ) {
						json_decode( $value );
						return JSON_ERROR_NONE === json_last_error() ?: new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid JSON', $param ) );
					},
				],
			],
		]
	);
}

/**
 * REST API wrapper to query the ES
 *
 * @param \WP_REST_Request $request
 * @return void
 */
function rest_callback( \WP_REST_Request $request ) {

	$ep     = \ElasticPress\Elasticsearch::factory();
	$result = $ep->remote_request(
		trim( wp_parse_url( $request['url'], PHP_URL_PATH ), '/' ),
		[
			'body'   => $request['query'],
			'method' => 'POST',
		],
		[],
		'query'
	);

	if ( ! is_wp_error( $result ) ) {
		$body    = json_decode( wp_remote_retrieve_body( $result ) );
		$code    = wp_remote_retrieve_response_code( $result );
		$message = wp_remote_retrieve_response_message( $result );
		$result  = [
			'body'     => rest_response_body( $body, $code ),
			// Like the page's query log: the status marks a failure even when the body is JSON without an
			// `error` field (e.g. a gateway's `{"message":"Bad Gateway"}`).
			'response' => [
				'code'    => $code,
				'message' => $message,
			],
		];
	} else {
		$result = [
			'body' => $result->get_error_messages(),
		];
	}

	return rest_ensure_response( [ 'result' => $result ] );
}


/**
 * A capability-based check for whether Dev Tools should be enabled or not.
 * Also check for the existence of ep_get_query_log because the plugin won't work without it.
 *
 * @return boolean
 */
function should_enable_search_dev_tools(): bool {
	$cap = apply_filters( 'vip_search_dev_tools_cap', 'manage_options' );
	return ( current_user_can( $cap ) || ( function_exists( 'is_debug_mode_enabled' ) && is_debug_mode_enabled() ) ) && function_exists( 'ep_get_query_log' );
}

/**
 * Validate the request URL - we should only allow search URLs.
 *
 * @param mixed $value
 * @param WP_Rest_Request $request
 * @param string $param key
 * @return mixed true if valid, WP_Error if not.
 */
function rest_endpoint_url_validate_callback( $value, $request, $param ) {
	$error = new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', $param ) );

	// Not a valid URL.
	if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
		return $error;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	$path = trim( parse_url( $value, PHP_URL_PATH ), '/' );

	// Not an allowed endpoint
	if ( ! str_ends_with( $path, '_search' ) ) {
		return $error;
	}

	$index_part   = strtok( $path, '/' );
	$index_prefix = 'vip-' . FILES_CLIENT_SITE_ID . '-';

	// Check for the allowed index names.
	foreach ( explode( ',', $index_part ) as $idx ) {
		if ( ! str_starts_with( $idx, $index_prefix ) ) {
			return $error;
		}
	}

	return true;
}

/**
 * Add our scripts and styles.
 *
 * @return void
 */
function enqueue_assets() {
	if ( ! should_enable_search_dev_tools() ) {
		return;
	}

	$assets_dir = __DIR__ . '/build';
	$assets_url = plugin_dir_url( __FILE__ ) . 'build';

	wp_enqueue_script( 'vip-search-dev-tools', $assets_url . '/bundle.js', [], filemtime( $assets_dir . '/bundle.js' ), true );
	wp_enqueue_style( 'vip-search-dev-tools', $assets_url . '/bundle.css', [], filemtime( $assets_dir . '/bundle.css' ) );
}

/**
 * Print all the necessary data as a global that's used to populate the SearchContext.
 * Also print the portal mount point.
 *
 * @return void
 */
function print_data() {
	if ( ! should_enable_search_dev_tools() ) {
		return;
	}

	$queries = array_values(
		array_filter(
			ep_get_query_log(),
			function ( $query ) {
				return false !== stripos( $query['url'], '_search' );
			}
		)
	);

	$mapped_queries = array_map(
		function ( $query ) {
			// The happy path: we sanitize query response, and if a body is empty we populate an empty object
			if ( is_array( $query['request'] ) ) {
				$query['request']['body'] = sanitize_query_response( json_decode( $query['request']['body'] ) ?? (object) [] );
				// Network error.
			} elseif ( is_wp_error( $query['request'] ) ) {
				$query['request'] = [
					'body'     => [
						'took'  => intval( ( $query['time_finish'] - $query['time_start'] ) * 1000 ),
						'error' => $query['request'],
					],
					'response' => [
						'code'    => 'timeout',
						'message' => 'Request failure',
					],
				];
				// Handle any other weirdness by including catch all.
			} else {
				$query['request'] = [
					'body'     => [
						'took'  => intval( ( $query['time_finish'] - $query['time_start'] ) * 1000 ),
						'error' => 'Unknown error, please contact VIP for further investigation',
					],
					'response' => [
						'code'    => 'unknown',
						'message' => 'Request failure',
					],
				];
			}

			// Whether the search left its site, as noted while it ran (judged now only for anything logged before
			// Dev Tools was listening).
			$index_part          = (string) Search::instance()->get_index_name_for_url( $query['url'] );
			$query['cross_site'] = cross_site_decisions()[ query_log_key( $query ) ] ?? is_cross_site_request( $index_part );

			$query['args']['body'] = json_decode( $query['args']['body'], true );
			$query['args']['body'] = array_merge( [ 'profile' => false ], $query['args']['body'] );
			// We only want to show booleans (either true or false) or other values that would cast to boolean true (non-empty strings, arrays and non-0 ints),
			// Because the full list of core query arguments is > 60 elements long and it doesn't look good on the frontend.
			$query['query_args'] = array_filter(
				$query['query_args'],
				function ( $v ) {
					return is_bool( $v ) || ( ! is_bool( $v ) && $v );
				}
			);
			// Network alias queries: the indexes the alias reached, or a flag when they couldn't be looked up.
			return array_merge( $query, get_alias_details( $index_part ) );
		},
		$queries
	);

	$data = [
		'status'      => 'enabled',
		'queries'     => $mapped_queries,
		'information' => get_information(),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'ajaxurl'     => rest_url( 'vip/v1/search/dev-tools' ),
	];

	// Compact, not pretty-printed: this holds every query's full request and response on each page view, and
	// indentation made it ~3x larger (189 KB vs 60 KB on a search page). Slashes stay unescaped for size;
	// JSON_HEX_TAG encodes `<` and `>` instead, so content like `</script>` in a post title can't close the tag.
	wp_print_inline_script_tag( sprintf( 'var VIPSearchDevTools = %s;', wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) ) );
	?>
<div id="search-dev-tools-portal"></div>
	<?php
}

/**
 * General Search information shown in the Dev Tools info strip.
 * Each item has a stable `key` the frontend can rely on, a display `label` and a `value`.
 *
 * @param Search|null $search_instance Search instance; defaults to the global one (injectable for tests).
 * @return array[] Information items.
 */
function get_information( ?Search $search_instance = null ): array {
	$search_instance = $search_instance ?? Search::instance();
	$is_rate_limited = Search::is_rate_limited() || $search_instance->queue->is_indexing_ratelimited();
	if ( $is_rate_limited ) {
		$rate_limit   = [ 'search: ' . ( Search::is_rate_limited() ? sprintf( 'yes (%d of %d)', Search::get_query_count(), Search::$max_query_count ) : 'no' ) ];
		$rate_limit[] = 'indexing: ' . ( $search_instance->queue->is_indexing_ratelimited() ? 'yes' : 'no' );
	} else {
		$rate_limit = 'no';
	}

	$concurrent_requests = 0;
	if ( is_callable( [ $search_instance->concurrency_limiter, 'get_backend' ] ) ) {
		$concurrent_requests = $search_instance->concurrency_limiter->get_backend()->get_value();
	}

	return [
		[
			'key'     => 'es_version',
			'label'   => 'Elasticsearch',
			'value'   => get_current_elasticsearch_version(),
			'options' => [
				'collapsible' => false,
			],
		],
		[
			'key'     => 'rate_limited',
			'label'   => 'Rate limited',
			'value'   => $rate_limit,
			'options' => [
				'collapsible' => false,
			],
		],
		[
			'key'     => 'concurrent_requests',
			'label'   => 'Concurrent',
			'value'   => $concurrent_requests,
			'options' => [
				'collapsible' => false,
			],
		],
		[
			'key'     => 'post_types',
			'label'   => 'Post types',
			'value'   => array_values( \ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_types() ),
			'options' => [
				'collapsible' => true,
			],
		],
		[
			'key'     => 'post_statuses',
			'label'   => 'Statuses',
			'value'   => array_values( \ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_status() ),
			'options' => [
				'collapsible' => true,
			],
		],
		[
			'key'     => 'meta_allow_list',
			'label'   => 'Meta',
			'value'   => get_meta_for_all_indexable_post_types(),
			'options' => [
				'collapsible' => true,
			],
		],
	];
}

/**
 * Whether a search request left the current site: it named an index that isn't one of this site's own (the
 * network alias for `'sites' => 'all'`, or other sites' indexes). This reads the index ElasticPress actually
 * chose, so its scope rules (`sites`, `ep_search_scope`, network mode) aren't repeated here.
 *
 * @param string $index_part Index part of the request URL (see Search::get_index_name_for_url()).
 * @return bool Cross-site request.
 */
function is_cross_site_request( string $index_part ): bool {
	if ( '' === $index_part || ! is_multisite() ) {
		return false;
	}
	$own = array_map( fn ( $indexable ) => $indexable->get_index_name(), \ElasticPress\Indexables::factory()->get_all() );
	return (bool) array_diff( explode( ',', $index_part ), $own );
}

/**
 * Key tying a query log entry to the decision record_cross_site() noted for it.
 *
 * @param array $query Query log entry.
 * @return string Key.
 */
function query_log_key( array $query ): string {
	return ( $query['url'] ?? '' ) . '|' . ( $query['time_start'] ?? '' );
}

/**
 * Cross-site decisions noted while requests ran, keyed by query_log_key().
 *
 * @param array|null $query    Query log entry to note a decision for, or null to only read.
 * @param bool       $decision Decision for `$query`.
 * @return bool[] Decisions so far.
 */
function cross_site_decisions( ?array $query = null, bool $decision = false ): array {
	static $decisions = [];
	if ( null !== $query ) {
		$decisions[ query_log_key( $query ) ] = $decision;
	}
	return $decisions;
}

/**
 * On `ep_add_query_log`: note whether a search left its site while that site is still current. print_data()
 * runs in the footer, where a query made inside switch_to_blog() would be judged against the wrong site.
 *
 * @param array $query Query log entry.
 * @return void
 */
function record_cross_site( $query ): void {
	// Same condition as ElasticPress's query log: without the log there's nothing to show.
	$logging = ( defined( 'WP_DEBUG' ) && constant( 'WP_DEBUG' ) ) || ( defined( 'WP_EP_DEBUG' ) && constant( 'WP_EP_DEBUG' ) );
	if ( ! $logging || ! is_multisite() || ! is_array( $query ) || false === stripos( (string) ( $query['url'] ?? '' ), '_search' ) ) {
		return;
	}
	cross_site_decisions( $query, is_cross_site_request( (string) Search::instance()->get_index_name_for_url( $query['url'] ) ) );
}

/**
 * Whether an index part names a network alias (`vip-123-post-all`), which `'sites' => 'all'` queries search.
 *
 * @param string $index_part Index part of a request URL.
 * @return bool Network alias.
 */
function is_network_alias( string $index_part ): bool {
	return '' !== $index_part && ! str_contains( $index_part, ',' ) && str_ends_with( $index_part, '-all' );
}

/**
 * What to report about a query's network alias, so the UI never presents the alias itself as an index.
 *
 * @param string $index_part Index part of a request URL.
 * @return array `alias_indexes` with the indexes behind the alias, `alias_unresolved` when they couldn't be looked
 *               up, or nothing when the query didn't use a network alias.
 */
function get_alias_details( string $index_part ): array {
	if ( ! is_network_alias( $index_part ) ) {
		return [];
	}
	$indexes = get_alias_indexes( $index_part );
	return $indexes ? [ 'alias_indexes' => $indexes ] : [ 'alias_unresolved' => true ];
}

/**
 * Concrete indexes behind a network alias index (`vip-123-post-all`, used for `'sites' => 'all'`).
 * Only alias names trigger a lookup, once per request, and it is capped at VIP's global ES timeout (2s on web).
 * Not cached across requests on purpose: a dev tool should show the alias as it is right now, e.g. right after
 * `wp vip-search recreate-network-alias`.
 *
 * @param string $index_part Index part of a request URL.
 * @return string[] Sorted index names, or [] when this is not a network alias or the lookup fails.
 */
function get_alias_indexes( string $index_part ): array {
	static $cache = [];

	if ( ! is_network_alias( $index_part ) ) {
		return [];
	}

	if ( isset( $cache[ $index_part ] ) ) {
		return $cache[ $index_part ];
	}

	$indexes  = [];
	$response = \ElasticPress\Elasticsearch::factory()->remote_request( $index_part . '/_alias', [ 'method' => 'GET' ], [], 'get' );
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) ) {
			$indexes = array_map( 'strval', array_keys( $body ) );
			sort( $indexes );
		}
	}

	$cache[ $index_part ] = $indexes;
	return $indexes;
}

/**
 * Register Admin Bar node/App mount point
 *
 * @param \WP_Admin_Bar $admin_bar
 * @return void
 */
function admin_bar_node( \WP_Admin_Bar $admin_bar ) {
	if ( ! should_enable_search_dev_tools() ) {
		return;
	}

	$admin_bar->add_menu(
		[
			'id'     => 'vip-search-dev-tools',
			'parent' => null,
			'group'  => null,
			'title'  => '',
			'href'   => '#',
			'meta'   => [
				'title' => 'Open VIP Search Dev Tools',
				'class' => 'vip-search-dev-tools-ab',
				'html'  => '<div id="vip-search-dev-tools-mount" data-widget-host="vip-search-dev-tools"></div>',
			],
		]
	);
}

/**
 * Skip nginx-http-concat and load as a separate file
 *
 * @param boolean $do_concat whether to concat current file.
 * @param string $handle registered script handle.
 * @return boolean
 */
function skip_js_do_concat( bool $do_concat, string $handle ): bool {
	if ( 'vip-search-dev-tools' === $handle ) {
		$do_concat = false;
	}
	return $do_concat;
}

/**
 * Run response body for the frontend. A proxy or gateway error page may not be JSON at all; report that
 * instead of failing on the decode, and pass any other JSON (a string or array message) through as is.
 *
 * @param mixed      $body Decoded Elasticsearch response body (null when it wasn't JSON).
 * @param int|string $code HTTP status.
 * @return mixed Body.
 */
function rest_response_body( $body, $code ) {
	if ( is_object( $body ) ) {
		return sanitize_query_response( $body );
	}
	if ( null === $body ) {
		return [ 'error' => sprintf( 'Elasticsearch returned a non-JSON response (HTTP %s).', $code ) ];
	}
	return $body;
}

/**
 * Prepare the query response body for the front-end:
 * remove the sensitive or not needed data
 *
 * @param object $response_body decoded JSON payload containing query result response.
 * @return object
 */
function sanitize_query_response( object $response_body ): object {
	if ( ! isset( $response_body->hits->hits ) ) {
		return $response_body;
	}

	foreach ( $response_body->hits->hits as &$hit ) {
		// Post content tends to be large, breaking the layout and decreasing usability.
		// TODO: There may be rare cases where it's needed though. Add conditional toggle for that.
		if ( isset( $hit->_source->post_content ) ) {
			$hit->_source->post_content = '#CONTENT TRUNCATED#';
		}
	}

	return $response_body;
}

/**
 * Get current Elasticsearch version information including ES7/ES8 migration context
 *
 * @return string ES version information with migration context
 */
function get_current_elasticsearch_version() {
	if ( ! method_exists( '\ElasticPress\Elasticsearch', 'get_elasticsearch_version' ) ) {
		return 'Version detection unavailable';
	}

	// Get the base ES version
	$base_version = \ElasticPress\Elasticsearch::factory()->get_elasticsearch_version() ?: 'Unknown';

	if ( defined( 'VIP_ELASTICSEARCH_MIGRATION_IN_PROGRESS' ) && constant( 'VIP_ELASTICSEARCH_MIGRATION_IN_PROGRESS' ) ) {
		$constant_value          = defined( 'VIP_ELASTICSEARCH_VERSION' ) ? constant( 'VIP_ELASTICSEARCH_VERSION' ) : 'Unknown';
		$is_testing_next_version = \Automattic\VIP\Search\Search::instance()->is_testing_next_version();
		return sprintf( '%s (Migration: %s)', $base_version, '8' === $constant_value || $is_testing_next_version ? 'Using ES8' : 'Using ES7' );
	}

	return $base_version;
}

/**
 * Safer way to get the correct meta keys for all post types.
 * This way of calling the filter should avoid potential TypeError fatals,
 * in case one of the filter type hints the $post to be a WP_Post instance.
 *
 * @return array meta keys in the allow list
 */
function get_meta_for_all_indexable_post_types(): array {
	$ret        = [];
	$post_types = \ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_types();

	foreach ( $post_types as $post_type ) {
		$fake_post = new \WP_Post( (object) [ 'post_type' => $post_type ] );
		$ret[]     = Search::instance()->get_post_meta_allow_list( $fake_post );
	}

	// Flatten and return unique values.
	return array_values( array_unique( array_merge( [], ...$ret ) ) );
}
