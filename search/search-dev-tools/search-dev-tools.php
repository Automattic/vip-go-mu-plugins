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

define( 'SEARCH_DEV_TOOLS_CAP', 'manage_options' );

add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );
add_action( 'admin_bar_menu', __NAMESPACE__ . '\admin_bar_node', PHP_INT_MAX );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets', 11 );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets', 11 );
add_filter( 'js_do_concat', __NAMESPACE__ . '\skip_js_do_concat', 10, 2 );
add_action( 'wp_footer', __NAMESPACE__ . '\print_data', 5 );
add_action( 'admin_footer', __NAMESPACE__ . '\print_data', 5 );

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
					'validate_callback' => function( $value, $request, $param ) {
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
		]
	);

	$result['body'] = sanitize_query_response( json_decode( $result['body'] ) );
	return rest_ensure_response( [ 'result' => $result ] );
}


/**
 * A capability-based check for whether Dev Tools should be enabled or not.
 * Also check for the existence of ep_get_query_log because the plugin won't work without it.
 *
 * @return boolean
 */
function should_enable_search_dev_tools(): bool {
	return current_user_can( SEARCH_DEV_TOOLS_CAP ) && function_exists( 'ep_get_query_log' );
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
	if ( ! wp_endswith( $path, '_search' ) ) {
		return $error;
	}

	$index_part = strtok( $path, '/' );

	// Check for the allowed index names.
	foreach ( explode( ',', $index_part ) as $idx ) {
		if ( ! wp_startswith( $idx, 'vip-' . FILES_CLIENT_SITE_ID ) ) {
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
			function( $query ) {
				return false !== stripos( $query['url'], '_search' );
			}
		)
	);

	$mapped_queries = array_map(
		function( $query ) {
			// The happy path: we sanitize query response
			if ( is_array( $query['request'] ) ) {
				$query['request']['body'] = sanitize_query_response( json_decode( $query['request']['body'] ) );
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

			$query['args']['body'] = json_decode( $query['args']['body'] );
			// We only want to show booleans (either true or false) or other values that would cast to boolean true (non-empty strings, arrays and non-0 ints),
			// Because the full list of core query arguments is > 60 elements long and it doesn't look good on the frontend.
			$query['query_args'] = array_filter(
				$query['query_args'],
				function( $v ) {
					return is_bool( $v ) || ( ! is_bool( $v ) && $v );
				}
			);
			return $query;
		},
		$queries
	);

	$limit_count = sprintf(
		'%s (%d of %d limit)',
		Search::is_rate_limited() ? 'yes' : 'no',
		Search::get_query_count(),
		Search::$max_query_count
	);

	$data = [
		'status'                  => 'enabled',
		'queries'                 => $mapped_queries,
		'information'             => [
			[
				'label'   => 'Rate limited?',
				'value'   => $limit_count,
				'options' => [
					'collapsible' => false,
				],
			],
			[
				'label'   => 'Indexable post types',
				'value'   => array_values( \ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_types() ),
				'options' => [
					'collapsible' => true,
				],
			],
			[
				'label'   => 'Indexable post status',
				'value'   => array_values( \ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_status() ),
				'options' => [
					'collapsible' => true,
				],
			],
			[
				'label'   => 'Meta Key Allow List',
				'value'   => get_meta_for_all_indexable_post_types(),
				'options' => [
					'collapsible' => true,
				],
			],

		],
		'nonce'                   => wp_create_nonce( 'wp_rest' ),
		'ajaxurl'                 => rest_url( 'vip/v1/search/dev-tools' ),
		'__webpack_public_path__' => plugin_dir_url( __FILE__ ) . 'build',
	];

	?>
<script>
	var VIPSearchDevTools = <?php echo wp_json_encode( $data, JSON_PRETTY_PRINT ); ?>;
</script>
<div id="search-dev-tools-portal"></div>
	<?php
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
