<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;
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

	public $test_index_name = 'vip-1234-post-0-v3';

	public function setUp(): void {
		$this->search_instance = new \Automattic\VIP\Search\Search();

		$cache_key = \Automattic\VIP\Search\Search::INDEX_EXISTENCE_CACHE_KEY_PREFIX . $this->test_index_name;
		wp_cache_delete( $cache_key, \Automattic\VIP\Search\Search::SEARCH_CACHE_GROUP );

		header_remove();
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

	public function test__vip_search_filter_ep_index_name_with_overridden_version() {
		define( 'VIP_ORIGIN_DATACENTER', 'dfw' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		// For EP to register Indexables
		do_action( 'plugins_loaded' );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$new_version = $es->versioning->add_version( $indexable );

		$this->assertNotFalse( $new_version, 'Failed to add new version of index' );
		$this->assertNotInstanceOf( \WP_Error::class, $new_version, 'Got WP_Error when adding new index version' );

		// Override the version
		$override_result = $es->versioning->set_current_version_number( $indexable, 2 );

		$this->assertTrue( $override_result, 'Setting current version number failed' );

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $indexable );

		$this->assertEquals( 'vip-123-post-v2', $index_name, 'Overridden index name is not correct' );

		// Reset
		$es->versioning->reset_current_version_number( $indexable );

		$index_name = apply_filters( 'ep_index_name', 'index-name', null, $indexable );

		$this->assertEquals( 'vip-123-post', $index_name );

		delete_option( Versioning::INDEX_VERSIONS_OPTION );
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

		do_action( 'plugins_loaded' );

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

		do_action( 'plugins_loaded' );

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

		do_action( 'plugins_loaded' );

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

		do_action( 'plugins_loaded' );

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

	/**
	 * Test that instantiating the HealthJob works as expected (files are properly included, init is hooked)
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_search_setup_healthchecks_with_enabled() {
		// Need to filter to enable the HealthJob
		add_filter( 'enable_vip_search_healthchecks', '__return_true' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();
		$es->setup_cron_jobs();
		// Should not have fataled (class was included)

		// Ensure it returns the priority set. Easiest way to to ensure it's not false
		$this->assertTrue( false !== has_action( 'wp_loaded', [ $es->healthcheck, 'init' ] ) );
	}

	/**
	 * Test that instantiating the HealthJob does not happen when not in production
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_search_setup_healthchecks_disabled_in_non_production_env() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();
		$es->setup_cron_jobs();

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

	/**
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

	/**
	 * Ensure ratelimiting works prioperly with ep_skip_query_integration filter
	 */
	public function test__rate_limit_ep_query_integration__trigers() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		add_option( 'vip_enable_vip_search_query_integration', true );
		define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );
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

		$this->expectNotice();

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
		define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );

		$should = \Automattic\VIP\Search\Search::should_load_es_wp_query();

		$this->assertTrue( $should );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_migration() {
		define( 'VIP_SEARCH_MIGRATION_SOURCE', 'jetpack' );

		$this->assertTrue( $this->search_instance->is_jetpack_migration() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_migration__different_value() {
		define( 'VIP_SEARCH_MIGRATION_SOURCE', 'foo' );

		$this->assertFalse( $this->search_instance->is_jetpack_migration() );
	}

	/**
	 * This tests the correct implementaton of the ep_$indexable_mapping filters, but note that these filters
	 * operate on the mapping and settings together - EP doesn't yet distinguish between them
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter__ep_indexable_mapping() {
		define( 'VIP_ORIGIN_DATACENTER', 'dfw' );

		$this->search_instance->init();

		// Ensure ElasticPress is ready
		do_action( 'plugins_loaded' );

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
		define( 'VIP_ORIGIN_DATACENTER', 'foo' );

		$this->search_instance->init();

		// Ensure ElasticPress is ready
		do_action( 'plugins_loaded' );

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
		define( 'VIP_ORIGIN_DATACENTER', 'dca' );

		$this->search_instance->init();

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
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', $endpoints );

		$this->search_instance->init();

		$origin_dc = $this->search_instance->get_index_routing_allocation_include_dc();

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
		define( 'VIP_SEARCH_MIGRATION_SOURCE', 'jetpack' );

		$es = \Automattic\VIP\Search\Search::instance();

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

		$result = $es->get_post_meta_allow_list( $post );

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
		$es = \Automattic\VIP\Search\Search::instance();

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

		$result = $es->get_post_meta_allow_list( $post );

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
		$es = \Automattic\VIP\Search\Search::instance();

		$post     = new \WP_Post( new \StdClass() );
		$post->ID = 0;

		// clearing up jetpack values as those are put by default to vip_search_post_meta_allow_list but are not the object of testing here
		\add_filter( 'jetpack_sync_post_meta_whitelist', function () {
			return [];
		} );

		\add_filter( 'vip_search_post_meta_allow_list', function () use ( $returned_by_filter ) {
			return $returned_by_filter;
		}, 0);

		$result = $es->get_post_meta_allow_list( $post );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__ep_skip_post_meta_sync_filter_should_return_true_if_meta_not_in_allow_list() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );

		$post = \get_post( $post_id );

		$es = \Automattic\VIP\Search\Search::instance();

		$this->assertTrue( apply_filters( 'ep_skip_post_meta_sync', false, $post, 40, 'random_key', 'random_value' ) );
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
		\add_filter( 'jetpack_sync_post_meta_whitelist', function () {
			return [];
		} );

		\add_filter( 'vip_search_post_meta_allow_list', function ( $meta_keys ) use ( $added_keys ) {
			return array_merge( $meta_keys, $added_keys );
		}, 0);

		\Automattic\VIP\Search\Search::instance();

		$result = \apply_filters( 'ep_prepare_meta_allowed_protected_keys', $default_ep_protected_keys, $post );

		$this->assertEquals( $expected, $result );
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

		$this->expectNotice();
		$this->expectNoticeMessage(
			sprintf(
				'add_filter was called <strong>incorrectly</strong>. %s should be an integer. Please see <a href="https://wordpress.org/support/article/debugging-in-wordpress/">Debugging in WordPress</a> for more information. (This message was added in version 5.5.3.)',
				$filter
			)
		);

		$this->search_instance->apply_settings();
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

		$this->expectNotice();
		$this->expectNoticeMessage(
			sprintf(
				'add_filter was called <strong>incorrectly</strong>. %s Please see <a href="https://wordpress.org/support/article/debugging-in-wordpress/">Debugging in WordPress</a> for more information. (This message was added in version 5.5.3.)',
				$too_low_message
			)
		);

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

		$this->expectNotice();
		$this->expectNoticeMessage(
			sprintf(
				'add_filter was called <strong>incorrectly</strong>. %s Please see <a href="https://wordpress.org/support/article/debugging-in-wordpress/">Debugging in WordPress</a> for more information. (This message was added in version 5.5.3.)',
				$too_high_message
			)
		);

		$this->search_instance->apply_settings();
	}

	public function test__is_protected_content_enabled_should_return_false_if_protected_content_not_enabled() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		// Ensure ElasticPress is ready
		do_action( 'plugins_loaded' );

		$this->assertFalse( $es->is_protected_content_enabled() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_enable_ep_query_logging_no_debug_tools_enabled() {
		add_filter( 'debug_bar_enable', '__return_false', PHP_INT_MAX );
		add_filter( 'wpcom_vip_qm_enable', '__return_false', PHP_INT_MAX );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		do_action( 'plugins_loaded' );

		$this->assertFalse( defined( 'WP_EP_DEBUG' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_enable_ep_query_logging_qm_enabled() {
		add_filter( 'debug_bar_enable', '__return_false', PHP_INT_MAX );
		add_filter( 'wpcom_vip_qm_enable', '__return_true' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		do_action( 'plugins_loaded' );

		$this->assertTrue( defined( 'WP_EP_DEBUG' ) );
		$this->assertTrue( WP_EP_DEBUG );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_enable_ep_query_logging_debug_bar_enabled() {
		add_filter( 'wpcom_vip_qm_enable', '__return_false', PHP_INT_MAX );
		add_filter( 'debug_bar_enable', '__return_true' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		do_action( 'plugins_loaded' );

		$this->assertTrue( defined( 'WP_EP_DEBUG' ) );
		$this->assertTrue( WP_EP_DEBUG );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_enable_ep_query_logging_debug_bar_and_qm_enabled() {
		add_filter( 'debug_bar_enable', '__return_true' );
		add_filter( 'wpcom_vip_qm_enable', '__return_true' );

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		do_action( 'plugins_loaded' );

		$this->assertTrue( defined( 'WP_EP_DEBUG' ) );
		$this->assertTrue( WP_EP_DEBUG );
	}

	public function test__are_es_constants_defined__all_constatns() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'endpoint' ] );
		define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		$result = \Automattic\VIP\Search\Search::are_es_constants_defined();

		$this->assertTrue( $result );
	}

	public function test__are_es_constants_defined__empty_password() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'endpoint' ] );
		define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		define( 'VIP_ELASTICSEARCH_PASSWORD', '' );

		$result = \Automattic\VIP\Search\Search::are_es_constants_defined();

		$this->assertFalse( $result );
	}

	public function test__are_es_constants_defined__no_username() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', [ 'endpoint' ] );
		define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

		$result = \Automattic\VIP\Search\Search::are_es_constants_defined();

		$this->assertFalse( $result );
	}

	public function test__are_es_constants_defined__no_endpoints() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', [] );
		define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );

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
}
