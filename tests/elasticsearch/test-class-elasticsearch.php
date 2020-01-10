<?php

namespace Automattic\VIP\Elasticsearch;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class Elasticsearch_Test extends \WP_UnitTestCase {
	public function setUp() {
		require_once __DIR__ . '/../../elasticsearch/class-elasticsearch.php';
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
}
