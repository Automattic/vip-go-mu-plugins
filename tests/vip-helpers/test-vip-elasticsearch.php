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
	 * Test `ep_index_name` filter for ElasticPress + VIP Elasticsearch for global indexes
	 * 
	 * On "global" indexes, such as users, no blog id will be present
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_filter_ep_index_name_global_index() {
		$mock_indexable = (object) [ 'slug' => 'users' ];

		define( 'USE_VIP_ELASTICSEARCH', true );

		// Hack to get around the constant not being defined early enough...there is probably a proper PHPUnit way to do that
		add_filter( 'ep_index_name', 'vip_elasticsearch_filter_ep_index_name', PHP_INT_MAX, 3 );

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $mock_indexable );

		$this->assertEquals( 'vip-123-users', $index_name );
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
}
