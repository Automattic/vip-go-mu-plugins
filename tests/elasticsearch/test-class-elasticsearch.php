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

		$this->assertEquals( EP_SYNC_CHUNK_LIMIT, 500 );
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
	 * Test that we load the ElasticPress Debug Bar plugin when Debug Bar is showing
	 */
	public function test__vip_elasticsearch_loads_ep_debug_bar_when_debug_bar_showing() {
		// Remove previous filters that would affect test (b/c it also uses PHP_INT_MAX priority)
		remove_all_filters( 'debug_bar_enable' );

		// Debug bar enabled
		add_filter( 'debug_bar_enable', '__return_true', PHP_INT_MAX );

		// Be sure we don't already have the class loaded (or our test does nothing)
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ) );

		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$es->action__plugins_loaded();

		// Class should now exist
		$this->assertEquals( true, function_exists( 'ep_add_debug_bar_panel' ) );
	}

	/**
	 * Test that we load the ElasticPress Debug Bar plugin when Debug Bar is disabled, but Query Monitor is showing
	 */
	public function test__vip_elasticsearch_loads_ep_debug_bar_when_debug_bar_disabled_but_qm_enabled() {
		// Remove previous filters that would affect test (b/c it also uses PHP_INT_MAX priority)
		remove_all_filters( 'debug_bar_enable' );

		// Debug bar disabled
		add_filter( 'debug_bar_enable', '__return_false', PHP_INT_MAX );
		// But QM enabled
		add_filter( 'wpcom_vip_qm_enable', '__return_true', PHP_INT_MAX );

		// Be sure we don't already have the class loaded (or our test does nothing)
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ) );

		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$es->action__plugins_loaded();

		// Class should now exist
		$this->assertEquals( true, function_exists( 'ep_add_debug_bar_panel' ) );
	}

	/**
	 * Test that we load the ElasticPress Debug Bar plugin when both Debug Bar Query Monitor are showing
	 */
	public function test__vip_elasticsearch_loads_ep_debug_bar_when_debug_bar_and_qm_enabled() {
		// Remove previous filters that would affect test (b/c it also uses PHP_INT_MAX priority)
		remove_all_filters( 'debug_bar_enable' );

		// Debug bar enabled
		add_filter( 'debug_bar_enable', '__return_true', PHP_INT_MAX );
		// And QM enabled
		add_filter( 'wpcom_vip_qm_enable', '__return_true', PHP_INT_MAX );

		// Be sure we don't already have the class loaded (or our test does nothing)
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ) );

		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$es->action__plugins_loaded();

		// Class should now exist
		$this->assertEquals( true, function_exists( 'ep_add_debug_bar_panel' ) );
	}

	/**
	 * Test that we don't load the ElasticPress Debug Bar plugin when neither Debug Bar or Query Monitor are showing
	 */
	public function test__vip_elasticsearch_does_not_load_ep_debug_bar_when_debug_bar_and_qm_disabled() {
		// Remove previous filters that would affect test (b/c it also uses PHP_INT_MAX priority)
		remove_all_filters( 'debug_bar_enable' );

		// Debug bar disabled
		add_filter( 'debug_bar_enable', '__return_false', PHP_INT_MAX );
		// And QM disabled
		add_filter( 'wpcom_vip_qm_enable', '__return_false', PHP_INT_MAX );

		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$es->action__plugins_loaded();

		// Class should not exist
		$this->assertEquals( false, function_exists( 'ep_add_debug_bar_panel' ) );
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

	public function vip_elasticsearch_filter__jetpack_widgets_to_include_data() {
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
	 * @dataProvider vip_elasticsearch_filter__jetpack_widgets_to_include_data
	 */
	public function test__vip_elasticsearch_filter__jetpack_widgets_to_include( $input, $expected ) {
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$result = $es->filter__jetpack_widgets_to_include( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that instantiating the HealthJob works as expected (files are properly included, init is hooked)
	 */
	public function test__vip_elasticsearch_setup_healthchecks_with_enabled() {
		// Need to filter to enable the HealthJob
		add_filter( 'enable_vip_search_healthchecks', '__return_true' );

		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		// Should not have fataled (class was included)

		// Should have registered the init action to setup the health check
		$this->assertEquals( true, has_action( 'init', [ $es->healthcheck, 'init' ] ) );
	}

	/**
	 * Test that instantiating the HealthJob does not happen when not in production
	 */
	public function test__vip_elasticsearch_setup_healthchecks_disabled_in_non_production_env() {
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		// Should not have fataled (class was included)

		// Should not have instantiated and registered the init action to setup the health check
		$this->assertEquals( false, isset( $es->healthcheck ) );
	}
}
