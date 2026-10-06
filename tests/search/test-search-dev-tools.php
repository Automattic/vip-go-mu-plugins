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
		Constant_Mocker::clear();
		require_once __DIR__ . '/../../search/search-dev-tools/search-dev-tools.php';
		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		// Don't leak mocked constants into other test classes (tests run in random order on CI).
		Constant_Mocker::clear();
		parent::tearDown();
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

		// Per-site settings are flagged so the UI can say they only describe the current site.
		$site_scoped = array_column( array_filter( $information, fn ( $item ) => 'site' === ( $item['scope'] ?? null ) ), 'key' );
		$this->assertSame( [ 'post_types', 'post_statuses', 'meta_allow_list' ], $site_scoped );
	}

	public function test__is_cross_site_query_needs_network_mode() {
		// Without EP_IS_NETWORK, ElasticPress forces the current site whatever `sites` says.
		$this->assertFalse( \Automattic\VIP\Search\Dev_Tools\is_cross_site_query( [ 'sites' => 'all' ] ) );
		$this->assertFalse( \Automattic\VIP\Search\Dev_Tools\is_cross_site_query( [ 'sites' => [ 2, 3 ] ] ) );
		$this->assertFalse( \Automattic\VIP\Search\Dev_Tools\is_cross_site_query( [] ) );
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
}
