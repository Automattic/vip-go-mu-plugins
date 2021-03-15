<?php
// phpcs:disable WordPress.PHP.DisallowShortTernary.Found
namespace Automattic\VIP\Search\Dev_Tools;

use Automattic\VIP\Search\Search;

if ( ! ( defined( 'VIP_ENABLE_VIP_SEARCH' ) && VIP_ENABLE_VIP_SEARCH ) ) {
	return;
}

define( 'SEARCH_DEV_TOOLS_CAP', 'edit_others_posts' );

add_action(
	'rest_api_init',
	function() {
		register_rest_route(
			'vip/v1',
			'search/repl',
			[
				'methods'             => [
					'POST',
				],
				'callback'            => __NAMESPACE__ . '\rest_callback',
				'permission_callback' => __NAMESPACE__ . '\should_enable_search_dev_tools',
				// Uncomment this for testing with `npm run dev`
				// 'permission_callback' => '__return_true',
				'args'                => [
					'url'   => [
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function( $value, $request, $param ) {
								return filter_var( $value, FILTER_VALIDATE_URL ) ?: new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid URL', $param ) );
						},
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
);

/**
 * REST API wrapper to query the ES
 *
 * @param \WP_REST_Request $request
 * @return void
 */
function rest_callback( \WP_REST_Request $request ) {

	$ep             = \ElasticPress\Elasticsearch::factory();
	$result         = $ep->remote_request(
		trim( parse_url( $request['url'], PHP_URL_PATH ), '/' ),
		[
			'body'   => $request['query'],
			'method' => 'POST',
		]
	);
	$result['body'] = json_encode( json_decode( $result['body'] ), JSON_PRETTY_PRINT );
	return rest_ensure_response( [ 'result' => $result ] );
}


/**
 * A capability-based check for whether Dev Tools should be enabled or not
 *
 * @return boolean
 */
function should_enable_search_dev_tools(): bool {
	return current_user_can( SEARCH_DEV_TOOLS_CAP );
}

function is_ratelimited(): bool {
	return wp_cache_get( Search::QUERY_COUNT_CACHE_KEY, Search::QUERY_COUNT_CACHE_GROUP ) > Search::$max_query_count;
}

add_action( 'wp_enqueue_scripts', function() {
	if ( ! should_enable_search_dev_tools() ) {
		return;
	}

	// @todo: remove cachebusting arg.
	wp_enqueue_script( 'vip-search-dev-tools', plugin_dir_url( __FILE__ ) . 'build/bundle.js', [], current_time( 'timestamp' ), true );
	wp_enqueue_style( 'vip-search-dev-tools', plugin_dir_url( __FILE__ ) . 'build/bundle.css', [], current_time( 'timestamp' ) );
}, 11 );

add_action(
	'wp_body_open',
	function() {
		if ( ! should_enable_search_dev_tools() ) {
			return;
		}

		$queries = array_filter(
			ep_get_query_log(),
			function( $query ) {
				return false === stripos( $query['url'], '_nodes/plugins' );
			}
		);

		$mapped_queries = array_map(
			function( $query ) {
				$query['request']['body'] = json_decode( $query['request']['body'] );
				$query['args']['body']    = json_decode( $query['args']['body'] );
				return $query;
			},
			$queries
		);

		$data = [
			'status'      => 'enabled',
			'queries'     => $mapped_queries,
			'information' => [
				[
					'label' => 'Currently ratelimited?',
					'value' => is_ratelimited() ? 'yes' : 'no',
					'options' => [
						'collapsible' => false,
					],
				],
				[
					'label' => 'Indexable post types',
					'value' => join( ', ', \ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_types() ),
					'options' => [
						'collapsible' => true,
					],
				],
				[
					'label' => 'Indexable post status',
					'value' => join( ', ', \ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_status() ),
					'options' => [
						'collapsible' => true,
					],
				],
				[
					'label' => 'Meta Key Allow List',
					'value' => join( ', ', Search::instance()->get_post_meta_allow_list( null ) ),
					'options' => [
						'collapsible' => true,
					],
				],

			],
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'ajaxurl'     => rest_url( 'vip/v1/search/repl' ),
			'__webpack_public_path__' => plugin_dir_url( __FILE__ ) . 'build',
		];

		?>
	<script>
		var VIPSearchDevTools = <?php echo wp_json_encode( $data, JSON_PRETTY_PRINT ); ?>;
	</script>
	<div id="search-dev-tools-portal"></div>
		<?php
	},
	5
);


add_action( 'admin_bar_menu', function( \WP_Admin_Bar $admin_bar ) {
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
}, PHP_INT_MAX );
