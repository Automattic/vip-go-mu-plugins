<?php

namespace Automattic\VIP\Search;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

require_once __DIR__ . '/../../search/search.php';
require_once __DIR__ . '/../../search/includes/classes/class-versioning.php';
require_once __DIR__ . '/../../search/elasticpress/elasticpress.php';

class Search_Dev_Tools_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/../../search/search-dev-tools/search-dev-tools.php';
	}

	/**
	 * A fresh Search instance (not the global singleton, which would outlive this test).
	 */
	private function init_search(): Search {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'https://elasticsearch:9200' ] );
		$search = new Search();
		$search->init();
		// Required so that EP registers the Indexables.
		do_action( 'plugins_loaded' );
		return $search;
	}

	public function data_provider_endpoint_urls() {
		return [
			[
				'input'    => 'http://vip-search:9200/vip-123-post-1/_search',
				'expected' => true,
			],
			[
				'input'    => 'http://vip-search:9200/vip-123-post-1,vip-123-post-post-2-v1/_search',
				'expected' => true,
			],
			[
				'input'    => 'http://vip-search:9200/vip-123-post-v1,vip-3456-post-2-v1/_search',
				'expected' => new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', 'url' ) ),
			],
			[
				'input'    => 'http://vip-search:9200/vip-2345-post-v1/_search',
				'expected' => new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', 'url' ) ),
			],
			[
				'input'    => 'http://vip-search:9200/restricted/_endpoint',
				'expected' => new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', 'url' ) ),
			],
			[
				'input'    => 'notavalidurl',
				'expected' => new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', 'url' ) ),
			],
		];
	}

	public function test__get_information_exposes_stable_keys() {
		$search = $this->init_search();
		// Keep the ES version lookup offline.
		add_filter( 'pre_http_request', fn () => new \WP_Error( 'http_blocked', 'Blocked in tests' ) );

		$information = \Automattic\VIP\Search\Dev_Tools\get_information( $search );

		$this->assertSame(
			[ 'es_version', 'rate_limited', 'concurrent_requests', 'post_types', 'post_statuses', 'meta_allow_list' ],
			array_column( $information, 'key' ),
			'Info items must keep their keys and order; the frontend relies on them'
		);

		foreach ( $information as $item ) {
			$this->assertArrayHasKey( 'label', $item );
			$this->assertArrayHasKey( 'value', $item );
			$this->assertIsBool( $item['options']['collapsible'] );
		}
	}

	public function test__is_cross_site_request() {
		$this->init_search();
		$own = \ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();

		$this->assertFalse( \Automattic\VIP\Search\Dev_Tools\is_cross_site_request( $own ) );
		$this->assertFalse( \Automattic\VIP\Search\Dev_Tools\is_cross_site_request( '' ) );
		if ( ! is_multisite() ) {
			// A single site has nowhere else to search.
			$this->assertFalse( \Automattic\VIP\Search\Dev_Tools\is_cross_site_request( 'vip-123-post-all' ) );
			return;
		}

		$other = \ElasticPress\Indexables::factory()->get( 'post' )->get_index_name( self::factory()->blog->create() );
		$this->assertTrue( \Automattic\VIP\Search\Dev_Tools\is_cross_site_request( $other ) );
		$this->assertTrue( \Automattic\VIP\Search\Dev_Tools\is_cross_site_request( "{$own},{$other}" ) );
		$this->assertTrue( \Automattic\VIP\Search\Dev_Tools\is_cross_site_request( \ElasticPress\Indexables::factory()->get( 'post' )->get_network_alias() ) );
	}

	public function test__record_cross_site_judges_the_site_the_query_ran_on() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Needs multisite.' );
		}
		$this->init_search();
		$blog_id = self::factory()->blog->create();

		// A query made inside switch_to_blog() against that site's own index stays on its site...
		switch_to_blog( $blog_id );
		$index = \ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();
		$query = [
			'url'        => "https://elasticsearch:9200/{$index}/_search",
			'time_start' => 1.5,
		];
		\Automattic\VIP\Search\Dev_Tools\record_cross_site( $query );
		restore_current_blog();

		$this->assertFalse( \Automattic\VIP\Search\Dev_Tools\cross_site_decisions()[ \Automattic\VIP\Search\Dev_Tools\query_log_key( $query ) ] );
		// ...although judged from the current site in the footer, it would look cross-site.
		$this->assertTrue( \Automattic\VIP\Search\Dev_Tools\is_cross_site_request( $index ) );
	}

	public function test__rest_callback_reports_a_non_json_response() {
		$data = $this->run_rest_callback_with_response( 502, 'Bad Gateway', '<html><body>Bad Gateway</body></html>' );

		$this->assertSame( [ 'error' => 'Elasticsearch returned a non-JSON response (HTTP 502).' ], $data['result']['body'] );
		$this->assertSame( 502, $data['result']['response']['code'] );
	}

	public function test__rest_callback_keeps_the_status_of_a_json_error_response() {
		// A gateway error can be JSON without an `error` field; the status is what marks it as failed.
		$data = $this->run_rest_callback_with_response( 502, 'Bad Gateway', '{"message":"Bad Gateway"}' );

		$this->assertSame( 'Bad Gateway', $data['result']['body']->message );
		$this->assertSame(
			[
				'code'    => 502,
				'message' => 'Bad Gateway',
			],
			$data['result']['response']
		);
	}

	/**
	 * Run the Dev Tools REST callback against a mocked Elasticsearch response.
	 *
	 * @param int    $code    HTTP status.
	 * @param string $message HTTP status message.
	 * @param string $body    Response body.
	 * @return array REST response data.
	 */
	private function run_rest_callback_with_response( int $code, string $message, string $body ): array {
		$this->init_search();
		add_filter(
			'pre_http_request',
			fn () => [
				'headers'  => [],
				'body'     => $body,
				'response' => [
					'code'    => $code,
					'message' => $message,
				],
				'cookies'  => [],
			]
		);

		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'url', 'https://elasticsearch:9200/vip-123-post-1/_search' );
		$request->set_param( 'query', '{}' );
		return \Automattic\VIP\Search\Dev_Tools\rest_callback( $request )->get_data();
	}

	/**
	 * @dataProvider get_route_permission_cases
	 */
	public function test_registered_route_checks_permission_before_query( ?string $role, bool $allowed ): void {
		$this->init_search();
		// WordPress resets actions between tests, while require_once keeps this module loaded.
		add_action( 'rest_api_init', '\\Automattic\\VIP\\Search\\Dev_Tools\\register_rest_routes' );
		// Earlier tests may have initialized the REST server before this module was loaded.
		do_action( 'rest_api_init' );
		$this->assertTrue( function_exists( 'ep_get_query_log' ), 'The query-log prerequisite must be available.' );

		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Disable debug-mode cookies for this permission test.
		unset( $_COOKIE['vip-go-cb'], $_COOKIE['a8c-debug'] );
		$this->assertFalse( is_debug_mode_enabled(), 'Debug mode would bypass the capability gate.' );
		add_filter( 'vip_search_dev_tools_cap', static fn () => 'manage_options' );
		wp_set_current_user( null === $role ? 0 : self::factory()->user->create( [ 'role' => $role ] ) );

		$requests = [];
		add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) use ( &$requests ) {
			$requests[] = $url;
			return [
				'headers'  => [],
				'body'     => '{}',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		}, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/vip/v1/search/dev-tools' );
		$request->set_param( 'url', 'https://elasticsearch:9200/vip-' . FILES_CLIENT_SITE_ID . '-post-1/_search' );
		$request->set_param( 'query', '{}' );
		$response = rest_do_request( $request );

		if ( $allowed ) {
			$this->assertSame( 200, $response->get_status() );
			$this->assertNotEmpty( $requests, 'An authorized request must reach Elasticsearch.' );
			$this->assertStringContainsString( '_search', end( $requests ) );
		} else {
			$this->assertSame( null === $role ? 401 : 403, $response->get_status() );
			$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
			$this->assertSame( [], $requests, 'Denied requests must not reach Elasticsearch.' );
		}
	}

	public function get_route_permission_cases(): array {
		return [
			'anonymous'  => [ null, false ],
			'subscriber' => [ 'subscriber', false ],
			'admin'      => [ 'administrator', true ],
		];
	}

	public function test__get_alias_indexes_resolves_network_alias_once() {
		$this->init_search();

		$requests = [];
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$requests ) {
				$requests[] = $url;
				return [
					'headers'  => [],
					'body'     => wp_json_encode(
						[
							'vip-123-post-3-v2' => [ 'aliases' => [ 'vip-123-post-all' => [] ] ],
							'vip-123-post-1'    => [ 'aliases' => [ 'vip-123-post-all' => [] ] ],
						]
					),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
				];
			},
			10,
			3
		);

		$indexes = \Automattic\VIP\Search\Dev_Tools\get_alias_indexes( 'vip-123-post-all' );
		$again   = \Automattic\VIP\Search\Dev_Tools\get_alias_indexes( 'vip-123-post-all' );

		$this->assertSame( [ 'vip-123-post-1', 'vip-123-post-3-v2' ], $indexes, 'Alias members should be sorted index names' );
		$this->assertSame( $indexes, $again );
		$this->assertCount( 1, $requests, 'The alias should be looked up once per request' );
		$this->assertStringContainsString( 'vip-123-post-all/_alias', $requests[0] );
	}

	public function test__get_alias_details_flags_failed_lookups() {
		$this->init_search();
		add_filter( 'pre_http_request', fn () => new \WP_Error( 'http_request_failed', 'Timed out' ) );

		// A unique alias name: get_alias_indexes() caches per request, and other tests resolve their own alias.
		$this->assertSame( [ 'alias_unresolved' => true ], \Automattic\VIP\Search\Dev_Tools\get_alias_details( 'vip-456-post-all' ) );
		$this->assertSame( [], \Automattic\VIP\Search\Dev_Tools\get_alias_details( 'vip-456-post-1' ) );
	}

	public function test__get_alias_indexes_skips_regular_indexes() {
		$requests = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$requests ) {
				++$requests;
				return new \WP_Error( 'http_blocked', 'Blocked in tests' );
			}
		);

		$this->assertSame( [], \Automattic\VIP\Search\Dev_Tools\get_alias_indexes( 'vip-123-post-1' ) );
		$this->assertSame( [], \Automattic\VIP\Search\Dev_Tools\get_alias_indexes( 'vip-123-post-2,vip-123-post-all' ) );
		$this->assertSame( [], \Automattic\VIP\Search\Dev_Tools\get_alias_indexes( '' ) );
		$this->assertSame( 0, $requests, 'Only network alias names trigger a lookup' );
	}

	/**
	 * @dataProvider data_provider_endpoint_urls
	 */
	public function test__url_validation( $input, $expected ) {
		$val = \Automattic\VIP\Search\Dev_Tools\rest_endpoint_url_validate_callback( $input, new \WP_REST_Request( 'POST' ), 'url' );
		$this->assertEquals( $val, $expected, 'URL validation failed' );
	}

	public function data_provider_queries() {
		$error = new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid JSON', 'query' ) );

		return [
			'valid JSON string'   => [ '{"query":{"match_all":{}}}', true ],
			'invalid JSON string' => [ '{"query":', $error ],
			'decoded object'      => [ [ 'query' => [ 'match_all' => [] ] ], $error ],
			'number'              => [ 123, $error ],
		];
	}

	/**
	 * @dataProvider data_provider_queries
	 */
	public function test__query_validation( $input, $expected ) {
		$val = \Automattic\VIP\Search\Dev_Tools\rest_endpoint_query_validate_callback( $input, new \WP_REST_Request( 'POST' ), 'query' );
		$this->assertEquals( $expected, $val, 'Query validation failed' );
	}
}
