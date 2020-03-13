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

		$timeout = $es->get_http_timeout_for_query( $query );

		$this->assertEquals( $expected_timeout, $timeout );
	}

	/**
	 * Test that we are setting up the filter to auto-disable JP Search
	 */
	public function test__vip_search_has_jp_search_module_filter() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertEquals( true, has_filter( 'jetpack_active_modules', [ $es, 'filter__jetpack_active_modules' ] ) );
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

	/*
	 * Test for making sure the round robin function returns the next array value
	 */
	public function test__vip_search_get_next_host() {
		$es = new \Automattic\VIP\Search\Search();
		$hosts = array(
			'test0',
			'test1',
			'test2', 
			'test3',
		);

		$this->assertEquals( 'test0', $es->get_next_host( $hosts, 0 ), 'get_next_host() didn\'t use the same host with 0 total failures and 4 hosts with a starting index of 0' );
		$this->assertEquals( 'test1', $es->get_next_host( $hosts, 1 ), 'get_next_host() didn\'t get the correct host with 1 total failures and 4 hosts with a starting index of 0' );
		$this->assertEquals( 'test0', $es->get_next_host( $hosts, 3 ), 'get_next_host() didn\'t restart at the beginning of the list upon reaching the end with 4 total failures and 4 hosts with a starting index of 1' );
		$this->assertEquals( 'test1', $es->get_next_host( $hosts, 17 ), 'get_next_host() didn\'t match expected result with 21 total failures and 4 hosts. and a starting index of 0' );

		array_push( $hosts, 'test4', 'test5', 'test6' ); // Add some array values

		$this->assertEquals( 'test5', $es->get_next_host( $hosts, 4 ), 'get_next_host() didn\'t get the same host with 25 total failures and 7 hosts with starting index of 1.' );
		$this->assertEquals( 'test6', $es->get_next_host( $hosts, 1 ), 'get_next_host() didn\'t get the next host with 26 total failure and 7 hosts with starting index of 5' );
		$this->assertEquals( 'test3', $es->get_next_host( $hosts, 4 ), 'get_next_host() didn\'t get the correct host with 30 total failures and 7 hosts with a starting index of 6' );
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

}
