<?php

class VIP_ElasticSearch_Test extends \WP_UnitTestCase {
	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Elasticsearch
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_filter_ep_index_name() {
		$mock_indexable = (object) [ 'slug' => 'slug' ];

		define( 'USE_VIP_ELASTICSEARCH', true );

		// Hack to get around the constant not being defined early enough...there is probably a proper PHPUnit way to do that
		add_filter( 'ep_index_name', 'vip_elasticsearch_filter_ep_index_name', PHP_INT_MAX, 3 );

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'vip-123-slug-1', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Elasticsearch
	 *
	 * USE_VIP_ELASTICSEARCH not defined
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_filter_ep_index_name__no_constant() {
		$mock_indexable = (object) [ 'slug' => 'slug' ];

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'index-name', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Elasticsearch
	 *
	 * USE_VIP_ELASTICSEARCH is false
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_filter_ep_index_name__constant_is_false() {
		$mock_indexable = (object) [ 'slug' => 'slug' ];

		define( 'USE_VIP_ELASTICSEARCH', false );

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'index-name', $index_name );
	}

	/**
	 * Test that we set a default bulk index chunk size limit
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_bulk_chunk_size_default() {
		define( 'USE_VIP_ELASTICSEARCH', true );

		vip_elasticsearch_setup_constants();

		$this->assertEquals( EP_SYNC_CHUNK_LIMIT, 250 );
	}

	/**
	 * Test that the default bulk index chunk size limit is not applied if constant is already defined
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_bulk_chunk_size_already_defined() {
		define( 'USE_VIP_ELASTICSEARCH', true );

		define( 'EP_SYNC_CHUNK_LIMIT', 500 );

		vip_elasticsearch_setup_constants();

		$this->assertEquals( EP_SYNC_CHUNK_LIMIT, 500 );
	}

	/**
	 * Test that the default bulk index chunk size limit is not defined if we're not using VIP Elasticsearch
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_bulk_chunk_size_not_defined_when_not_using_vip_elasticsearch() {
		define( 'USE_VIP_ELASTICSEARCH', false );

		vip_elasticsearch_setup_constants();

		$this->assertEquals( defined( 'EP_SYNC_CHUNK_LIMIT' ), false );
	}
}
