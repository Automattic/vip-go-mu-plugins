<?php

namespace Automattic\VIP\Search;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/mock-header.php';
require_once __DIR__ . '/../../../../search/search.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-versioning.php';
require_once __DIR__ . '/../../../../search/elasticpress/elasticpress.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Search_Test extends WP_UnitTestCase {
	use ExpectPHPException;

	public static $mock_global_functions;
	public $test_index_name = 'vip-1234-post-0-v3';

	public function setUp(): void {
		parent::setUp();
		$this->search_instance = new \Automattic\VIP\Search\Search();

		self::$mock_global_functions = $this->getMockBuilder( self::class )
			->setMethods( [ 'mock_vip_safe_wp_remote_request', 'mock_wp_remote_request' ] )
			->getMock();

		header_remove();
	}

	public function test_query_es_with_invalid_type() {
		$this->init_es();

		$result = $this->search_instance->query_es( 'foo' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'indexable-not-found', $result->get_error_code() );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Search
	 */
	public function test__vip_search_filter_ep_index_name() {
		$this->init_es();

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$index_name = apply_filters( 'ep_index_name', 'index-name', 1, $indexable );

		$this->assertEquals( 'vip-123-post-1', $index_name );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Search for global indexes
	 *
	 * On "global" indexes, such as users, no blog id will be present
	 */
	public function test__vip_search_filter_ep_index_name_global_index() {
		$this->init_es();

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $indexable );

		$this->assertEquals( 'vip-123-post', $index_name );
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

	public function vip_search_is_url_query_cacheable_data() {
		return array(
			// Regular search
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/_search',
				),
				// The expected result
				true,
			),
			// Regular multiget
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/_mget',
				),
				// The expected result
				true,
			),
			// Regular entity multiget
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_doc/_mget',
				),
				// The expected result
				true,
			),
			// Bulk index
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/_bulk',
				),
				// The expected result
				false,
			),
			// Url containing _bulk
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/_bulk/bar?_mget',
				),
				// The expected result
				false,
			),
			// Random other url
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_anything',
				),
				// The expected result
				false,
			),
		);
	}

	/**
	 * Test that we correctly calculate the HTTP request timeout value for ES requests
	 *
	 * @dataProvider vip_search_is_url_query_cacheable_data()
	 */
	public function test__is_url_query_cacheable( $query, $expected_is_cacheable ) {
		$is_cacheable = $this->search_instance->is_url_query_cacheable( $query['url'], array() );

		$this->assertEquals( $expected_is_cacheable, $is_cacheable );
	}

	/**
	 * Test `ep_index_name` filter with versioning
	 *
	 * When current version is 1, the index name should not have a version applied to it
	 *
	 * @dataProvider vip_search_filter_ep_index_name_with_versions_data
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_search_filter_ep_index_name_with_versions( $current_version, $blog_id, $expected_index_name ) {
		$this->init_es();

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		// Mock the Versioning class so we can control which version it returns
		$stub = $this->getMockBuilder( \Automattic\VIP\Search\Versioning::class )
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

	public function test__vip_search_filter_ep_index_name_with_overridden_version() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'dfw' );
		$this->init_es();

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$new_version = $this->search_instance->versioning->add_version( $indexable );

		$this->assertNotFalse( $new_version, 'Failed to add new version of index' );
		$this->assertNotInstanceOf( \WP_Error::class, $new_version, 'Got WP_Error when adding new index version' );

		// Override the version
		$override_result = $this->search_instance->versioning->set_current_version_number( $indexable, 2 );

		$this->assertTrue( $override_result, 'Setting current version number failed' );

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $indexable );

		$this->assertEquals( 'vip-123-post-v2', $index_name, 'Overridden index name is not correct' );

		// Reset
		$this->search_instance->versioning->reset_current_version_number( $indexable );

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $indexable );

		$this->assertEquals( 'vip-123-post', $index_name );

		delete_option( Versioning::INDEX_VERSIONS_OPTION );
	}

	public function test__vip_search_filter__ep_global_alias() {
		$this->init_es();

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$alias_name = $indexable->get_network_alias();

		$this->assertEquals( 'vip-123-post-all', $alias_name );
	}

	public function test__vip_search_filter_ep_default_index_number_of_shards() {
		$this->init_es();

		$shards = apply_filters( 'ep_default_index_number_of_shards', 5 );

		$this->assertEquals( 1, $shards );
	}

	public function test__vip_search_filter_filter__ep_post_mapping__large_site() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'foo' );
		Constant_Mocker::define( 'VIP_GO_ENV', 'production' );
		$this->init_es();

		// Simulate a large site
		$return_big_count = function( $counts ) {
			$counts->publish = 2000000;

			return $counts;
		};

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		add_filter( 'wp_count_posts', $return_big_count );

		$settings = $indexable->build_settings();

		$this->assertEquals( 4, $settings['index.number_of_shards'] );

		remove_filter( 'wp_count_posts', $return_big_count );
	}

	public function test__vip_search_filter_filter__ep_user_mapping__large_site() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'foo' );
		Constant_Mocker::define( 'VIP_GO_ENV', 'production' );
		$this->init_es();

		// Activate and set-up the feature
		\ElasticPress\Features::factory()->activate_feature( 'users' );
		\ElasticPress\Features::factory()->setup_features();

		// Simulate a large site
		$return_big_count = function( $counts ) {
			$counts              = new \stdClass();
			$counts->avail_roles = 100;
			$counts->total_users = 3000000;

			return $counts;
		};

		add_filter( 'pre_count_users', $return_big_count );

		$indexable = \ElasticPress\Indexables::factory()->get( 'user' );
		$settings  = $indexable->build_settings();
		$this->assertEquals( 4, $settings['index.number_of_shards'] );

		remove_filter( 'pre_count_users', $return_big_count );
	}

	public function test__vip_search_filter_ep_default_index_number_of_replicas() {
		$this->init_es();

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
		$this->init_es();

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
		$this->init_es();

		$this->assertEquals( Constant_Mocker::constant( 'EP_SYNC_CHUNK_LIMIT' ), 500 );
	}

	/**
	 * Test that the default bulk index chunk size limit is not applied if constant is already defined
	 */
	public function test__vip_search_bulk_chunk_size_already_defined() {
		Constant_Mocker::define( 'EP_SYNC_CHUNK_LIMIT', 500 );

		$this->init_es();

		$this->assertEquals( Constant_Mocker::constant( 'EP_SYNC_CHUNK_LIMIT' ), 500 );
	}

	/**
	 * Test that the default bulk index chunk size limit is not defined if we're not using VIP Search
	 */
	public function test__vip_search_bulk_chunk_size_not_defined_when_not_using_vip_search() {
		$this->markTestSkipped( 'Revisit this test' );
		$this->assertEquals( defined( 'EP_SYNC_CHUNK_LIMIT' ), false );
	}

	/**
	 * Test that the ES config constants are set automatically when not already defined and VIP-provided configs are present
	 */
	public function test__vip_search_connection_constants() {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
			'https://es-endpoint1',
			'https://es-endpoint2',
		) );

		Constant_Mocker::define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		$this->init_es();

		$this->assertContains( Constant_Mocker::constant( 'EP_HOST' ), Constant_Mocker::constant( 'VIP_ELASTICSEARCH_ENDPOINTS' ) );
		$this->assertEquals( Constant_Mocker::constant( 'ES_SHIELD' ), 'foo:bar' );
	}

	/**
	 * Test that the ES config constants are _not_ set automatically when already defined and VIP-provided configs are present
	 *
	 */
	public function test__vip_search_connection_constants_with_overrides() {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
			'https://es-endpoint1',
			'https://es-endpoint2',
		) );

		Constant_Mocker::define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		// Client over-rides - don't fatal
		Constant_Mocker::define( 'EP_HOST', 'https://somethingelse' );
		Constant_Mocker::define( 'ES_SHIELD', 'bar:baz' );

		$this->init_es();

		$this->assertEquals( Constant_Mocker::constant( 'EP_HOST' ), 'https://somethingelse' );
		$this->assertEquals( Constant_Mocker::constant( 'ES_SHIELD' ), 'bar:baz' );
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
		$this->assertEquals( false, Constant_Mocker::defined( 'WP_EP_DEBUG' ), 'WP_EP_DEBUG constant already defined, therefore this test is not asserting that the constant is set at runtime' );

		$this->init_es();

		do_action( 'plugins_loaded' );

		// Class should now exist
		$this->assertEquals( true, function_exists( 'ep_add_debug_bar_panel' ), 'EP Debug Bar was not found' );

		// And the debug constant should have been set (required for saving queries)
		$this->assertEquals( true, Constant_Mocker::constant( 'WP_EP_DEBUG' ), 'Incorrect value for WP_EP_DEBUG constant' );
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

		$this->init_es();

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

		$this->init_es();

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

		$this->init_es();

		// Class should not exist
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ) );
	}

	/**
	 * Test that we are sending HTTP requests through the VIP helper functions
	 */
	public function test__vip_search_has_http_layer_filters() {
		$this->init_es();

		$this->assertEquals( true, has_filter( 'ep_intercept_remote_request', '__return_true' ) );
		$this->assertEquals( true, has_filter( 'ep_do_intercept_request', [ $this->search_instance, 'filter__ep_do_intercept_request' ] ) );
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
		Constant_Mocker::define( 'EP_DASHBOARD_SYNC', 'test' );

		$timeout = $this->search_instance->get_http_timeout_for_query( $query, array() );

		$this->assertEquals( $expected_timeout, $timeout );
	}

	/**
	 * Test that instantiating the HealthJob works as expected (files are properly included, init is hooked)
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_search_setup_healthchecks_with_enabled() {
		// Need to filter to enable the HealthJob
		add_filter( 'enable_vip_search_healthchecks', '__return_true' );

		$this->init_es();

		$this->search_instance->setup_cron_jobs();
		// Should not have fataled (class was included)

		// Ensure it returns the priority set. Easiest way to to ensure it's not false
		$this->assertTrue( false !== has_action( 'wp_loaded', [ $this->search_instance->healthcheck, 'init' ] ) );
	}

	/**
	 * Test that instantiating the HealthJob does not happen when not in production
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_search_setup_healthchecks_disabled_in_non_production_env() {
		Constant_Mocker::define( 'VIP_GO_ENV', '999' );
		$this->init_es();

		$this->search_instance->setup_cron_jobs();

		// Should not have fataled (class was included)

		// Should not have instantiated and registered the init action to setup the health check
		$this->assertEquals( false, $this->search_instance->healthcheck->is_enabled() );
	}

	/**
	 * Test that checks both single and multi-host retries
	 */
	public function test__vip_search_filter__ep_pre_request_host() {
		$this->init_es();

		// If VIP_ELASTICSEARCH_ENDPOINTS is not defined, just hand the last host back
		$this->assertEquals( 'test', $this->search_instance->filter__ep_pre_request_host( 'test', 0 ), 'filter__ep_pre_request_host() did\'t just hand the last host back when VIP_ELASTICSEARCH_ENDPOINTS was undefined' );

		Constant_Mocker::define(
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

		$this->assertContains( $this->search_instance->filter__ep_pre_request_host( 'endpoint1', 0 ), Constant_Mocker::constant( 'VIP_ELASTICSEARCH_ENDPOINTS' ), 'filter__ep_pre_request_host() didn\'t return a value that exists in VIP_ELASTICSEARCH_ENDPOINTS with 0 total failures' );
		$this->assertContains( $this->search_instance->filter__ep_pre_request_host( 'endpoint1', 107 ), Constant_Mocker::constant( 'VIP_ELASTICSEARCH_ENDPOINTS' ), 'filter__ep_pre_request_host() didn\'t return a value that exists in VIP_ELASTICSEARCH_ENDPOINTS with 107 failures' );
	}

	/*
	 * Test for making sure filter__ep_pre_request_host handles empty endpoint lists
	 */
	public function test__vip_search_filter__ep_pre_request_host_empty_endpoint() {
		$this->init_es();

		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', array() );

		$this->assertEquals( 'test', $this->search_instance->filter__ep_pre_request_host( 'test', 0 ) );
	}

	/*
	 * Test for making sure filter__ep_pre_request_host handles endpoint lists that aren't arrays
	 */
	public function test__vip_search_filter__ep_pre_request_host_endpoint_not_array() {
		$this->init_es();

		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', 'Random string' );

		$this->assertEquals( 'test', $this->search_instance->filter__ep_pre_request_host( 'test', 0 ) );
	}

	/**
	 * Ensure that we're allowing querying during bulk re-index, via the ep_enable_query_integration_during_indexing filter
	 */
	public function test__vip_search_filter__ep_enable_query_integration_during_indexing() {
		$this->init_es();

		$allowed = apply_filters( 'ep_enable_query_integration_during_indexing', false );

		$this->assertTrue( $allowed );
	}

	/*
	 * Test for making sure the round robin function returns the next array value
	 */
	public function test__vip_search_get_next_host() {
		$es = new \Automattic\VIP\Search\Search();
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS',
			array(
				'test0',
				'test1',
				'test2',
				'test3',
			)
		);

		$this->assertEquals( 'test0', $this->search_instance->get_next_host( 0 ), 'get_next_host() didn\'t use the same host with 0 total failures and 4 hosts with a starting index of 0' );
		$this->assertEquals( 'test1', $this->search_instance->get_next_host( 1 ), 'get_next_host() didn\'t get the correct host with 1 total failures and 4 hosts with a starting index of 0' );
		$this->assertEquals( 'test0', $this->search_instance->get_next_host( 3 ), 'get_next_host() didn\'t restart at the beginning of the list upon reaching the end with 4 total failures and 4 hosts with a starting index of 1' );
		$this->assertEquals( 'test1', $this->search_instance->get_next_host( 17 ), 'get_next_host() didn\'t match expected result with 21 total failures and 4 hosts. and a starting index of 0' );
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
		$es    = new \Automattic\VIP\Search\Search();

		$this->assertContains( $this->search_instance->get_random_host( $hosts ), $hosts );
	}

	public function test__send_vary_headers__sent_for_group() {
		$this->init_es();
		$_GET['ep_debug'] = true;

		apply_filters( 'ep_valid_response', array(), array(), array(), array(), null );

		do_action( 'send_headers' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['ep_debug'] );

		$headers = headers_list();
		$this->assertContains( 'X-ElasticPress-Search-Valid-Response: true', $headers, '', true );
	}

	public function test__vip_search_filter__ep_facet_taxonomies_size() {
		$this->init_es();

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
		$this->init_es();

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
		$this->init_es();

		$result = $this->search_instance->filter__jetpack_widgets_to_include( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that the track_total_hits arg exists
	 */
	public function test__vip_filter__ep_post_formatted_args() {
		$this->init_es();

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
		$this->search_instance->init();
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
		$this->init_es();

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
		$this->init_es();

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
		Constant_Mocker::define( 'VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION', true );

		$this->assertTrue( \Automattic\VIP\Search\Search::is_query_integration_enabled() );
	}

	/*
	 * Ensure is_query_integration_enabled() properly considers VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_query_integration_enabled_via_constant() {
		Constant_Mocker::define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );

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
		$_GET[ \Automattic\VIP\Search\Search::QUERY_INTEGRATION_FORCE_ENABLE_KEY ] = true;

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
		Constant_Mocker::define( 'EP_IS_NETWORK', true );

		$this->assertTrue( \Automattic\VIP\Search\Search::is_network_mode() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_network_mode_with_constant_false() {
		Constant_Mocker::define( 'EP_IS_NETWORK', false );

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
		Constant_Mocker::define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );

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
	 * Ensure ratelimiting works properly with ep_skip_query_integration filter
	 */
	public function test__rate_limit_ep_query_integration__trigers() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		add_option( 'vip_enable_vip_search_query_integration', true );
		Constant_Mocker::define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );
		$_GET[ \Automattic\VIP\Search\Search::QUERY_INTEGRATION_FORCE_ENABLE_KEY ] = true;

		$this->assertFalse( $es->rate_limit_ep_query_integration( false ), 'the default value should be false' );
		$this->assertTrue( $es->rate_limit_ep_query_integration( true ), 'should honor filters that skip query integrations' );

		// Force ratelimiting to apply
		$es::$max_query_count = 0;

		// Force this request to be ratelimited
		$es::$query_db_fallback_value = 11;

		// ep_skip_query_integration should be true if ratelimited
		$this->assertTrue( $es->rate_limit_ep_query_integration( false ), 'should return true if the query is ratelimited' );
	}

	public function test__rate_limit_ep_query_integration__handles_start_correctly() {
		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'handle_query_limiting_start_timestamp', 'maybe_alert_for_prolonged_query_limiting' ] )
			->getMock();
		$partially_mocked_search->init();

		// Force ratelimiting to apply
		$partially_mocked_search::$max_query_count = 0;

		// Force this request to be ratelimited
		$partially_mocked_search::$query_db_fallback_value = 11;

		$partially_mocked_search->expects( $this->once() )->method( 'handle_query_limiting_start_timestamp' );
		$partially_mocked_search->expects( $this->once() )->method( 'maybe_alert_for_prolonged_query_limiting' );

		$partially_mocked_search->rate_limit_ep_query_integration( false );
	}

	public function test__rate_limit_ep_query_integration__clears_start_correctly() {
		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'clear_query_limiting_start_timestamp' ] )
			->getMock();
		$partially_mocked_search->init();

		$partially_mocked_search->expects( $this->once() )->method( 'clear_query_limiting_start_timestamp' );

		$partially_mocked_search->rate_limit_ep_query_integration( false );
	}

	public function test__record_ratelimited_query_stat__records_statsd() {
		$stats_key = 'foo';

		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'get_statsd_prefix', 'maybe_increment_stat' ] )
			->getMock();
		$partially_mocked_search->init();

		$indexables_mock = $this->createMock( \ElasticPress\Indexables::class );

		$partially_mocked_search->indexables = $indexables_mock;

		$indexables_mock->method( 'get' )
			->willReturn( $this->createMock( \ElasticPress\Indexable::class ) );

		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_key );

		$partially_mocked_search->expects( $this->once() )
			->method( 'maybe_increment_stat' )
			->with( "$stats_key" );

		$partially_mocked_search->record_ratelimited_query_stat();
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
		require_once __DIR__ . '/../../../../search/es-wp-query/es-wp-query.php';

		$this->setExpectedIncorrectUsage( 'Automattic\VIP\Search\Search::should_load_es_wp_query' );

		$should = \Automattic\VIP\Search\Search::should_load_es_wp_query();

		$this->assertFalse( $should );
	}

	/**
	 * Ensure we do load es-wp-query when query integration is enabled
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__should_load_es_wp_query_query_integration() {
		Constant_Mocker::define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );

		$should = \Automattic\VIP\Search\Search::should_load_es_wp_query();

		$this->assertTrue( $should );
	}

	/**
	 * Ensure the incrementor for tracking request counts behaves properly
	 */
	public function test__query_count_incr() {
		$query_count_incr = self::get_method( 'query_count_incr' );

		// Reset cache key
		wp_cache_delete( $this->search_instance::QUERY_COUNT_CACHE_KEY, $this->search_instance::SEARCH_CACHE_GROUP );

		$this->assertEquals( 1, $query_count_incr->invokeArgs( $this->search_instance, [] ), 'initial value should be 1' );

		for ( $i = 2; $i < 10; $i++ ) {
			$this->assertEquals( $i, $query_count_incr->invokeArgs( $this->search_instance, [] ), 'value should increment with loop' );
		}
	}

	public function test__truncate_search_string_length() {
		$es = new \Automattic\VIP\Search\Search();

		$expected_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpgg';
		$provided_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpgg' .
			'g5oMk1nWsx5fJ0B3bAFYKt1Y5dOA0Q4lQUqj8mf1LjcmR73wQwujc1GQfgCKj9X9Ktr6LrDtN5zAJFQboAJa7fZ9AiGxbJqUrLFs';

		$wp_query_mock = new \WP_Query();

		$wp_query_mock->set( 's', $provided_search_string );
		$wp_query_mock->is_search = true;

		$this->search_instance->truncate_search_string_length( $wp_query_mock );

		$this->assertEquals( $expected_search_string, $wp_query_mock->get( 's' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__limit_field_limit_absolute_maximum_is_20000() {
		$this->setExpectedIncorrectUsage( 'limit_field_limit' );

		$es = new \Automattic\VIP\Search\Search();

		$this->assertEquals( 20000, $this->search_instance->limit_field_limit( 1000000 ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__limit_field_limit_should_respect_values_under_maximum() {
		$es = new \Automattic\VIP\Search\Search();

		$this->assertEquals( 777, $this->search_instance->limit_field_limit( 777 ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_total_field_limit_should_limit_total_fields() {
		$this->setExpectedIncorrectUsage( 'limit_field_limit' );

		$this->init_es();

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
		$this->init_es();

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
		$this->init_es();

		$post = new \stdClass();

		$filtered_taxonomies = apply_filters( 'ep_sync_taxonomies', $input_taxonomies, $post );

		$input_taxonomy_names    = wp_list_pluck( $input_taxonomies, 'name' );
		$filtered_taxonomy_names = wp_list_pluck( $filtered_taxonomies, 'name' );

		// No change expected
		$this->assertEquals( $input_taxonomy_names, $filtered_taxonomy_names );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_sync_taxonomies_added() {
		$this->init_es();

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
		$this->init_es();

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

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_migration() {
		Constant_Mocker::define( 'VIP_SEARCH_MIGRATION_SOURCE', 'jetpack' );

		$this->assertTrue( $this->search_instance->is_jetpack_migration() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_migration__no_constant() {
		$this->assertFalse( $this->search_instance->is_jetpack_migration() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_migration__different_value() {
		Constant_Mocker::define( 'VIP_SEARCH_MIGRATION_SOURCE', 'foo' );

		$this->assertFalse( $this->search_instance->is_jetpack_migration() );
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

		$meta = $es->filter__ep_prepare_meta_data( $post_meta, $post );

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

	/**
	 * This tests the correct implementaton of the ep_$indexable_mapping filters, but note that these filters
	 * operate on the mapping and settings together - EP doesn't yet distinguish between them
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_indexable_mapping() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'dfw' );
		$this->init_es();

		// Should apply to all indexables
		$indexables = \ElasticPress\Indexables::factory()->get_all();

		// Make sure the above worked
		$this->assertNotEmpty( $indexables, 'Indexables array was empty' );

		foreach ( $indexables as $indexable ) {
			$settings = $indexable->build_settings();

			$this->assertEquals( 'dfw', $settings['index.routing.allocation.include.dc'], 'Indexable ' . $indexable->slug . ' has the wrong routing allocation' );
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_indexable_mapping_invalid_datacenter() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'foo' );
		$this->init_es();

		// Should apply to all indexables
		$indexables = \ElasticPress\Indexables::factory()->get_all();

		// Make sure the above worked
		$this->assertNotEmpty( $indexables, 'Indexables array was empty' );

		foreach ( $indexables as $indexable ) {
			$settings = $indexable->build_settings();

			// Datacenter was invalid, so it should not have added the allocation settings
			$this->assertArrayNotHasKey( 'index.routing.allocation.include.dc', $settings, 'Indexable ' . $indexable->slug . ' incorrectly defined the allocation settings' );
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__get_index_routing_allocation_include_dc_from_constant() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'dca' );
		$this->init_es();

		$origin_dc = $this->search_instance->get_index_routing_allocation_include_dc();

		$this->assertEquals( 'dca', $origin_dc );
	}

	public function get_index_routing_allocation_include_dc_from_endpoints_data() {
		return array(
			// Valid
			array(
				// Endpoints to define in VIP_ELASTICSEARCH_ENDPOINTS
				array(
					'https://es-ha.dfw.vipv2.net:1234',
				),
				// Expected datacenter
				'dfw',
			),
			array(
				// Endpoints to define in VIP_ELASTICSEARCH_ENDPOINTS
				array(
					'https://es-ha.bur.vipv2.net/some/path',
				),
				// Expected datacenter
				'bur',
			),
			// Unknown dc
			array(
				// Endpoints to define in VIP_ELASTICSEARCH_ENDPOINTS
				array(
					'https://es-ha.bar.vipv2.net:1234',
				),
				// Expected datacenter
				null,
			),
			// Weird format
			array(
				// Endpoints to define in VIP_ELASTICSEARCH_ENDPOINTS
				array(
					'https://test:test@foo.com/bar/baz',
				),
				// Expected datacenter
				null,
			),
		);
	}

	/**
	 * @dataProvider get_index_routing_allocation_include_dc_from_endpoints_data
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__get_index_routing_allocation_include_dc_from_endpoints( $endpoints, $expected ) {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', $endpoints );
		Constant_Mocker::define( 'EP_DASHBOARD_SYNC', 'test' );
		$this->search_instance->init();

		$origin_dc = $this->search_instance->get_index_routing_allocation_include_dc();

		$this->assertEquals( $expected, $origin_dc );
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
		Constant_Mocker::define( 'EP_DASHBOARD_SYNC', 'test' );
		$this->search_instance->init();

		$origin_dc = $this->search_instance->get_origin_dc_from_es_endpoint( $host );

		$this->assertEquals( $expected, $origin_dc );
	}

	public function get_post_meta_allow_list__combinations_for_jetpack_migration_data() {
		return [
			[
				null, // VIP search
				null, // Jetpack filter added
				array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, Search::JETPACK_POST_META_DEFAULT_ALLOW_LIST ), // expected
			],
			[
				[ 'foo' ], // VIP search
				null, // Jetpack filter added
				array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, Search::JETPACK_POST_META_DEFAULT_ALLOW_LIST, [ 'foo' ] ), // expected
			],
			[
				// keys provided by VIP and JP filters
				[ 'foo' ], // VIP search
				[ 'bar' ], // Jetpack filter added
				array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, Search::JETPACK_POST_META_DEFAULT_ALLOW_LIST, [ 'bar', 'foo' ] ), // expected
			],
			[
				// keys from empty VIP filter, JP filter
				[], // VIP search
				[ 'bar' ], // Jetpack filter added
				array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, Search::JETPACK_POST_META_DEFAULT_ALLOW_LIST, [ 'bar' ] ), // expected
			],
			[
				// No VIP filter, JP filter
				null, // VIP search
				[ 'bar' ], // Jetpack filter added
				array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, Search::JETPACK_POST_META_DEFAULT_ALLOW_LIST, [ 'bar' ] ), // expected
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_post_meta_allow_list__combinations_for_jetpack_migration_data
	 */
	public function test__get_post_meta_allow_list__combinations_for_jetpack_migration( $vip_search_keys, $jetpack_added, $expected ) {
		Constant_Mocker::define( 'VIP_SEARCH_MIGRATION_SOURCE', 'jetpack' );

		remove_all_filters( 'vip_search_post_meta_allow_list' );
		remove_all_filters( 'jetpack_sync_post_meta_whitelist' );
		$this->init_es();

		$post     = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		if ( is_array( $vip_search_keys ) ) {
			\add_filter( 'vip_search_post_meta_allow_list', function ( $post_meta ) use ( $vip_search_keys ) {
				return array_merge( $post_meta, $vip_search_keys );
			});
		}

		if ( is_array( $jetpack_added ) ) {
			\add_filter( 'jetpack_sync_post_meta_whitelist', function ( $post_meta ) use ( $jetpack_added ) {
				return array_merge( $post_meta, $jetpack_added );
			});
		}

		$result = $this->search_instance->get_post_meta_allow_list( $post );

		$this->assertEquals( $expected, $result );
	}

	public function get_post_meta_allow_list__combinations_not_jetpack_migration_data() {
		return [
			[
				null, // VIP search
				null, // Jetpack filter added
				Search::POST_META_DEFAULT_ALLOW_LIST, // expected
			],
			[
				[ 'foo' ], // VIP search
				null, // Jetpack filter added
				array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, [ 'foo' ] ), // expected
			],
			[
				// keys provided by VIP and JP filters
				[ 'foo' ], // VIP search
				[ 'bar' ], // Jetpack filter added
				array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, [ 'foo' ] ), // expected
			],
			[
				// keys from empty VIP filter, JP filter
				[], // VIP search
				[ 'bar' ], // Jetpack filter added
				Search::POST_META_DEFAULT_ALLOW_LIST, // expected
			],
			[
				// No VIP filter, JP filter
				null, // VIP search
				[ 'bar' ], // Jetpack filter added
				Search::POST_META_DEFAULT_ALLOW_LIST, // expected
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_post_meta_allow_list__combinations_not_jetpack_migration_data
	 */
	public function test__get_post_meta_allow_list__combinations_not_jetpack_migration( $vip_search_keys, $jetpack_added, $expected ) {
		$this->init_es();

		$post     = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		if ( is_array( $vip_search_keys ) ) {
			\add_filter( 'vip_search_post_meta_allow_list', function ( $post_meta ) use ( $vip_search_keys ) {
				return array_merge( $post_meta, $vip_search_keys );
			});
		}

		if ( is_array( $jetpack_added ) ) {
			\add_filter( 'jetpack_sync_post_meta_whitelist', function ( $post_meta ) use ( $jetpack_added ) {
				return array_merge( $post_meta, $jetpack_added );
			});
		}

		$result = $this->search_instance->get_post_meta_allow_list( $post );

		$this->assertEquals( $expected, $result );
	}

	public function get_post_meta_allow_list__processing_array_data() {
		return [
			[
				[ 'foo' ], // input
				[ 'foo' ],  // expected
			],
			[
				'non-array', // input
				[],  // expected
			],
			[
				// assoc array -> only true goes
				[
					'foo'         => true,
					'bar'         => false,
					'string-true' => 'true',
					'number'      => 1,
				],
				[ 'foo' ],  // expected
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_post_meta_allow_list__processing_array_data
	 */
	public function test__get_post_meta_allow_list__processing_array( $returned_by_filter, $expected ) {
		$this->init_es();

		$post     = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		// clearing up jetpack values as those are put by default to vip_search_post_meta_allow_list but are not the object of testing here
		\add_filter( 'jetpack_sync_post_meta_whitelist', '__return_empty_array' );

		\add_filter( 'vip_search_post_meta_allow_list', function () use ( $returned_by_filter ) {
			return $returned_by_filter;
		}, 0);

		$result = $this->search_instance->get_post_meta_allow_list( $post );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_skip_post_meta_sync_should_return_true_if_meta_not_in_allow_list() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = \get_post( $post_id );

		$this->init_es();

		$this->assertTrue( $this->search_instance->filter__ep_skip_post_meta_sync( false, $post, 40, 'random_key', 'random_value' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_skip_post_meta_sync_should_return_false_if_meta_is_in_allow_list() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = \get_post( $post_id );

		\add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_key',
				);
			}
		);

		$this->init_es();

		$this->assertFalse( $this->search_instance->filter__ep_skip_post_meta_sync( false, $post, 40, 'random_key', 'random_value' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_skip_post_meta_sync_should_return_true_if_a_previous_filter_is_true() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = \get_post( $post_id );

		\add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_key',
				);
			}
		);

		$this->init_es();

		$this->assertTrue( $this->search_instance->filter__ep_skip_post_meta_sync( true, $post, 40, 'random_key', 'random_value' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_skip_post_meta_sync_filter_should_return_true_if_meta_not_in_allow_list() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = \get_post( $post_id );

		$this->init_es();

		$this->assertTrue( apply_filters( 'ep_skip_post_meta_sync', false, $post, 40, 'random_key', 'random_value' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_skip_post_meta_sync_filter_should_return_false_if_meta_is_in_allow_list() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = \get_post( $post_id );

		\add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_key',
				);
			}
		);

		$this->init_es();

		$this->assertFalse( apply_filters( 'ep_skip_post_meta_sync', false, $post, 40, 'random_key', 'random_value' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_skip_post_meta_sync_filter_should_return_true_if_a_previous_filter_is_true() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = \get_post( $post_id );

		\add_filter(
			'vip_search_post_meta_allow_list',
			function() {
				return array(
					'random_key',
				);
			}
		);

		$this->init_es();

		$this->assertTrue( apply_filters( 'ep_skip_post_meta_sync', true, $post, 40, 'random_key', 'random_value' ) );
	}

	public function filter__ep_prepare_meta_allowed_protected_keys__should_use_post_meta_allow_list_data() {
		return [
			[
				[], // default
				[], // new
				[], // expected
			],
			[
				[ 'foo' ], // default
				[ 'bar' ], // new
				[ 'foo', 'bar' ], // expected
			],
			[
				// should handle assoc array
				[], // default
				[
					'foo' => true,
					'bar' => false,
				],
				[ 'foo' ], // expected
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider filter__ep_prepare_meta_allowed_protected_keys__should_use_post_meta_allow_list_data
	 */
	public function test__filter__ep_prepare_meta_allowed_protected_keys__should_use_post_meta_allow_list( $default_ep_protected_keys, $added_keys, $expected ) {
		$post     = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		// clearing up jetpack values as those are put by default to vip_search_post_meta_allow_list but are not the object of testing here
		\add_filter( 'jetpack_sync_post_meta_whitelist', '__return_empty_array' );

		\add_filter( 'vip_search_post_meta_allow_list', function ( $meta_keys ) use ( $added_keys ) {
			return array_merge( $meta_keys, $added_keys );
		}, 0);

		\Automattic\VIP\Search\Search::instance();

		$result = \apply_filters( 'ep_prepare_meta_allowed_protected_keys', $default_ep_protected_keys, $post );

		$this->assertEquals( $expected, $result );
	}

	public function test__filter__ep_do_intercept_request__records_statsd() {
		$query                = [ 'url' => 'https://foo.bar' ];
		$args                 = [];
		$stats_prefix         = 'foo';
		$mocked_response_body = [
			'took' => 100,
		];
		$mocked_response      = [
			'body' => wp_json_encode( $mocked_response_body ),
		];

		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_increment_stat', 'maybe_send_timing_stat' ] )
			->getMock();

		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );

		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_wp_remote_request' )
			->willReturn( $mocked_response );

		$partially_mocked_search->expects( $this->once() )
			->method( 'maybe_increment_stat' )
			->with( "$stats_prefix.total" );

		$partially_mocked_search->expects( $this->exactly( 2 ) )
			->method( 'maybe_send_timing_stat' )
			->withConsecutive(
				[ "$stats_prefix.engine", $mocked_response_body['took'] ],
				[ "$stats_prefix.total", $this->greaterThan( 0 ) ]
			);

		$partially_mocked_search->filter__ep_do_intercept_request( null, $query, $args, 0, null );
	}

	public function test__filter__ep_do_intercept_request__records_statsd_per_doc() {
		$query                = [ 'url' => 'https://foo.bar/' ];
		$args                 = [];
		$stats_prefix         = 'foo';
		$mocked_response_body = [
			'items' => [ [], [] ],
		];
		$mocked_response      = [
			'body' => wp_json_encode( $mocked_response_body ),
		];

		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_send_timing_stat' ] )
			->getMock();
		$partially_mocked_search->method( 'is_bulk_url' )
			->willReturn( true );
		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );
		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_wp_remote_request' )
			->willReturn( $mocked_response );

		$partially_mocked_search->expects( $this->exactly( 2 ) )
			->method( 'maybe_send_timing_stat' )
			->withConsecutive(
				[ "$stats_prefix.total", $this->greaterThan( 0 ) ],
				[ "$stats_prefix.per_doc", $this->greaterThan( 0 ) ]
			);

		$partially_mocked_search->filter__ep_do_intercept_request( null, $query, $args, 0, null );
	}

	public function test__filter__ep_do_intercept_request__records_statsd_on_non_200_response() {
		$query           = [ 'url' => 'https://foo.bar' ];
		$args            = [];
		$stats_prefix    = 'foo';
		$mocked_response = [
			'response' => [
				'code'    => 400,
				'message' => 'Bad Request',
			],
		];

		$statsd_mock = $this->createMock( \Automattic\VIP\StatsD::class );

		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_increment_stat' ] )
			->getMock();
		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );
		$partially_mocked_search->statsd = $statsd_mock;
		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_wp_remote_request' )
			->willReturn( $mocked_response );

		$partially_mocked_search->expects( $this->exactly( 2 ) )
			->method( 'maybe_increment_stat' )
			->withConsecutive( [ "$stats_prefix.total" ], [ "$stats_prefix.error" ] );

		$partially_mocked_search->filter__ep_do_intercept_request( null, $query, $args, 0, null );
	}

	public function test__filter__ep_do_intercept_request__records_statsd_on_wp_error_per_msg() {
		$query           = [ 'url' => 'https://foo.bar' ];
		$args            = [];
		$stats_prefix    = 'foo';
		$mocked_response = new \WP_Error( 'code1', 'msg1' );
		$mocked_response->add( 'code2', 'msg2' );

		$statsd_mock = $this->createMock( \Automattic\VIP\StatsD::class );

		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_increment_stat' ] )
			->getMock();

		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );

		$partially_mocked_search->statsd = $statsd_mock;

		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_wp_remote_request' )
			->willReturn( $mocked_response );

		$partially_mocked_search->expects( $this->exactly( 3 ) )
			->method( 'maybe_increment_stat' )
			->withConsecutive( [ "$stats_prefix.total" ], [ "$stats_prefix.error" ], [ "$stats_prefix.error" ] );

		$partially_mocked_search->filter__ep_do_intercept_request( null, $query, $args, 0, null );
	}

	public function test__filter__ep_do_intercept_request__records_statsd_on_wp_error_timeout() {
		$query           = [ 'url' => 'https://foo.bar' ];
		$args            = [];
		$stats_prefix    = 'foo';
		$mocked_response = new \WP_Error( 'code1', 'curl error 28' );

		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_increment_stat' ] )
			->getMock();

		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );

		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_wp_remote_request' )
			->willReturn( $mocked_response );

		$partially_mocked_search->expects( $this->exactly( 2 ) )
			->method( 'maybe_increment_stat' )
			->withConsecutive( [ "$stats_prefix.total" ], [ "$stats_prefix.timeout" ] );

		$partially_mocked_search->filter__ep_do_intercept_request( null, $query, $args, 0, null );
	}

	public function test__maybe_alert_for_average_queue_time__sends_notification() {
		$application_id      = 123;
		$application_url     = 'http://example.org';
		$average_queue_value = 3601;
		$queue_count_value   = 1;
		$longest_queue_value = $average_queue_value;
		$expected_message    = "Average index queue wait time for application {$application_id} - {$application_url} is currently {$average_queue_value} seconds. There are {$queue_count_value} items in the queue and the oldest item is {$longest_queue_value} seconds old";
		$expected_level      = 2;

		$es = new \Automattic\VIP\Search\Search();
		$this->search_instance->init();

		$alerts_mocked   = $this->createMock( \Automattic\VIP\Utils\Alerts::class );
		$queue_mocked    = $this->createMock( \Automattic\VIP\Search\Queue::class );
		$indexables_mock = $this->createMock( \ElasticPress\Indexables::class );

		$this->search_instance->queue      = $queue_mocked;
		$this->search_instance->indexables = $indexables_mock;
		$this->search_instance->alerts     = $alerts_mocked;

		$indexables_mock->method( 'get' )
			->willReturn( $this->createMock( \ElasticPress\Indexable::class ) );

		$queue_mocked
			->method( 'get_queue_stats' )
			->willReturn( (object) [
				'average_wait_time' => $average_queue_value,
				'queue_count'       => $queue_count_value,
				'longest_wait_time' => $longest_queue_value,
			] );

		$alerts_mocked->expects( $this->once() )
			->method( 'send_to_chat' )
			->with( '#vip-go-es-alerts', $expected_message, $expected_level );

		$this->search_instance->maybe_alert_for_average_queue_time();
	}

	public function maybe_alert_for_field_count_data() {
		return [
			[ 5000, false ],
			[ 5001, true ],
		];
	}

	/**
	 * @dataProvider maybe_alert_for_field_count_data
	 */
	public function test__maybe_alert_for_field_count( $field_count, $should_alert ) {
		$application_id   = 123;
		$application_url  = 'http://example.org';
		$expected_message = "The field count for post index for application $application_id - $application_url is too damn high - $field_count";
		$expected_level   = 2;

		/** @var MockObject&\Automattic\VIP\Search\Search */
		$partially_mocked_search = $this->getMockBuilder( \Automattic\VIP\Search\Search::class )
			->setMethods( [ 'get_current_field_count' ] )
			->getMock();
		$partially_mocked_search->init();

		$alerts_mocked   = $this->createMock( \Automattic\VIP\Utils\Alerts::class );
		$indexables_mock = $this->createMock( \ElasticPress\Indexables::class );

		$partially_mocked_search->indexables = $indexables_mock;
		$partially_mocked_search->alerts     = $alerts_mocked;

		$indexables_mock->method( 'get' )
			->willReturn( $this->createMock( \ElasticPress\Indexable::class ) );

		$partially_mocked_search->method( 'get_current_field_count' )->willReturn( $field_count );

		$alerts_mocked->expects( $should_alert ? $this->once() : $this->never() )
			->method( 'send_to_chat' )
			->with( '#vip-go-es-alerts', $expected_message, $expected_level );

		$partially_mocked_search->maybe_alert_for_field_count();
	}

	public function maybe_alert_for_prolonged_query_limiting_data() {
		return [
			[ false, false ],
			[ 0, false ],
			[ 12, false ],
			[ 7201, true ],
		];
	}

	/**
	 * @dataProvider maybe_alert_for_prolonged_query_limiting_data
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_alert_for_prolonged_query_limiting( $difference, $should_alert ) {
		$expected_level = 2;

		$time = time();

		if ( false !== $difference ) {
			$query_limited_start = $time - $difference;
			wp_cache_set( Search::QUERY_RATE_LIMITED_START_CACHE_KEY, $query_limited_start, Search::SEARCH_CACHE_GROUP );
		}

		$es = new \Automattic\VIP\Search\Search();
		$this->search_instance->init();
		$this->search_instance->set_time( $time );

		$alerts_mocked = $this->createMock( \Automattic\VIP\Utils\Alerts::class );

		$this->search_instance->alerts = $alerts_mocked;

		$alerts_mocked->expects( $should_alert ? $this->once() : $this->never() )
			->method( 'send_to_chat' )
			->with( '#vip-go-es-alerts', $this->anything(), $expected_level );

		// trigger_error is only called if an alert should happen
		if ( $should_alert ) {
			$this->expectWarning();
			$this->expectWarningMessage(
				sprintf(
					'Application 123 - http://example.org has had its Elasticsearch queries rate limited for %d seconds. Half of traffic is diverted to the database when queries are rate limited.',
					$difference
				)
			);
		}

		$this->search_instance->maybe_alert_for_prolonged_query_limiting();
		$this->search_instance->reset_time();
	}

	/* Format:
	 * [
	 * 		[
	 * 			$filter,
	 * 			$too_low_message,
	 * 			$too_high_message,
	 * 		]
	 * ]
	 */
	public function vip_search_ratelimiting_filter_data() {
		return array(
			[
				'vip_search_ratelimit_period',
				'vip_search_ratelimit_period should not be set below 60 seconds.',
				'vip_search_ratelimit_period should not be set above 7200 seconds.',
			],
			[
				'vip_search_max_query_count',
				'vip_search_max_query_count should not be below 10 queries per second.',
				'vip_search_max_query_count should not exceed 500 queries per second.',
			],
			[
				'vip_search_query_db_fallback_value',
				'vip_search_query_db_fallback_value should be between 1 and 10.',
				'vip_search_query_db_fallback_value should be between 1 and 10.',
			],
		);
	}

	/**
	 * @dataProvider vip_search_ratelimiting_filter_data
	 */
	public function test__filter__vip_search_ratelimiting_numeric_validation( $filter, $too_low_message, $too_high_message ) {
		add_filter(
			$filter,
			function() {
				return '30.ffr';
			}
		);

		$this->setExpectedIncorrectUsage( 'add_filter' );
		$this->search_instance->apply_settings();
	}

	/**
	 * @dataProvider vip_search_ratelimiting_filter_data
	 */
	public function test__filter__vip_search_ratelimiting_too_low_validation( $filter, $too_low_message, $too_high_message ) {
		add_filter(
			$filter,
			function() {
				return 0;
			}
		);

		$this->setExpectedIncorrectUsage( 'add_filter' );
		$this->search_instance->apply_settings();
	}

	/**
	 * @dataProvider vip_search_ratelimiting_filter_data
	 */
	public function test__filter__vip_search_ratelimiting_too_high_validation( $filter, $too_low_message, $too_high_message ) {
		add_filter(
			$filter,
			function() {
				return PHP_INT_MAX;
			}
		);

		$this->setExpectedIncorrectUsage( 'add_filter' );
		$this->search_instance->apply_settings();
	}

	public function stat_sampling_invalid_stat_param_data() {
		return [
			[ array() ],
			[ null ],
			[ new \stdClass() ],
			[ 5 ],
			[ 8.6 ],
		];
	}

	public function stat_sampling_invalid_value_param_data() {
		return [
			[ array() ],
			[ null ],
			[ new \stdClass() ],
			[ 'random' ],
		];
	}

	/**
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_increment_stat_sampling_keep() {
		$this->init_es();

		$this->search_instance::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->search_instance->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->once() )
			->method( 'increment' )
			->with( 'test' );

		$this->search_instance->maybe_increment_stat( 'test' );
	}

	/**
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_increment_stat_sampling_drop() {
		$this->init_es();

		$this->search_instance::$stat_sampling_drop_value = 0; // Guarantee a sampling drop

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->search_instance->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'increment' );

		$this->search_instance->maybe_increment_stat( 'test' );
	}

	/**
	 * @dataProvider stat_sampling_invalid_stat_param_data
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_increment_stat_sampling_invalid_stat_param( $stat ) {
		$es = new \Automattic\VIP\Search\Search();
		$this->search_instance->init();

		$es::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->search_instance->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'increment' );

		$this->search_instance->maybe_increment_stat( $stat );
	}

	/**
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_send_timing_stat_sampling_keep() {
		$this->init_es();

		$this->search_instance::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->search_instance->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->once() )
			->method( 'timing' )
			->with( 'test', 50 );

		$this->search_instance->maybe_send_timing_stat( 'test', 50 );
	}

	/**
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_send_timing_stat_sampling_drop() {
		$this->init_es();

		$this->search_instance::$stat_sampling_drop_value = 0; // Guarantee a sampling drop

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->search_instance->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'timing' );

		$this->search_instance->maybe_send_timing_stat( 'test', 50 );
	}

	/**
	 * @dataProvider stat_sampling_invalid_stat_param_data
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_send_timing_stat_sampling_invalid_stat_param( $stat ) {
		$this->init_es();

		$this->search_instance::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->search_instance->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'timing' );

		$this->search_instance->maybe_send_timing_stat( $stat, 50 );
	}

	/**
	 * @dataProvider stat_sampling_invalid_value_param_data
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_send_timing_stat_sampling_invalid_duration_param( $value ) {
		$this->init_es();

		$this->search_instance::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->search_instance->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'timing' );

		$this->search_instance->maybe_send_timing_stat( 'test', $value );
	}


	public function ep_handle_failed_request_data() {
		return [
			[
				[
					'body' => '{ "error": { "reason": "error text"} }',
				],
				'error text',
			],
			[
				[
					'body'     => '{ "error": {} }',
					'response' => [
						'code'    => 401,
						'message' => 'Unauthorized',
					],
				],
				'401 Unauthorized',
			],
			[
				[
					'body' => '{}',
				],
				'Unknown Elasticsearch query error',
			],
			[
				[],
				'Unknown Elasticsearch query error',
			],
		];
	}

	/**
	 * @dataProvider ep_handle_failed_request_data
	 */
	public function test__ep_handle_failed_request__log_message( $response, $expected_message ) {
		$this->init_es();

		$this->search_instance->logger = $this->getMockBuilder( \Automattic\VIP\Logstash\Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$this->search_instance->logger->expects( $this->once() )
				->method( 'log' )
				->with(
					$this->equalTo( 'error' ),
					$this->equalTo( 'search_query_error' ),
					$this->equalTo( $expected_message ),
					$this->anything()
				);

		$this->search_instance->ep_handle_failed_request( null, $response, [], '', null );
	}

	/**
	 * Ensure when actions from the skiplist are called, they do not get logged as a failed request.
	 */
	public function test__ep_handle_failed_request__skiplist() {
		$this->init_es();

		$this->search_instance->logger = $this->getMockBuilder( \Automattic\VIP\Logstash\Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$this->search_instance->logger->expects( $this->never() )->method( 'log' );

		$skiplist = [
			'index_exists',
			'get',
		];

		foreach ( $skiplist as $item ) {
			$this->search_instance->ep_handle_failed_request( null, 404, [], 0, $item );
		}
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

	public function test__maybe_log_query_ratelimiting_start_should_do_nothing_if_ratelimiting_already_started() {
		$this->init_es();

		wp_cache_set( $this->search_instance::QUERY_RATE_LIMITED_START_CACHE_KEY, time(), $this->search_instance::SEARCH_CACHE_GROUP );

		$this->search_instance->logger = $this->getMockBuilder( \Automattic\VIP\Logstash\Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$this->search_instance->logger->expects( $this->never() )->method( 'log' );

		$this->search_instance->maybe_log_query_ratelimiting_start();
	}

	public function test__maybe_log_query_ratelimiting_start_should_log_if_ratelimiting_not_already_started() {
		$this->init_es();

		$this->search_instance->logger = $this->getMockBuilder( \Automattic\VIP\Logstash\Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$this->search_instance->logger->expects( $this->once() )
				->method( 'log' )
				->with(
					$this->equalTo( 'warning' ),
					$this->equalTo( 'search_query_rate_limiting' ),
					$this->equalTo(
						'Application 123 - http://example.org has triggered Elasticsearch query rate limiting, which will last up to 300 seconds. Subsequent or repeat occurrences are possible. Half of traffic is diverted to the database when queries are rate limited.'
					),
					$this->anything()
				);

		$this->search_instance->maybe_log_query_ratelimiting_start();
	}

	public function test__add_attachment_to_ep_indexable_post_types_should_return_the_passed_value_if_not_array() {
		Constant_Mocker::define( 'EP_DASHBOARD_SYNC', 'test' );
		$es = new \Automattic\VIP\Search\Search();
		$this->search_instance->init();

		$this->assertEquals( 'testing', $this->search_instance->add_attachment_to_ep_indexable_post_types( 'testing' ) );
		$this->assertEquals( 65, $this->search_instance->add_attachment_to_ep_indexable_post_types( 65 ) );
		$this->assertEquals( null, $this->search_instance->add_attachment_to_ep_indexable_post_types( null ) );
		$this->assertEquals( new \StdClass(), $this->search_instance->add_attachment_to_ep_indexable_post_types( new \StdClass() ) );
	}

	public function test__add_attachment_to_ep_indexable_post_types_should_append_attachment_to_array() {
		$this->init_es();

		$this->assertEquals( array( 'attachment' => 'attachment' ), $this->search_instance->add_attachment_to_ep_indexable_post_types( array() ) );
		$this->assertEquals(
			array(
				'test'       => 'test',
				'one'        => 'one',
				'attachment' => 'attachment',
			),
			$this->search_instance->add_attachment_to_ep_indexable_post_types(
				array(
					'test' => 'test',
					'one'  => 'one',
				)
			)
		);
	}

	public function test__ep_indexable_post_types_should_return_the_passed_value_if_not_array() {
		$this->init_es();

		\ElasticPress\Features::factory()->activate_feature( 'protected_content' );

		$this->assertEquals( 'testing', apply_filters( 'ep_indexable_post_types', 'testing' ) );
		$this->assertEquals( 65, apply_filters( 'ep_indexable_post_types', 65 ) );
		$this->assertEquals( null, apply_filters( 'ep_indexable_post_types', null ) );
		$this->assertEquals( new \StdClass(), apply_filters( 'ep_indexable_post_types', new \StdClass() ) );
	}

	public function test__ep_indexable_post_types_should_append_attachment_to_array() {
		// Ensure ElasticPress is ready
		do_action( 'plugins_loaded' );

		\ElasticPress\Features::factory()->activate_feature( 'protected_content' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$this->assertEquals( array( 'attachment' => 'attachment' ), apply_filters( 'ep_indexable_post_types', array() ) );
		$this->assertEquals(
			array(
				'test'       => 'test',
				'one'        => 'one',
				'attachment' => 'attachment',
			),
			apply_filters(
				'ep_indexable_post_types',
				array(
					'test' => 'test',
					'one'  => 'one',
				)
			)
		);
	}

	public function test__is_protected_content_enabled_should_return_false_if_protected_content_not_enabled() {
		$this->init_es();

		$this->assertFalse( $this->search_instance->is_protected_content_enabled() );
	}

	public function test__is_protected_content_enabled_should_return_true_if_protected_content_enabled() {
		$this->init_es();

		\ElasticPress\Features::factory()->activate_feature( 'protected_content' );

		$this->assertTrue( $this->search_instance->is_protected_content_enabled() );
	}

	public function test__get_random_host_return_null_if_no_host() {
		$this->init_es();

		$this->assertSame( null, $this->search_instance->get_random_host( array() ) );
	}

	public function test__get_random_host_return_null_if_hosts_is_not_array() {
		$this->init_es();

		$this->assertSame( null, $this->search_instance->get_random_host( false ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_enable_ep_query_logging_no_debug_tools_enabled() {
		add_filter( 'debug_bar_enable', '__return_false', PHP_INT_MAX );
		add_filter( 'wpcom_vip_qm_enable', '__return_false', PHP_INT_MAX );

		$this->init_es();

		$this->assertFalse( defined( 'WP_EP_DEBUG' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_enable_ep_query_logging_qm_enabled() {
		add_filter( 'debug_bar_enable', '__return_false', PHP_INT_MAX );
		add_filter( 'wpcom_vip_qm_enable', '__return_true' );

		$this->init_es();

		$this->assertTrue( Constant_Mocker::defined( 'WP_EP_DEBUG' ) );
		$this->assertTrue( Constant_Mocker::constant( 'WP_EP_DEBUG' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_enable_ep_query_logging_debug_bar_enabled() {
		add_filter( 'wpcom_vip_qm_enable', '__return_false', PHP_INT_MAX );
		add_filter( 'debug_bar_enable', '__return_true' );

		$this->init_es();

		$this->assertTrue( Constant_Mocker::defined( 'WP_EP_DEBUG' ) );
		$this->assertTrue( Constant_Mocker::constant( 'WP_EP_DEBUG' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_enable_ep_query_logging_debug_bar_and_qm_enabled() {
		add_filter( 'debug_bar_enable', '__return_true' );
		add_filter( 'wpcom_vip_qm_enable', '__return_true' );

		$this->init_es();

		do_action( 'plugins_loaded' );

		$this->assertTrue( Constant_Mocker::defined( 'WP_EP_DEBUG' ) );
		$this->assertTrue( Constant_Mocker::constant( 'WP_EP_DEBUG' ) );
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
		$result = $this->search_instance->limit_max_result_window( $input );

		$this->assertEquals( $expected, $result );
	}

	public function test__are_es_constants_defined__no_constatns() {
		$result = \Automattic\VIP\Search\Search::are_es_constants_defined();

		$this->assertFalse( $result );
	}

	public function test__are_es_constants_defined__all_constatns() {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'endpoint' ] );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		$result = \Automattic\VIP\Search\Search::are_es_constants_defined();

		$this->assertTrue( $result );
	}

	public function test__are_es_constants_defined__empty_password() {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'endpoint' ] );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_PASSWORD', '' );

		$result = \Automattic\VIP\Search\Search::are_es_constants_defined();

		$this->assertFalse( $result );
	}

	public function test__are_es_constants_defined__no_username() {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'endpoint' ] );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		$result = \Automattic\VIP\Search\Search::are_es_constants_defined();

		$this->assertFalse( $result );
	}

	public function test__are_es_constants_defined__no_endpoints() {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', [] );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		$result = \Automattic\VIP\Search\Search::are_es_constants_defined();

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

	/**
	 * Helper function for accessing protected properties.
	 */
	protected static function get_property( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\Search' );

		$property = $class->getProperty( $name );
		$property->setAccessible( true );

		return $property;
	}

	public function mock_vip_safe_wp_remote_request() {
		/* Empty */
	}

	public function mock_wp_remote_request() {
		/* Empty */
	}

	/**
	 * Helper function to set required constant, initialize the search instance, and do required action for setting up EP indexables.
	 * 
	 * @return void
	 */
	private function init_es() {
		Constant_Mocker::define( 'EP_DASHBOARD_SYNC', false );
		$this->search_instance->init();

		do_action( 'plugins_loaded' );
	}
}

/**
 * Overwriting global function so that no real remote request is called
 */
function vip_safe_wp_remote_request( $url, $fallback_value = '', $threshold = 3, $timeout = 1, $retry = 20, $args = array() ) {
	return is_null( Search_Test::$mock_global_functions ) ? null : Search_Test::$mock_global_functions->mock_vip_safe_wp_remote_request();
}

/**
 * Overwriting global function so that no real remote request is called
 */
function wp_remote_request( $url, $args = array() ) {
	return is_null( Search_Test::$mock_global_functions ) ? null : Search_Test::$mock_global_functions->mock_wp_remote_request();
}
