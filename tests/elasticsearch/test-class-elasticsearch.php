<?php

namespace Automattic\VIP\Elasticsearch;

class Elasticsearch_Test extends \WP_UnitTestCase {
	/**
	 * Make tests run in separate processes since we're testing state
	 * related to plugin init, including various constants.
	 */
	protected $preserveGlobalState = FALSE;
	protected $runTestInSeparateProcess = TRUE;

	public function setUp() {
		require_once __DIR__ . '/../../elasticsearch/elasticsearch.php';
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Elasticsearch
	 */
	public function test__vip_elasticsearch_filter_ep_index_name() {
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$mock_indexable = (object) [ 'slug' => 'slug' ];

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'vip-123-slug-1', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Elasticsearch for global indexes
	 *
	 * On "global" indexes, such as users, no blog id will be present
	 */
	public function test__vip_elasticsearch_filter_ep_index_name_global_index() {
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$mock_indexable = (object) [ 'slug' => 'users' ];

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $mock_indexable );

		$this->assertEquals( 'vip-123-users', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Elasticsearch
	 *
	 * USE_VIP_ELASTICSEARCH not defined (Elasticseach class doesn't load)
	 */
	public function test__vip_elasticsearch_filter_ep_index_name__no_constant() {
		$mock_indexable = (object) [ 'slug' => 'slug' ];

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'index-name', $index_name );
	}

	/**
	 * Test that we set a default bulk index chunk size limit
	 */
	public function test__vip_elasticsearch_bulk_chunk_size_default() {
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$this->assertEquals( EP_SYNC_CHUNK_LIMIT, 250 );
	}

	/**
	 * Test that the default bulk index chunk size limit is not applied if constant is already defined
	 */
	public function test__vip_elasticsearch_bulk_chunk_size_already_defined() {
		define( 'EP_SYNC_CHUNK_LIMIT', 500 );

		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$this->assertEquals( EP_SYNC_CHUNK_LIMIT, 500 );
	}

	/**
	 * Test that the default bulk index chunk size limit is not defined if we're not using VIP Elasticsearch
	 */
	public function test__vip_elasticsearch_bulk_chunk_size_not_defined_when_not_using_vip_elasticsearch() {
		$this->assertEquals( defined( 'EP_SYNC_CHUNK_LIMIT' ), false );
	}

	/**
	 * Test that the ES config constants are set automatically when not already defined and VIP-provided configs are present
	 */
	public function test__vip_elasticsearch_connection_constants() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
			'https://es-endpoint1',
			'https://es-endpoint2',
		) );

		define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$this->assertEquals( EP_HOST, 'https://es-endpoint1' );
		$this->assertEquals( ES_SHIELD, 'foo:bar' );
	}

	/**
	 * Test that the ES config constants are _not_ set automatically when already defined and VIP-provided configs are present
	 * 
	 */
	public function test__vip_elasticsearch_connection_constants_with_overrides() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
			'https://es-endpoint1',
			'https://es-endpoint2',
		) );

		define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		// Client over-rides - don't fatal
		define( 'EP_HOST', 'https://somethingelse' );
		define( 'ES_SHIELD', 'bar:baz' );

		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$this->assertEquals( EP_HOST, 'https://somethingelse' );
		$this->assertEquals( ES_SHIELD, 'bar:baz' );
	}

	/**
	 * Test that we are sending HTTP requests through the VIP helper functions
	 */
	public function test__vip_elasticsearch_has_http_layer_filters() {
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$this->assertEquals( true, has_filter( 'ep_intercept_remote_request', '__return_true' ) );
		$this->assertEquals( true, has_filter( 'ep_do_intercept_request', [ $es, 'filter__ep_do_intercept_request' ] ) );
	}

	/**
	 * Test that we are setting up the filter to auto-disable JP Search
	 */
	public function test__vip_elasticsearch_has_jp_search_module_filter() {
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$this->assertEquals( true, has_filter( 'jetpack_active_modules', [ $es, 'filter__jetpack_active_modules' ] ) );
	}

	public function vip_elasticsearch_filter__jetpack_active_modules() {
		return array(
			// No modules, no change
			array(
				// Input
				array(),

				// Expected
				array(),
			),

			// Search not enabled, no change
			array(
				// Input
				array(
					'foo',
				),

				// Expected
				array(
					'foo',
				),
			),

			// Search enabled, should be removed from list
			array(
				// Input
				array(
					'foo',
					'search',
				),

				// Expected
				array(
					'foo',
				),
			),

			// Search-like module enabled, should not be removed from list
			array(
				// Input
				array(
					'foo',
					'searchbar',
				),

				// Expected
				array(
					'foo',
					'searchbar',
				),
			),

			// Search enabled multiple times, should be removed from list
			array(
				// Input
				array(
					'search',
					'foo',
					'search',
				),

				// Expected
				array(
					'foo',
				),
			),
		);
	}

	/**
	 * Test that our active modules filter works as expected
	 * 
	 * @dataProvider vip_elasticsearch_filter__jetpack_active_modules
	 */
	public function test__vip_elasticsearch_filter__jetpack_active_modules( $input, $expected ) {
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$result = $es->filter__jetpack_active_modules( $input );

		$this->assertEquals( $expected, $result );
	}
}
