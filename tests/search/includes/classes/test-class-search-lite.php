<?php

namespace Automattic\VIP\Search;

use ElasticPress\Features;
use ElasticPress\Indexables;
use WP_UnitTestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/mock-header.php';
require_once __DIR__ . '/../../../../search/search.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-versioning.php';
require_once __DIR__ . '/../../../../search/elasticpress/elasticpress.php';

class Search_Lite_Test extends WP_UnitTestCase {
	use ExpectPHPException;

	public $test_index_name = 'vip-1234-post-0-v3';

	/** @var Search  */
	private $search_instance;

	public function setUp(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['ep_debug'] );

		$this->search_instance = new Search();
		$this->search_instance->init();

		do_action( 'plugins_loaded' );

		$cache_key = Search::INDEX_EXISTENCE_CACHE_KEY_PREFIX . $this->test_index_name;
		wp_cache_delete( $cache_key, Search::SEARCH_CACHE_GROUP );

		header_remove();
	}

	public function test_query_es_with_invalid_type() {
		$result = $this->search_instance->query_es( 'foo' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'indexable-not-found', $result->get_error_code() );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Search
	 */
	public function test__vip_search_filter_ep_index_name() {
		$indexable = Indexables::factory()->get( 'post' );

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $indexable );

		$this->assertEquals( 'vip-123-post-1', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Search for global indexes
	 *
	 * On "global" indexes, such as users, no blog id will be present
	 */
	public function test__vip_search_filter_ep_index_name_global_index() {
		$indexable = Indexables::factory()->get( 'post' );

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $indexable );

		$this->assertEquals( 'vip-123-post', $index_name );
	}

	public function vip_search_filter_ep_index_name_with_versions_data() {
		return array(
			array(
				// Active index number
				1,
				// Blog id
				null,
				// Expected index name
				'vip-123-post',
			),
			array(
				// Active index number
				2,
				// Blog id
				null,
				// Expected index name
				'vip-123-post-v2',
			),
			array(
				// Active index number
				1,
				// Blog id
				2,
				// Expected index name
				'vip-123-post-2',
			),
			array(
				// Active index number
				2,
				// Blog id
				2,
				// Expected index name
				'vip-123-post-2-v2',
			),
			array(
				// Active index number
				null,
				// Blog id
				null,
				// Expected index name
				'vip-123-post',
			),
			array(
				// Active index number
				0,
				// Blog id
				null,
				// Expected index name
				'vip-123-post',
			),
		);
	}

	/**
	 * Test `ep_index_name` filter with versioning
	 *
	 * When current version is 1, the index name should not have a version applied to it
	 *
	 * @dataProvider vip_search_filter_ep_index_name_with_versions_data
	 */
	public function test__vip_search_filter_ep_index_name_with_versions( $current_version, $blog_id, $expected_index_name ) {
		$indexable = Indexables::factory()->get( 'post' );

		// Mock the Versioning class so we can control which version it returns
		$stub = $this->getMockBuilder( Versioning::class )
				->setMethods( [ 'get_current_version_number' ] )
				->getMock();

		$stub->expects( $this->once() )
				->method( 'get_current_version_number' )
				->with( $indexable )
				->will( $this->returnValue( $current_version ) );

		$this->search_instance->versioning = $stub;

		$index_name = apply_filters( 'ep_index_name', 'index-name', $blog_id, $indexable );

		$this->assertEquals( $expected_index_name, $index_name );
	}

	public function test__vip_search_filter__ep_global_alias() {
		$indexable = Indexables::factory()->get( 'post' );

		$alias_name = $indexable->get_network_alias();

		$this->assertEquals( 'vip-123-post-all', $alias_name );
	}

	public function test__vip_search_filter_ep_default_index_number_of_shards() {
		$shards = apply_filters( 'ep_default_index_number_of_shards', 5 );

		$this->assertEquals( 1, $shards );
	}

	public function test__vip_search_filter_ep_default_index_number_of_shards_large_site() {
		// Simulate a large site
		$return_big_count = function( $counts ) {
			$counts->publish = 2000000;

			return $counts;
		};

		add_filter( 'wp_count_posts', $return_big_count );

		$shards = apply_filters( 'ep_default_index_number_of_shards', 5 );

		$this->assertEquals( 4, $shards );
	}

	public function test__vip_search_filter_ep_default_index_number_of_replicas() {
		$replicas = apply_filters( 'ep_default_index_number_of_replicas', 2 );

		$this->assertEquals( 1, $replicas );
	}

	public function vip_search_enforces_disabled_features_data() {
		return array(
			array( 'documents' ),
		);
	}

	/**
	 * Test that given an EP Feature slug, that feature is always disabled
	 *
	 * @dataProvider vip_search_enforces_disabled_features_data
	 */
	public function test__vip_search_enforces_disabled_features( $slug ) {
		Features::factory()->activate_feature( $slug );

		// And attempt to force-enable it via filter
		add_filter( 'ep_feature_active', '__return_true' );

		$active = Features::factory()->get_registered_feature( $slug )->is_active();

		$this->assertFalse( $active );
	}

	/**
	 * Test that we set a default bulk index chunk size limit
	 */
	public function test__vip_search_bulk_chunk_size_default() {
		$es = new Search();
		$es->init();

		$this->assertEquals( EP_SYNC_CHUNK_LIMIT, 500 );
	}

	public function vip_search_get_http_timeout_for_query_data() {
		return array(
			// Regular search
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_search',
				),
				// The expected timeout
				2,
			),
			// Bulk index
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_bulk',
				),
				// The expected timeout
				5,
			),
			// Url containing _bulk
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_bulk/bar?_bulk',
				),
				// The expected timeout
				2,
			),
			// Random other url
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_anything',
				),
				// The expected timeout
				2,
			),
		);
	}

	/**
	 * Test that we correctly calculate the HTTP request timeout value for ES requests
	 *
	 * @dataProvider vip_search_get_http_timeout_for_query_data()
	 */
	public function test__vip_search_get_http_timeout_for_query( $query, $expected_timeout ) {
		$timeout = $this->search_instance->get_http_timeout_for_query( $query, array() );

		$this->assertEquals( $expected_timeout, $timeout );
	}

	/**
	 * Ensure that we're allowing querying during bulk re-index, via the ep_enable_query_integration_during_indexing filter
	 */
	public function test__vip_search_filter__ep_enable_query_integration_during_indexing() {
		$allowed = apply_filters( 'ep_enable_query_integration_during_indexing', false );

		$this->assertTrue( $allowed );
	}

	/*
	 * Test for making sure the load balance functionality works
	 */
	public function test__vip_search_get_random_host() {
		$hosts = array(
			'test0',
			'test1',
			'test2',
			'test3',
		);

		$this->assertContains( $this->search_instance->get_random_host( $hosts ), $hosts );
	}

	public function test__send_vary_headers__sent_for_group() {
		$_GET['ep_debug'] = true;

		apply_filters( 'ep_valid_response', array(), array(), array(), array(), null );

		do_action( 'send_headers' );

		$headers = headers_list();
		$this->assertContains( 'X-ElasticPress-Search-Valid-Response: true', $headers, '', true );
	}

	public function test__vip_search_filter__ep_facet_taxonomies_size() {
		$this->assertEquals( 5, $this->search_instance->filter__ep_facet_taxonomies_size( 10000, 'category' ) );
	}

	public function vip_search_filter__jetpack_active_modules() {
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
	 * @dataProvider vip_search_filter__jetpack_active_modules
	 */
	public function test__vip_search_filter__jetpack_active_modules( $input, $expected ) {
		$result = $this->search_instance->filter__jetpack_active_modules( $input );

		$this->assertEquals( $expected, $result );
	}

	public function vip_search_filter__jetpack_widgets_to_include_data() {
		return array(
			array(
				// Input
				array(
					'/path/to/jetpack/modules/widgets/file.php',
					'/path/to/jetpack/modules/widgets/other.php',
				),

				// Expected
				array(
					'/path/to/jetpack/modules/widgets/file.php',
					'/path/to/jetpack/modules/widgets/other.php',
				),
			),

			array(
				// Input
				array(
					'/path/to/jetpack/modules/widgets/file.php',
					'/path/to/jetpack/modules/widgets/search.php',
					'/path/to/jetpack/modules/widgets/other.php',
				),

				// Expected
				array(
					'/path/to/jetpack/modules/widgets/file.php',
					'/path/to/jetpack/modules/widgets/other.php',
				),
			),

			array(
				// Input
				12345, // non-array

				// Expected
				12345,
			),
		);
	}

	/**
	 * Test that the widgets filter works as expected
	 *
	 * @dataProvider vip_search_filter__jetpack_widgets_to_include_data
	 */
	public function test__vip_search_filter__jetpack_widgets_to_include( $input, $expected ) {
		$result = $this->search_instance->filter__jetpack_widgets_to_include( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that the track_total_hits arg exists
	 */
	public function test__vip_filter__ep_post_formatted_args() {
		$result = $this->search_instance->filter__ep_post_formatted_args( array(), '', '' );

		$this->assertTrue( array_key_exists( 'track_total_hits', $result ), 'track_total_hits doesn\'t exist in fortmatted args' );
		if ( array_key_exists( 'track_total_hits', $result ) ) {
			$this->assertTrue( $result['track_total_hits'], 'track_total_hits isn\'t set to true' );
		}
	}

	public function get_statsd_request_mode_for_request_data() {
		return array(
			// Search
			array(
				'https://host/_search',
				'post',
				'search',
			),
			array(
				'https://host/index-name/_search',
				'post',
				'search',
			),
			array(
				'https://host/index-name/_search?foo=bar',
				'post',
				'search',
			),
			array(
				'https://host/index-name/_search',
				'get',
				'search',
			),
			array(
				'https://host/index-name/_search?foo=bar',
				'get',
				'search',
			),

			// Get
			array(
				'https://host/index-name/_doc/12345',
				'get',
				'get',
			),
			array(
				'https://host/index-name/_doc/12345',
				'head',
				'other',
			),
			array(
				'https://host/index-name/_mget',
				'get',
				'get',
			),
			array(
				'https://host/index-name/_mget?foo=bar',
				'post',
				'get',
			),

			// Delete
			array(
				'https://host/index-name/_doc/12345',
				'delete',
				'delete',
			),
			array(
				'https://host/index-name/_doc/12345?foo=bar',
				'delete',
				'delete',
			),

			// Indexing
			array(
				'https://host/index-name/_doc/12345',
				'put',
				'index',
			),
			array(
				'https://host/index-name/_doc',
				'post',
				'index',
			),
			array(
				'https://host/index-name/_create/12345',
				'post',
				'index',
			),
			array(
				'https://host/index-name/_create/12345',
				'put',
				'index',
			),
			array(
				'https://host/index-name/_update/12345',
				'post',
				'index',
			),

			// Bulk indexing
			array(
				'https://host/_bulk',
				'post',
				'index',
			),
			array(
				'https://host/index-name/_bulk',
				'post',
				'index',
			),
			array(
				'https://host/index-name/_bulk?foo=bar',
				'post',
				'index',
			),
		);
	}

	/**
	 * Test that we correctly determine the right stat (referred to as "mode" on wpcom)
	 * for a given ES url
	 *
	 * manage|analyze|status|langdetect|index|delete_query|get|scroll|search
	 *
	 * @dataProvider get_statsd_request_mode_for_request_data()
	 */
	public function test_get_statsd_request_mode_for_request( $url, $method, $expected_mode ) {
		$args = array(
			'method' => $method,
		);

		$mode = $this->search_instance->get_statsd_request_mode_for_request( $url, $args );

		$this->assertEquals( $expected_mode, $mode );
	}

	public function get_index_name_for_url_data() {
		return array(
			// Search
			array(
				'https://host.com/_search',
				null,
			),
			array(
				'https://host.com/index-name/_search',
				'index-name',
			),
			array(
				'https://host.com/index-name,index-name-2/_search',
				'index-name,index-name-2',
			),
			// Other misc operations
			array(
				'https://host.com/index-name/_bulk',
				'index-name',
			),
			array(
				'https://host.com/index-name/_doc',
				'index-name',
			),
			array(
				'  https://host.com/index-name/_doc  ',
				'index-name',
			),
		);
	}

	/**
	 * Test that we correctly determine the index name from an ES API url for stats purposes
	 *
	 * @dataProvider get_index_name_for_url_data()
	 */
	public function test_get_index_name_for_url( $url, $expected_index_name ) {
		$index_name = $this->search_instance->get_index_name_for_url( $url );

		$this->assertEquals( $expected_index_name, $index_name );
	}

	public function get_statsd_prefix_data() {
		return array(
			array(
				'https://es-ha-bur.vipv2.net:1234',
				'search',
				'com.wordpress.elasticsearch.bur.ha1234_vipgo.search',
			),
			array(
				'https://es-ha-dca.vipv2.net:4321',
				'index',
				'com.wordpress.elasticsearch.dca.ha4321_vipgo.index',
			),
		);
	}

	/**
	 * @dataProvider get_statsd_prefix_data
	 */
	public function test_get_statsd_prefix( $url, $mode, $expected ) {
		$prefix = $this->search_instance->get_statsd_prefix( $url, $mode );

		$this->assertEquals( $expected, $prefix );
	}

	/**
	 * Test formatted args structure checks
	 */
	public function test__vip_search_filter__ep_formatted_args() {
		$this->assertEquals( array( 'wrong' ), $this->search_instance->filter__ep_formatted_args( array( 'wrong' ), '' ), 'didn\'t just return formatted args when the structure of formatted args didn\'t match what was expected' );

		$formatted_args = array(
			'query' => array(
				'bool' => array(
					'should' => array(
						array(
							'multi_match' => array(
								'operator' => 'Random string',
							),
						),
						'Random string',
					),
				),
			),
		);

		$result = $this->search_instance->filter__ep_formatted_args( $formatted_args, '' );

		$this->assertTrue( array_key_exists( 'must', $result['query']['bool'] ), 'didn\'t replace should with must' );
		$this->assertEquals( $result['query']['bool']['must'][0]['multi_match']['operator'], 'AND', 'didn\'t set the remainder of the query correctly' );
	}

	/**
	 * Ensure we disable indexing of filtered content by default
	 */
	public function test__vip_search_filter__ep_allow_post_content_filtered_index() {
		$enabled = apply_filters( 'ep_allow_post_content_filtered_index', true );

		$this->assertFalse( $enabled );
	}

	/*
	 * Ensure that is_query_integration_enabled() is false by default with no options/constants
	 */
	public function test__is_query_integration_enabled_default() {
		$this->assertFalse( Search::is_query_integration_enabled() );
	}

	/*
	 * Ensure is_query_integration_enabled() option works properly with the vip_enable_vip_search_query_integration option
	 */
	public function test__is_query_integration_enabled_via_option() {
		try {
			update_option( 'vip_enable_vip_search_query_integration', true );
			$this->assertTrue( Search::is_query_integration_enabled() );
		} finally {
			delete_option( 'vip_enable_vip_search_query_integration' );
		}
	}

	/**
	 * Ensure query integration is enabled when the 'es' query param is set
	 */
	public function test__is_query_integration_enabled_via_query_param() {
		try {
			// Set es query string to test override
			$_GET[ Search::QUERY_INTEGRATION_FORCE_ENABLE_KEY ] = true;

			$this->assertTrue( Search::is_query_integration_enabled() );
		} finally {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET[ Search::QUERY_INTEGRATION_FORCE_ENABLE_KEY ] );
		}
	}

	public function test_is_network_mode_default() {
		$this->assertFalse( Search::is_network_mode() );
	}

	/**
	 * Ensure that EP query integration is disabled by default
	 */
	public function test__ep_skip_query_integration_default() {
		$this->assertTrue( Search::ep_skip_query_integration( false ) );
	}

	/**
	 * Ensure the incrementor for tracking request counts behaves properly
	 */
	public function test__query_count_incr() {
		$query_count_incr = self::get_method( 'query_count_incr' );

		// Reset cache key
		wp_cache_delete( Search::QUERY_COUNT_CACHE_KEY, Search::SEARCH_CACHE_GROUP );

		$this->assertEquals( 1, $query_count_incr->invokeArgs( $this->search_instance, [] ), 'initial value should be 1' );

		for ( $i = 2; $i < 10; $i++ ) {
			$this->assertEquals( $i, $query_count_incr->invokeArgs( $this->search_instance, [] ), 'value should increment with loop' );
		}
	}

	public function test__truncate_search_string_length() {
		$expected_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpgg';
		$provided_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpgg' .
			'g5oMk1nWsx5fJ0B3bAFYKt1Y5dOA0Q4lQUqj8mf1LjcmR73wQwujc1GQfgCKj9X9Ktr6LrDtN5zAJFQboAJa7fZ9AiGxbJqUrLFs';

		$wp_query_mock = new \WP_Query();

		$wp_query_mock->set( 's', $provided_search_string );
		$wp_query_mock->is_search = true;

		$this->search_instance->truncate_search_string_length( $wp_query_mock );

		$this->assertEquals( $expected_search_string, $wp_query_mock->get( 's' ) );
	}

	public function test__limit_field_limit_absolute_maximum_is_20000() {
		// Don't trigger an error since it's expected
		add_filter( 'doing_it_wrong_trigger_error', '__return_false', PHP_INT_MAX );

		$this->assertEquals( 20000, $this->search_instance->limit_field_limit( 1000000 ) );
	}

	public function test__limit_field_limit_should_respect_values_under_maximum() {
		$this->assertEquals( 777, $this->search_instance->limit_field_limit( 777 ) );
	}

	public function test__ep_total_field_limit_should_limit_total_fields() {
		// Don't trigger an error since it's expected
		add_filter( 'doing_it_wrong_trigger_error', '__return_false', PHP_INT_MAX );

		add_filter(
			'ep_total_field_limit',
			function() {
				return 1000000;
			}
		);

		$this->assertEquals( 20000, apply_filters( 'ep_total_field_limit', 5000 ) );
	}

	public function test__ep_total_field_limit_should_respect_values_under_the_limit() {
		add_filter(
			'ep_total_field_limit',
			function() {
				return 787;
			}
		);

		$this->assertEquals( 787, apply_filters( 'ep_total_field_limit', 5000 ) );
	}

	public function get_filter__ep_sync_taxonomies_default_data() {
		return array(
			array(
				array(),
			),
			array(
				array(
					(object) array(
						'name' => 'category',
					),
				),
			),
			array(
				array(
					(object) array(
						'name' => 'category',
					),
					(object) array(
						'name' => 'post_tag',
					),
				),
			),
		);
	}

	/**
	 * @dataProvider get_filter__ep_sync_taxonomies_default_data
	 */
	public function test__filter__ep_sync_taxonomies_default( $input_taxonomies ) {
		$post = new \stdClass();

		$filtered_taxonomies = apply_filters( 'ep_sync_taxonomies', $input_taxonomies, $post );

		$input_taxonomy_names    = wp_list_pluck( $input_taxonomies, 'name' );
		$filtered_taxonomy_names = wp_list_pluck( $filtered_taxonomies, 'name' );

		// No change expected
		$this->assertEquals( $input_taxonomy_names, $filtered_taxonomy_names );
	}

	public function test__filter__ep_sync_taxonomies_added() {
		$post = new \stdClass();

		$start_taxonomies = array(
			(object) array(
				'name' => 'category',
			),
		);

		add_filter(
			'vip_search_post_taxonomies_allow_list',
			function( $taxonomies ) {
				$taxonomies[] = 'post_tag';
				$taxonomies[] = 'post_tag';

				return $taxonomies;
			}
		);

		$filtered_taxonomies = apply_filters( 'ep_sync_taxonomies', $start_taxonomies, $post );

		// Pull out just the names, for easier comparison
		$filtered_taxonomy_names = wp_list_pluck( $filtered_taxonomies, 'name' );

		$expected_taxonomy_names = array(
			'category',
			'post_tag',
		);

		// Should now include the additional taxonomies
		$this->assertEquals( $expected_taxonomy_names, $filtered_taxonomy_names );
	}

	public function test__filter__ep_sync_taxonomies_removed() {
		$post = new \stdClass();

		$start_taxonomies = array(
			(object) array(
				'name' => 'category',
			),
			(object) array(
				'name' => 'post_tag',
			),
		);

		add_filter(
			'vip_search_post_taxonomies_allow_list',
			function() {
				return array( 'post_tag' );
			}
		);

		$filtered_taxonomies = apply_filters( 'ep_sync_taxonomies', $start_taxonomies, $post );

		// Pull out just the names, for easier comparison
		$filtered_taxonomy_names = wp_list_pluck( $filtered_taxonomies, 'name' );

		$expected_taxonomy_names = array(
			'post_tag',
		);

		// Should now not include the removed taxonomies
		$this->assertEquals( $expected_taxonomy_names, $filtered_taxonomy_names );
	}

	public function test__is_jetpack_migration__no_constant() {
		$this->assertFalse( $this->search_instance->is_jetpack_migration() );
	}

	public function test__filter__ep_prepare_meta_data_allow_list_should_be_respected_by_default() {
		add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_post_meta',
					'another_one',
					'third',
				);
			}
		);

		// Matches allow list
		$post_meta = array(
			'random_post_meta' => array(
				'Random value',
			),
			'another_one'      => array(
				'4656784',
			),
			'third'            => array(
				'true',
			),
		);

		$post_meta['random_thing_not_allow_listed'] = array( 'Missing' );

		$post     = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		$meta = $this->search_instance->filter__ep_prepare_meta_data( $post_meta, $post );

		unset( $post_meta['random_thing_not_allow_listed'] ); // Remove last added value that should have been excluded by the filter

		$this->assertEquals( $meta, $post_meta );
	}

	public function test__filter__ep_prepare_meta_data_allow_list_should_be_respected_by_default_assoc() {
		add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_post_meta' => true,
					'another_one'      => true,
					'skipped'          => false,
					'skipped_another'  => 4,
					'skipped_string'   => 'Wooo',
					'third'            => true,
				);
			}
		);

		// Matches allow list
		$post_meta = array(
			'random_post_meta' => array(
				'Random value',
			),
			'another_one'      => array(
				'4656784',
			),
			'skipped'          => array(
				'Skip',
			),
			'skipped_another'  => array(
				'Skip',
			),
			'skipped_string'   => array(
				'Skip',
			),
			'third'            => array(
				'true',
			),
		);

		$post_meta['random_thing_not_allow_listed'] = array( 'Missing' );

		$post     = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		$meta = $this->search_instance->filter__ep_prepare_meta_data( $post_meta, $post );

		$this->assertEquals(
			$meta,
			array(
				'random_post_meta' => array(
					'Random value',
				),
				'another_one'      => array(
					'4656784',
				),
				'third'            => array(
					'true',
				),
			)
		);
	}

	public function get_origin_dc_from_es_endpoint_data() {
		return array(
			array(
				'https://es-ha.bur.vipv2.net:1234',
				'bur',
			),
			array(
				'https://es-ha.dca.vipv2.net:4321',
				'dca',
			),
			array(
				'https://es-ha.DCA.vipv2.net:4321',
				'dca',
			),
			array(
				'https://es-ha.dfw.vipv2.net:4321',
				'dfw',
			),
		);
	}

	/**
	 * @dataProvider get_origin_dc_from_es_endpoint_data
	 */
	public function test__get_origin_dc_from_es_endpoint( $host, $expected ) {
		$origin_dc = $this->search_instance->get_origin_dc_from_es_endpoint( $host );

		$this->assertEquals( $expected, $origin_dc );
	}

	public function test__filter__ep_skip_post_meta_sync_should_return_true_if_meta_not_in_allow_list() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = \get_post( $post_id );

		$this->assertTrue( $this->search_instance->filter__ep_skip_post_meta_sync( false, $post, 40, 'random_key', 'random_value' ) );
	}

	public function test__filter__ep_skip_post_meta_sync_should_return_false_if_meta_is_in_allow_list() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = get_post( $post_id );

		add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_key',
				);
			}
		);

		$this->assertFalse( $this->search_instance->filter__ep_skip_post_meta_sync( false, $post, 40, 'random_key', 'random_value' ) );
	}

	public function test__filter__ep_skip_post_meta_sync_should_return_true_if_a_previous_filter_is_true() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = get_post( $post_id );

		add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_key',
				);
			}
		);

		$this->assertTrue( $this->search_instance->filter__ep_skip_post_meta_sync( true, $post, 40, 'random_key', 'random_value' ) );
	}

	public function test__ep_skip_post_meta_sync_filter_should_return_false_if_meta_is_in_allow_list() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = get_post( $post_id );

		add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_key',
				);
			}
		);

		$this->assertFalse( apply_filters( 'ep_skip_post_meta_sync', false, $post, 40, 'random_key', 'random_value' ) );
	}

	public function test__ep_skip_post_meta_sync_filter_should_return_true_if_a_previous_filter_is_true() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = get_post( $post_id );

		add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_key',
				);
			}
		);

		$this->assertTrue( apply_filters( 'ep_skip_post_meta_sync', true, $post, 40, 'random_key', 'random_value' ) );
	}

	public function get_sanitize_ep_query_for_logging_data() {
		return array(
			// No Auth header present
			array(
				// The "query" from ElasticPress
				array(
					'args' => array(
						'headers' => array(
							'some' => 'header',
						),
					),
				),
				// Expected sanitized value
				array(
					'args' => array(
						'headers' => array(
							'some' => 'header',
						),
					),
				),
			),
			// Auth header present, should be sanitized
			array(
				array(
					'args' => array(
						'headers' => array(
							'Authorization' => 'foo',
							'some'          => 'header',
						),
					),
				),
				array(
					'args' => array(
						'headers' => array(
							'Authorization' => '<redacted>',
							'some'          => 'header',
						),
					),
				),
			),
		);
	}

	/**
	 * @dataProvider get_sanitize_ep_query_for_logging_data
	 */
	public function test__sanitize_ep_query_for_logging( $input, $expected ) {
		$sanitized = $this->search_instance->sanitize_ep_query_for_logging( $input );

		$this->assertEquals( $expected, $sanitized );
	}

	public function test__add_attachment_to_ep_indexable_post_types_should_return_the_passed_value_if_not_array() {
		$es = $this->search_instance;

		$this->assertEquals( 'testing', $es->add_attachment_to_ep_indexable_post_types( 'testing' ) );
		$this->assertEquals( 65, $es->add_attachment_to_ep_indexable_post_types( 65 ) );
		$this->assertEquals( null, $es->add_attachment_to_ep_indexable_post_types( null ) );
		$this->assertEquals( new \StdClass(), $es->add_attachment_to_ep_indexable_post_types( new \StdClass() ) );
	}

	public function test__add_attachment_to_ep_indexable_post_types_should_append_attachment_to_array() {
		$es = $this->search_instance;

		$this->assertEquals( array( 'attachment' => 'attachment' ), $es->add_attachment_to_ep_indexable_post_types( array() ) );
		$this->assertEquals(
			array(
				'test'       => 'test',
				'one'        => 'one',
				'attachment' => 'attachment',
			),
			$es->add_attachment_to_ep_indexable_post_types(
				array(
					'test' => 'test',
					'one'  => 'one',
				)
			)
		);
	}

	public function test__ep_indexable_post_types_should_return_the_passed_value_if_not_array() {
		$this->assertEquals( 'testing', apply_filters( 'ep_indexable_post_types', 'testing' ) );
		$this->assertEquals( 65, apply_filters( 'ep_indexable_post_types', 65 ) );
		$this->assertEquals( null, apply_filters( 'ep_indexable_post_types', null ) );
		$this->assertEquals( new \StdClass(), apply_filters( 'ep_indexable_post_types', new \StdClass() ) );
	}

	public function test__is_protected_content_enabled_should_return_true_if_protected_content_enabled() {
		$es = $this->search_instance;

		Features::factory()->activate_feature( 'protected_content' );
		$this->assertTrue( $es->is_protected_content_enabled() );
	}

	public function test__get_random_host_return_null_if_no_host() {
		$es = $this->search_instance;

		$this->assertSame( null, $es->get_random_host( array() ) );
	}

	public function test__get_random_host_return_null_if_hosts_is_not_array() {
		$es = $this->search_instance;

		$this->assertSame( null, $es->get_random_host( false ) );
	}

	public function limit_max_result_window_data() {
		return [
			[
				'input'    => 500,
				'expected' => 500,
			],
			[
				'input'    => 10000,
				'expected' => 10000,
			],
		];
	}

	/**
	 * @dataProvider limit_max_result_window_data
	 */
	public function test__limit_max_result_window( $input, $expected ) {
		$es = $this->search_instance;

		$result = $es->limit_max_result_window( $input );

		$this->assertEquals( $expected, $result );
	}

	public function test__are_es_constants_defined__no_constatns() {
		$result = Search::are_es_constants_defined();

		$this->assertFalse( $result );
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class  = new \ReflectionClass( __NAMESPACE__ . '\Search' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}
}
