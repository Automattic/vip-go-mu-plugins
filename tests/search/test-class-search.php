<?php

namespace Automattic\VIP\Search;

class Search_Test extends \WP_UnitTestCase {
	/**
	 * Make tests run in separate processes since we're testing state
	 * related to plugin init, including various constants.
	 */
	protected $preserveGlobalState = FALSE;
	protected $runTestInSeparateProcess = TRUE;

	public function setUp() {
		require_once __DIR__ . '/../../search/search.php';

		$this->search_instance = new \Automattic\VIP\Search\Search();
	}

	public function test_query_es_with_invalid_type() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$result = $es->query_es( 'foo' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'indexable-not-found', $result->get_error_code() );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Search
	 */
	public function test__vip_search_filter_ep_index_name() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$mock_indexable = (object) [ 'slug' => 'slug' ];

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'vip-123-slug-1', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Search for global indexes
	 *
	 * On "global" indexes, such as users, no blog id will be present
	 */
	public function test__vip_search_filter_ep_index_name_global_index() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$mock_indexable = (object) [ 'slug' => 'users' ];

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $mock_indexable );

		$this->assertEquals( 'vip-123-users', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Search
	 *
	 * USE_VIP_ELASTICSEARCH not defined (Elasticseach class doesn't load)
	 */
	public function test__vip_search_filter_ep_index_name__no_constant() {
		$mock_indexable = (object) [ 'slug' => 'slug' ];

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $mock_indexable );

		$this->assertEquals( 'index-name', $index_name );
	}

	public function test__vip_search_filter_ep_default_index_number_of_shards() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$shards = apply_filters( 'ep_default_index_number_of_shards', 5 );

		$this->assertEquals( 1, $shards );
	}

	public function test__vip_search_filter_ep_default_index_number_of_shards_large_site() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		// Simulate a large site
		$return_big_count = function( $counts ) {
			$counts->publish = 2000000;

			return $counts;
		};

		add_filter( 'wp_count_posts', $return_big_count );

		$shards = apply_filters( 'ep_default_index_number_of_shards', 5 );

		$this->assertEquals( 4, $shards );

		remove_filter( 'wp_count_posts', $return_big_count );
	}

	public function test__vip_search_filter_ep_default_index_number_of_replicas() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$replicas = apply_filters( 'ep_default_index_number_of_replicas', 1 );

		$this->assertEquals( 2, $replicas );
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
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		do_action( 'plugins_loaded' );

		// Activate the feature
		\ElasticPress\Features::factory()->activate_feature( $slug );

		// And attempt to force-enable it via filter
		add_filter( 'ep_feature_active', '__return_true' );

		$active = \ElasticPress\Features::factory()->get_registered_feature( $slug )->is_active();

		$this->assertFalse( $active );
	}

	/**
	 * Test that we set a default bulk index chunk size limit
	 */
	public function test__vip_search_bulk_chunk_size_default() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertEquals( EP_SYNC_CHUNK_LIMIT, 500 );
	}

	/**
	 * Test that the default bulk index chunk size limit is not applied if constant is already defined
	 */
	public function test__vip_search_bulk_chunk_size_already_defined() {
		define( 'EP_SYNC_CHUNK_LIMIT', 500 );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertEquals( EP_SYNC_CHUNK_LIMIT, 500 );
	}

	/**
	 * Test that the default bulk index chunk size limit is not defined if we're not using VIP Search
	 */
	public function test__vip_search_bulk_chunk_size_not_defined_when_not_using_vip_search() {
		$this->assertEquals( defined( 'EP_SYNC_CHUNK_LIMIT' ), false );
	}

	/**
	 * Test that the ES config constants are set automatically when not already defined and VIP-provided configs are present
	 */
	public function test__vip_search_connection_constants() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
			'https://es-endpoint1',
			'https://es-endpoint2',
		) );

		define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertContains( EP_HOST, VIP_ELASTICSEARCH_ENDPOINTS );
		$this->assertEquals( ES_SHIELD, 'foo:bar' );
	}

	/**
	 * Test that the ES config constants are _not_ set automatically when already defined and VIP-provided configs are present
	 * 
	 */
	public function test__vip_search_connection_constants_with_overrides() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
			'https://es-endpoint1',
			'https://es-endpoint2',
		) );

		define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		// Client over-rides - don't fatal
		define( 'EP_HOST', 'https://somethingelse' );
		define( 'ES_SHIELD', 'bar:baz' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertEquals( EP_HOST, 'https://somethingelse' );
		$this->assertEquals( ES_SHIELD, 'bar:baz' );
	}

	/**
	 * Test that we load the ElasticPress Debug Bar plugin when Debug Bar is showing
	 */
	public function test__vip_search_loads_ep_debug_bar_when_debug_bar_showing() {
		// Remove previous filters that would affect test (b/c it also uses PHP_INT_MAX priority)
		remove_all_filters( 'debug_bar_enable' );

		// Debug bar enabled
		add_filter( 'debug_bar_enable', '__return_true', PHP_INT_MAX );

		// Be sure we don't already have the class loaded (or our test does nothing)
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ), 'EP Debug Bar plugin already loaded, therefore this test is not asserting that the plugin is loaded' );

		// Be sure the constant isn't already defined (or our test does not assert that it was defined at runtime)
		$this->assertEquals( false, defined( 'WP_EP_DEBUG' ), 'WP_EP_DEBUG constant already defined, therefore this test is not asserting that the constant is set at runtime' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$es->action__plugins_loaded();

		// Class should now exist
		$this->assertEquals( true, function_exists( 'ep_add_debug_bar_panel' ), 'EP Debug Bar was not found' );

		// And the debug constant should have been set (required for saving queries)
		$this->assertEquals( true, constant( 'WP_EP_DEBUG' ), 'Incorrect value for WP_EP_DEBUG constant' );
	}

	/**
	 * Test that we load the ElasticPress Debug Bar plugin when Debug Bar is disabled, but Query Monitor is showing
	 */
	public function test__vip_search_loads_ep_debug_bar_when_debug_bar_disabled_but_qm_enabled() {
		// Remove previous filters that would affect test (b/c it also uses PHP_INT_MAX priority)
		remove_all_filters( 'debug_bar_enable' );

		// Debug bar disabled
		add_filter( 'debug_bar_enable', '__return_false', PHP_INT_MAX );
		// But QM enabled
		add_filter( 'wpcom_vip_qm_enable', '__return_true', PHP_INT_MAX );

		// Be sure we don't already have the class loaded (or our test does nothing)
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ) );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$es->action__plugins_loaded();

		// Class should now exist
		$this->assertEquals( true, function_exists( 'ep_add_debug_bar_panel' ) );
	}

	/**
	 * Test that we load the ElasticPress Debug Bar plugin when both Debug Bar Query Monitor are showing
	 */
	public function test__vip_search_loads_ep_debug_bar_when_debug_bar_and_qm_enabled() {
		// Remove previous filters that would affect test (b/c it also uses PHP_INT_MAX priority)
		remove_all_filters( 'debug_bar_enable' );

		// Debug bar enabled
		add_filter( 'debug_bar_enable', '__return_true', PHP_INT_MAX );
		// And QM enabled
		add_filter( 'wpcom_vip_qm_enable', '__return_true', PHP_INT_MAX );

		// Be sure we don't already have the class loaded (or our test does nothing)
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ) );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$es->action__plugins_loaded();

		// Class should now exist
		$this->assertEquals( true, function_exists( 'ep_add_debug_bar_panel' ) );
	}

	/**
	 * Test that we don't load the ElasticPress Debug Bar plugin when neither Debug Bar or Query Monitor are showing
	 */
	public function test__vip_search_does_not_load_ep_debug_bar_when_debug_bar_and_qm_disabled() {
		// Remove previous filters that would affect test (b/c it also uses PHP_INT_MAX priority)
		remove_all_filters( 'debug_bar_enable' );

		// Debug bar disabled
		add_filter( 'debug_bar_enable', '__return_false', PHP_INT_MAX );
		// And QM disabled
		add_filter( 'wpcom_vip_qm_enable', '__return_false', PHP_INT_MAX );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$es->action__plugins_loaded();

		// Class should not exist
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ) );
	}

	/**
	 * Test that we are sending HTTP requests through the VIP helper functions
	 */
	public function test__vip_search_has_http_layer_filters() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertEquals( true, has_filter( 'ep_intercept_remote_request', '__return_true' ) );
		$this->assertEquals( true, has_filter( 'ep_do_intercept_request', [ $es, 'filter__ep_do_intercept_request' ] ) );
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
		$es = new \Automattic\VIP\Search\Search();

		$timeout = $es->get_http_timeout_for_query( $query, array() );

		$this->assertEquals( $expected_timeout, $timeout );
	}

	/**
	 * Test that instantiating the HealthJob works as expected (files are properly included, init is hooked)
	 */
	public function test__vip_search_setup_healthchecks_with_enabled() {
		// Need to filter to enable the HealthJob
		add_filter( 'enable_vip_search_healthchecks', '__return_true' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		// Should not have fataled (class was included)

		// Should have registered the init action to setup the health check
		$this->assertEquals( true, has_action( 'init', [ $es->healthcheck, 'init' ] ) );
	}

	/**
	 * Test that instantiating the HealthJob does not happen when not in production
	 */
	public function test__vip_search_setup_healthchecks_disabled_in_non_production_env() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		// Should not have fataled (class was included)

		// Should not have instantiated and registered the init action to setup the health check
		$this->assertEquals( false, $es->healthcheck->is_enabled() );
	}

	/**
	 * Test that checks both single and multi-host retries
	 */
	public function test__vip_search_filter__ep_pre_request_host() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		// If VIP_ELASTICSEARCH_ENDPOINTS is not defined, just hand the last host back
		$this->assertEquals( 'test', $es->filter__ep_pre_request_host( 'test', 0, '', array() ), 'filter__ep_pre_request_host() did\'t just hand the last host back when VIP_ELASTICSEARCH_ENDPOINTS was undefined' );

		define( 
			'VIP_ELASTICSEARCH_ENDPOINTS', 
			array(
				'endpoint1',
				'endpoint2',
				'endpoint3',
				'endpoint4',
				'endpoint5',
				'endpoint6',
			) 
		);

		$this->assertContains( $es->filter__ep_pre_request_host( 'endpoint1', 0, '', array() ), VIP_ELASTICSEARCH_ENDPOINTS, 'filter__ep_pre_request_host() didn\'t return a value that exists in VIP_ELASTICSEARCH_ENDPOINTS with 0 total failures' );
		$this->assertContains( $es->filter__ep_pre_request_host( 'endpoint1', 107, '', array() ), VIP_ELASTICSEARCH_ENDPOINTS, 'filter__ep_pre_request_host() didn\'t return a value that exists in VIP_ELASTICSEARCH_ENDPOINTS with 107 failures' );
	}

	/*
	 * Test for making sure filter__ep_pre_request_host handles empty endpoint lists
	 */
	public function test__vip_search_filter__ep_pre_request_host_empty_endpoint() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();
		
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', array() );

		$this->assertEquals( 'test', $es->filter__ep_pre_request_host( 'test', 0, '', array() ) );
	}

	/*
	 * Test for making sure filter__ep_pre_request_host handles endpoint lists that aren't arrays
	 */
	public function test__vip_search_filter__ep_pre_request_host_endpoint_not_array() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();
		
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', 'Random string' );
	
		$this->assertEquals( 'test', $es->filter__ep_pre_request_host( 'test', 0, '', array() ) );
	}

	/**
	 * Ensure that we're allowing querying during bulk re-index, via the ep_enable_query_integration_during_indexing filter
	 */
	public function test__vip_search_filter__ep_enable_query_integration_during_indexing() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$allowed = apply_filters( 'ep_enable_query_integration_during_indexing', false );

		$this->assertTrue( $allowed );
	}

	/*
	 * Test for making sure the round robin function returns the next array value
	 */
	public function test__vip_search_get_next_host() {
		$es = new \Automattic\VIP\Search\Search();
		define( 'VIP_ELASTICSEARCH_ENDPOINTS',
			array(
				'test0',
				'test1',
				'test2', 
				'test3',
			)
		);

		$this->assertEquals( 'test0', $es->get_next_host( 0 ), 'get_next_host() didn\'t use the same host with 0 total failures and 4 hosts with a starting index of 0' );
		$this->assertEquals( 'test1', $es->get_next_host( 1 ), 'get_next_host() didn\'t get the correct host with 1 total failures and 4 hosts with a starting index of 0' );
		$this->assertEquals( 'test0', $es->get_next_host( 3 ), 'get_next_host() didn\'t restart at the beginning of the list upon reaching the end with 4 total failures and 4 hosts with a starting index of 1' );
		$this->assertEquals( 'test1', $es->get_next_host( 17 ), 'get_next_host() didn\'t match expected result with 21 total failures and 4 hosts. and a starting index of 0' );
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
		$es = new \Automattic\VIP\Search\Search();

		$this->assertContains( $es->get_random_host( $hosts ), $hosts );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__send_vary_headers__sent_for_group() {

		$es = new \Automattic\VIP\Search\Search();
		$es->init();
		
		$_GET['ep_debug'] = true;
		
		apply_filters( 'ep_valid_response', array(), array(), array(), array(), null );
		
		do_action( 'send_headers' );

		unset( $_GET['ep_debug'] );
		
		$this->assertContains( 'X-ElasticPress-Search-Valid-Response: true', xdebug_get_headers() );
	}

	public function test__vip_search_filter__ep_facet_taxonomies_size() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertEquals( 5, $es->filter__ep_facet_taxonomies_size( 10000, 'category' ) );
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
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$result = $es->filter__jetpack_active_modules( $input );

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
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$result = $es->filter__jetpack_widgets_to_include( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that the track_total_hits arg exists
	 */
	public function test__vip_filter__ep_post_formatted_args() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$result = $es->filter__ep_post_formatted_args( array(), '', '' );

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

	public function get_statsd_index_name_for_url_data() {
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
			array(
				'https://host.com/_all/_search',
				'_all',
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
		);
	}

	/**
	 * Test that we correctly determine the index name from an ES API url for stats purposes
	 * 
	 * @dataProvider get_statsd_index_name_for_url_data()
	 */
	public function test_get_statsd_index_name_for_url( $url, $expected_index_name ) {
		$index_name = $this->search_instance->get_statsd_index_name_for_url( $url );

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

	public function get_statsd_prefix_with_site_and_index_data() {
		return array(
			array(
				'https://es-ha-bur.vipv2.net:1234',
				'search',
				1,
				'vip-1-post',
				'com.wordpress.elasticsearch.bur.ha1234_vipgo.search.1.vip-1-post',
			),
			array(
				'https://es-ha-dca.vipv2.net:4321',
				'index',
				2,
				'vip-2-post-2',
				'com.wordpress.elasticsearch.dca.ha4321_vipgo.index.2.vip-2-post-2',
			),
			array(
				'https://es-ha-dca.vipv2.net:4321',
				'index',
				3,
				'vip-3-post-2-2',
				'com.wordpress.elasticsearch.dca.ha4321_vipgo.index.3.vip-3-post-2-2',
			),
		);
	}

	/**
	 * @dataProvider get_statsd_prefix_with_site_and_index_data
	 */
	public function test_get_statsd_prefix_with_site_and_index( $url, $mode, $app_id, $index_name, $expected ) {
		$prefix = $this->search_instance->get_statsd_prefix( $url, $mode, $app_id, $index_name );

		$this->assertEquals( $expected, $prefix );
	}

	/**
	 * Test formatted args structure checks
	 */
	public function test__vip_search_filter__ep_formatted_args() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertEquals( array( 'wrong' ), $es->filter__ep_formatted_args( array( 'wrong' ), '' ), 'didn\'t just return formatted args when the structure of formatted args didn\'t match what was expected' );

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

		$result = $es->filter__ep_formatted_args( $formatted_args, '' );

		$this->assertTrue( array_key_exists( 'must', $result['query']['bool'] ), 'didn\'t replace should with must' );
		$this->assertEquals( $result['query']['bool']['must'][0]['multi_match']['operator'], 'AND', 'didn\'t set the remainder of the query correctly' );
	}

	/**
	 * Ensure we disable indexing of filtered content by default
	 */
	public function test__vip_search_filter__ep_allow_post_content_filtered_index() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$enabled = apply_filters( 'ep_allow_post_content_filtered_index', true );

		$this->assertFalse( $enabled );
	}

	/*
	 * Ensure that is_query_integration_enabled() is false by default with no options/constants
	 */
	public function test__is_query_integration_enabled_default() {
		$this->assertFalse( \Automattic\VIP\Search\Search::is_query_integration_enabled() );
	}

	/*
	 * Ensure is_query_integration_enabled() option works properly with the vip_enable_vip_search_query_integration option
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_integration_enabled_via_option() {
		update_option( 'vip_enable_vip_search_query_integration', true );
	
		$this->assertTrue( \Automattic\VIP\Search\Search::is_query_integration_enabled() );

		delete_option( 'vip_enable_vip_search_query_integration' );
	}

	/*
	 * Ensure is_query_integration_enabled() properly considers VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_integration_enabled_via_legacy_constant() {
		define( 'VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION', true );

		$this->assertTrue( \Automattic\VIP\Search\Search::is_query_integration_enabled() );
	}

	/*
	 * Ensure is_query_integration_enabled() properly considers VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_integration_enabled_via_constant() {
		define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );

		$this->assertTrue( \Automattic\VIP\Search\Search::is_query_integration_enabled() );
	}

	/**
	 * Ensure query integration is enabled when the 'es' query param is set
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_integration_enabled_via_query_param() {
		// Set es query string to test override
		$_GET['es'] = true;

		$this->assertTrue( \Automattic\VIP\Search\Search::is_query_integration_enabled() );
	}

	public function test_is_network_mode_default() {
		$this->assertFalse( \Automattic\VIP\Search\Search::is_network_mode() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_network_mode_with_constant() {
		define( 'EP_IS_NETWORK', true );

		$this->assertTrue( \Automattic\VIP\Search\Search::is_network_mode() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_network_mode_with_constant_false() {
		define( 'EP_IS_NETWORK', false );

		$this->assertFalse( \Automattic\VIP\Search\Search::is_network_mode() );
	}

	/*
	 * Ensure that filters disabling query integration are honored
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_skip_query_integration_filter() {
		// Set constants to enable query integration
		define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );
		
		// We pass in `true` as the starting value for the filter, indicating it should be skipped. We expect that `true` comes back out,
		// even though query integration is enabled, which indicates that we're properly respecting other filters that have already decided
		// this query should be skipped
		$this->assertTrue( \Automattic\VIP\Search\Search::ep_skip_query_integration( true ) );
	}

	/*
	 * Ensure that EP query integration is disabled by default
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_skip_query_integration_default() {
		$this->assertTrue( \Automattic\VIP\Search\Search::ep_skip_query_integration( false ) );
	}

	/*
	 * Ensure ratelimiting works prioperly with ep_skip_query_integration filter
	 */
	public function test__rate_limit_ep_query_integration() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		add_option( 'vip_enable_vip_search_query_integration', true );
		define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );
		$_GET['es'] = true;

		$this->assertFalse( $es::rate_limit_ep_query_integration( false ), 'the default value should be false' );
		$this->assertTrue( $es::rate_limit_ep_query_integration( true ), 'should honor filters that skip query integrations' );

		// Force ratelimiting to apply
		$es::$max_query_count = 0;
		
		// Force this request to be ratelimited
		$es::$query_db_fallback_value = 11;

		// ep_skip_query_integration should be true if ratelimited
		$this->assertTrue( $es::rate_limit_ep_query_integration( false ), 'should return true if the query is ratelimited' );
	}

	/**
	 * Ensure we don't load es-wp-query by default (if it's not enabled)
	 */
	public function test__should_load_es_wp_query_default() {
		$should = \Automattic\VIP\Search\Search::should_load_es_wp_query();

		$this->assertFalse( $should );
	}

	/**
	 * Ensure we don't load es-wp-query if it is already loaded
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__should_load_es_wp_query_already_loaded() {
		// To cause should_load_es_wp_query() to otherwise return true
		define( 'VIP_ENABLE_SEARCH_QUERY_MIRRORING', true );

		require_once __DIR__ . '/../../search/es-wp-query/es-wp-query.php';

		$should = \Automattic\VIP\Search\Search::should_load_es_wp_query();

		$this->assertFalse( $should );
	}

	/**
	 * Ensure we do load es-wp-query when mirroring is enabled
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__should_load_es_wp_query_with_query_mirroring() {
		define( 'VIP_ENABLE_SEARCH_QUERY_MIRRORING', true );

		$should = \Automattic\VIP\Search\Search::should_load_es_wp_query();

		$this->assertTrue( $should );
	}

	/**
	 * Ensure we do load es-wp-query when query integration is enabled
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__should_load_es_wp_query_query_integration() {
		define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );

		$should = \Automattic\VIP\Search\Search::should_load_es_wp_query();

		$this->assertTrue( $should );
	}

	/**
	 * Ensure the incrementor for tracking request counts behaves properly
	 */
	public function test__query_count_incr() {
		$es = new \Automattic\VIP\Search\Search();
		$query_count_incr = self::get_method( 'query_count_incr' );

		// Reset cache key
		wp_cache_delete( $es::QUERY_COUNT_CACHE_KEY, $es::QUERY_COUNT_CACHE_GROUP );

		$this->assertEquals( 1, $query_count_incr->invokeArgs( $es, [] ), 'initial value should be 1' );

		for ( $i = 2; $i < 10; $i++ ) {
			$this->assertEquals( $i, $query_count_incr->invokeArgs( $es, [] ), 'value should increment with loop' );
		}
	}

	public function test__is_query_mirroring_enabled_no_constant_no_option() {
		$enabled = \Automattic\VIP\Search\Search::is_query_mirroring_enabled();

		$this->assertFalse( $enabled );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_mirroring_enabled_via_option() {
		update_option( 'vip_enable_search_query_mirroring', true );
		
		$enabled = \Automattic\VIP\Search\Search::is_query_mirroring_enabled();

		delete_option( 'vip_enable_search_query_mirroring' );

		$this->assertTrue( $enabled );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_mirroring_enabled_with_option_false() {
		update_option( 'vip_enable_search_query_mirroring', false );
		
		$enabled = \Automattic\VIP\Search\Search::is_query_mirroring_enabled();

		delete_option( 'vip_enable_search_query_mirroring' );

		$this->assertFalse( $enabled );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_mirroring_enabled_via_constant() {
		define( 'VIP_ENABLE_SEARCH_QUERY_MIRRORING', true );
		
		$enabled = \Automattic\VIP\Search\Search::is_query_mirroring_enabled();

		$this->assertTrue( $enabled );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_mirroring_enabled_with_constant_false() {
		define( 'VIP_ENABLE_SEARCH_QUERY_MIRRORING', false );
		
		$enabled = \Automattic\VIP\Search\Search::is_query_mirroring_enabled();

		$this->assertFalse( $enabled );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__should_mirror_wp_query_when_mirroring_disabled() {
		define( 'VIP_ENABLE_SEARCH_QUERY_MIRRORING', false );

		$es = new \Automattic\VIP\Search\Search();

		$query = new \WP_Query();

		$should_mirror = $es->should_mirror_wp_query( $query );

		$this->assertFalse( $should_mirror );
	}

	public function get_should_mirror_wp_query_when_query_already_offloaded_data() {
		return array(
			array(
				(object) array(
					'query_vars' => array(
						'es' => true,
					),
				),
			),

			array(
				(object) array(
					'query_vars' => array(
						'ep_integrate' => true,
					),
				),
			),
			
			array(
				(object) array(
					'query_vars' => array(
						'vip_search_mirrored' => true,
					),
				),
			),

			array(
				(object) array(
					'elasticsearch_succes' => true,
				),
			),
		);
	}

	/**
	 * @dataProvider get_should_mirror_wp_query_when_query_already_offloaded_data
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__should_mirror_wp_query_when_query_already_offloaded( $query ) {
		$es = new \Automattic\VIP\Search\Search();
		
		$should_mirror = $es->should_mirror_wp_query( $query );

		$this->assertFalse( $should_mirror );
	}

	/**
	 * Test the "the_posts" filter callback, which is responsible for mirroring WP_Query's under 
	 * certain limited circumstances
	 * 
	 * NOTE - due to PHPUnit's lack of support for partial mocking, we can't actually spy on the related
	 * function calls (to check if mirroring is enabled, then to do the mirroring if so), so we're just doing
	 * a very basic check that it's not altering the $posts array in any way
	 */
	public function test__filter_the_posts() {
		$es = new \Automattic\VIP\Search\Search();

		$posts = array();
		$query = new \stdClass();

		$filtered_posts = $es->filter__the_posts( $posts, $query );
	
		// Should not have altered the posts array
		$this->assertEquals( $posts, $filtered_posts );
	}

	public function test__queue_mirrored_wp_query() {
		$es = new \Automattic\VIP\Search\Search();

		$queue = self::get_property( 'mirrored_wp_query_queue' )->getValue( $es );

		$this->assertEmpty( $queue );

		$vars = array( 'foo' => 'bar' );

		$query = new \WP_Query( $vars );

		$es->queue_mirrored_wp_query( $query );

		$queue = self::get_property( 'mirrored_wp_query_queue' )->getValue( $es );

		$this->assertContains( $query, $queue );
	}

	public function test__get_mirrored_wp_query() {
		$es = new \Automattic\VIP\Search\Search();

		$vars = array( 'foo' => 'bar' );

		$query = new \WP_Query( $vars );

		$mirrored_query = $es->get_mirrored_wp_query( $query );

		// There's not really a better way in PHPUnit to assert the resulting array contains expected array/values...
		$this->assertEquals( 'bar', $mirrored_query->query_vars['foo'] );
		$this->assertEquals( true, $mirrored_query->query_vars['vip_search_mirrored'] );
	}

	public function get_diff_mirrored_wp_query_results_data() {
		return array(
			// No diff
			array(
				// Original
				array(
					array(
						'ID' => 1,
					),
					array(
						'ID' => 2,
					),
					array(
						'ID' => 3,
					),
				),

				// Mirrored
				array(
					array(
						'ID' => 1,
					),
					array(
						'ID' => 2,
					),
					array(
						'ID' => 3,
					),
				),

				// Expected diff
				null,
			),

			// Posts missing from mirrored
			array(
				// Original
				array(
					array(
						'ID' => 1,
					),
					array(
						'ID' => 2,
					),
					array(
						'ID' => 3,
					),
					array(
						'ID' => 4,
					),
				),

				// Mirrored
				array(
					array(
						'ID' => 1,
					),
					array(
						'ID' => 3,
					),
				),

				// Expected diff
				array(
					'missing' => array(
						2,
						4,
					),
					'extra' => array(),
				),
			),

			// Extra posts
			array(
				// Original
				array(
					array(
						'ID' => 1,
					),
					array(
						'ID' => 3,
					),
				),

				// Mirrored
				array(
					array(
						'ID' => 1,
					),
					array(
						'ID' => 2,
					),
					array(
						'ID' => 3,
					),
					array(
						'ID' => 4,
					),
				),

				// Expected diff
				array(
					'missing' => array(),
					'extra' => array(
						2,
						4,
					),
				),
			),

			// Non-array input
			array(
				// Original
				null,

				// Mirrored
				array(
					array(
						'ID' => 1,
					),
				),

				// Expected diff
				array(
					'missing' => array(),
					'extra' => array(
						1,
					),
				),
			),
			
			// Non-array input
			array(
				// Original
				array(
					array(
						'ID' => 1,
					),
				),

				// Mirrored
				null,

				// Expected diff
				array(
					'missing' => array(
						1,
					),
					'extra' => array(),
				),
			),
		);
	}

	/**
	 * @dataProvider get_diff_mirrored_wp_query_results_data
	 */
	public function test__diff_mirrored_wp_query_results( $original_posts, $mirrored_posts, $expected_diff ) {
		$es = new \Automattic\VIP\Search\Search();
		
		$diff = $es->diff_mirrored_wp_query_results( $original_posts, $mirrored_posts );

		$this->assertEquals( $expected_diff, $diff );
	}

	public function test__truncate_search_string_length() {
		$es = new \Automattic\VIP\Search\Search();

		$expected_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpgg';
		$provided_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpgg' .
			'g5oMk1nWsx5fJ0B3bAFYKt1Y5dOA0Q4lQUqj8mf1LjcmR73wQwujc1GQfgCKj9X9Ktr6LrDtN5zAJFQboAJa7fZ9AiGxbJqUrLFs';

		$wp_query_mock = new \WP_Query();

		$wp_query_mock->set( 's', $provided_search_string );
		$wp_query_mock->is_search = true;

		$es->truncate_search_string_length( $wp_query_mock );

		$this->assertEquals( $expected_search_string, $wp_query_mock->get( 's' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__limit_field_limit_absolute_maximum_is_20000() {
		// Don't trigger an error since it's expected
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false', PHP_INT_MAX );

		$es = new \Automattic\VIP\Search\Search();

		$this->assertEquals( 20000, $es->limit_field_limit( 1000000 ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__limit_field_limit_should_respect_values_under_maximum() {
		$es = new \Automattic\VIP\Search\Search();

		$this->assertEquals( 777, $es->limit_field_limit( 777 ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_total_field_limit_should_limit_total_fields() {
		// Don't trigger an error since it's expected
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false', PHP_INT_MAX );
	
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		\add_filter(
			'ep_total_field_limit',
			function() {
				return 1000000;
			}
		);

		$this->assertEquals( 20000, apply_filters( 'ep_total_field_limit', 5000 ) ); 
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_total_field_limit_should_respect_values_under_the_limit() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		\add_filter(
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
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$post = new \stdClass();

		$filtered_taxonomies = apply_filters( 'ep_sync_taxonomies', $input_taxonomies, $post );

		$input_taxonomy_names = wp_list_pluck( $input_taxonomies, 'name' );
		$filtered_taxonomy_names = wp_list_pluck( $filtered_taxonomies, 'name' );

		// No change expected
		$this->assertEquals( $input_taxonomy_names, $filtered_taxonomy_names );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_sync_taxonomies_added() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$post = new \stdClass();

		$start_taxonomies = array(
			(object) array(
				'name' => 'category',
			),
		);

		\add_filter(
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

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_sync_taxonomies_removed() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$post = new \stdClass();

		$start_taxonomies = array(
			(object) array(
				'name' => 'category',
			),
			(object) array(
				'name' => 'post_tag',
			),
		);

		\add_filter(
			'vip_search_post_taxonomies_allow_list',
			function( $taxonomies ) {
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
	
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_prepare_meta_data_allow_list_should_be_respected_by_default() {
		$es = new \Automattic\VIP\Search\Search();

		\add_filter(
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
			'another_one' => array(
				'4656784',
			),
			'third' => array(
				'true',
			),
		);

		$post_meta['random_thing_not_allow_listed'] = array( 'Missing' );

		$post = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		$meta = $es->filter__ep_prepare_meta_data( $post_meta, $post );

		unset( $post_meta['random_thing_not_allow_listed'] ); // Remove last added value that should have been excluded by the filter

		$this->assertEquals( $meta, $post_meta );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_prepare_meta_data_allow_list_should_be_respected_by_default_assoc() {
		$es = new \Automattic\VIP\Search\Search();

		\add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_post_meta' => true,
					'another_one' => true,
					'skipped' => false,
					'skipped_another' => 4,
					'skipped_string' => 'Wooo',
					'third' => true,
				);
			}
		);

		// Matches allow list
		$post_meta = array(
			'random_post_meta' => array(
				'Random value',
			),
			'another_one' => array(
				'4656784',
			),
			'skipped' => array(
				'Skip',
			),
			'skipped_another' => array(
				'Skip',
			),
			'skipped_string' => array(
				'Skip',
			),
			'third' => array(
				'true',
			),
		);

		$post_meta['random_thing_not_allow_listed'] = array( 'Missing' );

		$post = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		$meta = $es->filter__ep_prepare_meta_data( $post_meta, $post );

		$this->assertEquals(
			$meta,
			array(
				'random_post_meta' => array(
					'Random value',
				),
				'another_one' => array(
					'4656784',
				),
				'third' => array(
					'true',
				),
			)
		);
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\Search' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Helper function for accessing protected properties.
	 */
	protected static function get_property( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\Search' );

		$property = $class->getProperty( $name );
		$property->setAccessible( true );

		return $property;
	}
}
