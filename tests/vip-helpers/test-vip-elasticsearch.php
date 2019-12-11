<?php

class VIP_ElasticSearch_Test extends \WP_UnitTestCase {
	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP ElasticSearch
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_filter_ep_index_name() {
		$mock_indexable = (object) [ 'slug' => 'slug' ];

		define( 'VIP_GO_APP_ID', 123 );
		define( 'USE_VIP_ELASTICSEARCH', true );

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'vip-123-slug-1', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP ElasticSearch
	 *
	 * USE_VIP_ELASTICSEARCH not defined
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_filter_ep_index_name__no_constant() {
		$mock_indexable = (object) [ 'slug' => 'slug' ];

		define( 'VIP_GO_APP_ID', 123 );

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'index-name', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP ElasticSearch
	 *
	 * USE_VIP_ELASTICSEARCH is false
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_elasticsearch_filter_ep_index_name__constant_is_false() {
		$mock_indexable = (object) [ 'slug' => 'slug' ];

		define( 'VIP_GO_APP_ID', 123 );
		define( 'USE_VIP_ELASTICSEARCH', false );

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'index-name', $index_name );
	}
}
