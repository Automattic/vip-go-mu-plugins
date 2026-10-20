<?php

namespace Automattic\VIP\Search;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Utils\Alerts;
use ElasticPress\Feature;
use ElasticPress\Feature\SearchOrdering\SearchOrdering;
use ElasticPress\Features;
use ElasticPress\Indexable;
use ElasticPress\Indexables;
use stdClass;
use WP_Post;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\http_response;

require_once __DIR__ . '/mock-header.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-query-classifier.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-query-warning.php';
require_once __DIR__ . '/../../../../search/search.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-versioning.php';
require_once __DIR__ . '/../../../../search/elasticpress/elasticpress.php';
require_once __DIR__ . '/../../../../prometheus.php';

class Search_Test extends WP_UnitTestCase {
	public static $mock_global_functions;

	/** @var Search */
	private $search_instance;

	public function setUp(): void {
		parent::setUp();

		\Automattic\VIP\Prometheus\Plugin::get_instance()->init_registry();
		$this->search_instance = new Search();
		$this->search_instance->load_collector();
		\Automattic\VIP\Prometheus\Plugin::get_instance()->load_collectors();

		self::$mock_global_functions = $this->getMockBuilder( self::class )
			->addMethods( [ 'mock_vip_safe_wp_remote_request', 'mock_wp_remote_request' ] )
			->getMock();

		header_remove();

		// As of PHPUnit 10.x, expectWarning() is removed. We'll use a custom error handler to test for warnings.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static function ( int $errno, string $errstr ): never {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
			throw new \Exception( $errstr, $errno ); // NOSONAR
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();

		self::$mock_global_functions = null;

		parent::tearDown();
	}

	public function test_query_es_with_invalid_type() {
		$result = $this->search_instance->query_es( 'foo' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'indexable-not-found', $result->get_error_code() );
	}

	public function vip_search_filter_ep_index_name_data() {
		return array(
			// Current version number (false uses the real Versioning, which defaults to 1), blog id, expected index name
			'real versioning, blog id'          => array( false, 1, 'vip-123-post-1' ),
			// On "global" indexes, such as users, no blog id will be present
			'real versioning, global index'     => array( false, null, 'vip-123-post' ),
			'version 1, global index'           => array( 1, null, 'vip-123-post' ),
			'version 0, global index'           => array( 0, null, 'vip-123-post' ),
			'version 2, global index'           => array( 2, null, 'vip-123-post-v2' ),
			'version 1, blog id'                => array( 1, 2, 'vip-123-post-2' ),
			'version 2, blog id'                => array( 2, 2, 'vip-123-post-2-v2' ),
			'non-integer version, global index' => array( null, null, 'vip-123-post' ),
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
				[
					'method' => 'POST',
				],
				// The expected result
				true,
			),
			// Regular multiget
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/_mget',
				),
				[
					'method' => 'POST',
				],

				// The expected result
				true,
			),
			// Regular entity multiget
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_doc/_mget',
				),
				[
					'method' => 'POST',
				],
				// The expected result
				true,
			),
			// Regular entity multiget
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_doc/123456',
				),
				[
					'method' => 'DELETE',
				],
				// The expected result
				false,
			),
			// Bulk index
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/_bulk',
				),
				[
					'method' => 'POST',
				],
				// The expected result
				false,
			),
			// Url containing _bulk
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/_bulk/bar?_mget',
				),
				[
					'method' => 'POST',
				],
				// The expected result
				false,
			),
			// Random other url
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_anything',
				),
				[
					'method' => 'GET',
				],
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
	public function test__is_url_query_cacheable( $query, $args, $expected_is_cacheable ) {
		$is_cacheable = $this->search_instance->is_url_query_cacheable( $query['url'], $args );

		$this->assertEquals( $expected_is_cacheable, $is_cacheable );
	}

	/**
	 * Test `ep_index_name` filter for ElasticPress + VIP Search, with versioning
	 *
	 * When current version is 1, the index name should not have a version applied to it
	 *
	 * @dataProvider vip_search_filter_ep_index_name_data
	 */
	public function test__vip_search_filter_ep_index_name( $current_version, $blog_id, $expected_index_name ) {
		$this->init_es();

		$indexable = Indexables::factory()->get( 'post' );

		if ( false !== $current_version ) {
			// Mock the Versioning class so we can control which version it returns
			$stub = $this->getMockBuilder( Versioning::class )
					->onlyMethods( [ 'get_current_version_number' ] )
					->getMock();

			$stub->expects( $this->once() )
					->method( 'get_current_version_number' )
					->with( $indexable )
					->will( $this->returnValue( $current_version ) );

			$this->search_instance->versioning = $stub;
		}

		$index_name = apply_filters( 'ep_index_name', 'index-name', $blog_id, $indexable );

		$this->assertEquals( $expected_index_name, $index_name );
	}

	public function test__vip_search_sends_http_requests_via_helper_functions() {
		$this->define_es_credentials( array( 'https://es-endpoint1:9235' ) );

		self::$mock_global_functions->expects( $this->exactly( 2 ) )
			->method( 'mock_wp_remote_request' )
			->with( $this->callback( function ( $url ) {
				return in_array( $url, [
					'https://es-endpoint1:9235/vip-123-post-1',
					'https://es-endpoint1:9235/vip-123-post-1/_bulk',
				] );
			} ) )
			->willReturn([
				'response' => [ 'code' => 200 ],
				'body'     => '',
			]);

		$this->bulk_index_test_post();
	}

	/**
	 * Assert exact destinations and the common authenticated bulk write payload.
	 *
	 * @param array $requests Captured external HTTP request tuples.
	 * @param array $expected_urls One index-exists request and one bulk request per cluster.
	 */
	private function assert_migration_request_tuples( array $requests, array $expected_urls ): void {
		$urls = array_column( $requests, 'url' );
		sort( $urls );
		sort( $expected_urls );
		$this->assertSame( $expected_urls, $urls );
		$bulk = array_values( array_filter( $requests, static fn( $request ) => str_ends_with( $request['url'], '/_bulk' ) ) );
		$this->assertCount( 2, $bulk );
		$this->assertSame( $bulk[0]['args']['body'], $bulk[1]['args']['body'] );
		$this->assertStringContainsString( 'Test Post', $bulk[0]['args']['body'] );
		foreach ( $bulk as $request ) {
			$this->assertSame( 'POST', $request['args']['method'] );
			$this->assertSame( 'Basic ' . base64_encode( 'foo:bar' ), $request['args']['headers']['Authorization'] );
		}
	}

	public function vip_search_migration_writes_data() {
		return array(
			// Endpoint, extra constants, expected request urls
			'upgrading: writes are mirrored to the next version'          => array(
				'https://es-endpoint:9235',
				array(),
				array( 'https://es-endpoint:9235/vip-123-post-1', 'https://es-endpoint:9235/vip-123-post-1/_bulk', 'https://es-endpoint:9245/vip-123-post-1/_bulk' ),
			),
			'upgraded: writes are mirrored back to the previous version'  => array(
				'https://es-endpoint:9245',
				array( 'VIP_ELASTICSEARCH_VERSION' => '8' ),
				array( 'https://es-endpoint:9245/vip-123-post-1', 'https://es-endpoint:9245/vip-123-post-1/_bulk', 'https://es-endpoint:9235/vip-123-post-1/_bulk' ),
			),
			'testing next version: requests go to the next version host' => array(
				'https://es-endpoint:9235/weirdpath9235',
				array( 'VIP_ELASTICSEARCH_TEST_ES_NEXT' => true ),
				array( 'https://es-endpoint:9245/weirdpath9235/vip-123-post-1', 'https://es-endpoint:9245/weirdpath9235/vip-123-post-1/_bulk', 'https://es-endpoint:9235/weirdpath9235/vip-123-post-1/_bulk' ),
			),
		);
	}

	/**
	 * @dataProvider vip_search_migration_writes_data
	 */
	public function test__vip_search_sends_double_writes_during_migration( $endpoint, $constants, $expected_urls ) {
		$this->define_es_credentials( array( $endpoint ) );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_MIGRATION_IN_PROGRESS', true );

		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		// ElasticPress builds the Authorization header from a global ES_SHIELD constant, which Constant_Mocker can't provide
		add_filter( 'ep_format_request_headers', static fn( $headers ) => array_merge( $headers, array( 'Authorization' => 'Basic ' . base64_encode( 'foo:bar' ) ) ) );

		$requests = array();
		self::$mock_global_functions->expects( $this->exactly( 3 ) )
			->method( 'mock_wp_remote_request' )
			->willReturnCallback( static function ( $url, $args ) use ( &$requests ) {
				$requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '',
				);
			} );

		$this->bulk_index_test_post();
		$this->assert_migration_request_tuples( $requests, $expected_urls );
	}

	public function test__vip_search_filter__ep_global_alias() {
		$this->init_es();

		$indexable = Indexables::factory()->get( 'post' );

		$alias_name = $indexable->get_network_alias();

		$this->assertEquals( 'vip-123-post-all', $alias_name );
	}

	/**
	 * Test the ElasticPress defaults that VIP Search overrides via simple filters
	 */
	public function test__vip_search_filter_defaults() {
		$this->init_es();

		$this->assertEquals( 1, apply_filters( 'ep_default_index_number_of_shards', 5 ), 'Wrong default number of shards' );
		$this->assertEquals( 1, apply_filters( 'ep_default_index_number_of_replicas', 2 ), 'Wrong default number of replicas' );
		// Querying is allowed during bulk re-index
		$this->assertTrue( apply_filters( 'ep_enable_query_integration_during_indexing', false ), 'Query integration should be enabled during indexing' );
		// Indexing of filtered content is disabled by default
		$this->assertFalse( apply_filters( 'ep_allow_post_content_filtered_index', true ), 'Indexing of filtered content should be disabled' );
		$this->assertEquals( 5, apply_filters( 'ep_facet_taxonomies_size', 10000, 'category' ), 'Wrong facet taxonomies size' );
	}

	public function test__vip_search_filter_filter__ep_post_mapping__large_site() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'foo' );
		Constant_Mocker::define( 'VIP_GO_ENV', 'production' );
		$this->init_es();

		// Simulate a large site
		$return_big_count = function ( $counts ) {
			$counts->publish = 2000000;

			return $counts;
		};

		$indexable = Indexables::factory()->get( 'post' );

		add_filter( 'wp_count_posts', $return_big_count );

		$settings = $this->get_index_settings( $indexable );

		$this->assertEquals( 4, $settings['index.number_of_shards'] );
	}

	public function test__vip_search_filter_filter__ep_user_mapping__large_site() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'foo' );
		Constant_Mocker::define( 'VIP_GO_ENV', 'production' );
		$this->init_es();

		// Activate and set-up the feature
		Features::factory()->activate_feature( 'users' );
		Features::factory()->setup_features();

		// Simulate a large site
		$return_big_count = fn () => [
			'avail_roles' => 100,
			'total_users' => 3000000,
		];

		add_filter( 'pre_count_users', $return_big_count );

		$settings = $this->get_index_settings( Indexables::factory()->get( 'user' ) );

		$this->assertEquals( 4, $settings['index.number_of_shards'] );
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

		// The bundled ElasticPress doesn't ship these features, so register stand-ins with the same slugs
		$disabled_feature = $this->register_stub_feature( $slug );
		$allowed_feature  = $this->register_stub_feature( 'vip-test-allowed-feature' );

		try {
			// Activate the features
			Features::factory()->activate_feature( $slug );
			Features::factory()->activate_feature( 'vip-test-allowed-feature' );

			// And attempt to force-enable them via filter
			add_filter( 'ep_feature_active', '__return_true' );

			$this->assertFalse( $disabled_feature->is_active() );
			$this->assertTrue( $allowed_feature->is_active() );
		} finally {
			unset( Features::factory()->registered_features[ $slug ], Features::factory()->registered_features['vip-test-allowed-feature'] );
		}
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
		Constant_Mocker::define( 'EP_SYNC_CHUNK_LIMIT', 200 );

		$this->init_es();

		$this->assertEquals( 200, Constant_Mocker::constant( 'EP_SYNC_CHUNK_LIMIT' ) );
	}

	/**
	 * Test that the ES config constants are set automatically when not already defined and VIP-provided configs are present
	 */
	public function test__vip_search_connection_constants() {
		$this->define_es_credentials( array( 'https://es-endpoint1', 'https://es-endpoint2' ) );

		$this->init_es();

		$this->assertContains( Constant_Mocker::constant( 'EP_HOST' ), Constant_Mocker::constant( 'VIP_ELASTICSEARCH_ENDPOINTS' ) );
		$this->assertEquals( Constant_Mocker::constant( 'ES_SHIELD' ), 'foo:bar' );
	}

	/**
	 * Test that the ES config constants are _not_ set automatically when already defined and VIP-provided configs are present
	 *
	 */
	public function test__vip_search_connection_constants_with_overrides() {
		$this->define_es_credentials( array( 'https://es-endpoint1', 'https://es-endpoint2' ) );

		// Client over-rides - don't fatal
		Constant_Mocker::define( 'EP_HOST', 'https://somethingelse' );
		Constant_Mocker::define( 'ES_SHIELD', 'bar:baz' );

		$this->init_es();

		$this->assertEquals( Constant_Mocker::constant( 'EP_HOST' ), 'https://somethingelse' );
		$this->assertEquals( Constant_Mocker::constant( 'ES_SHIELD' ), 'bar:baz' );
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
				30,
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
			// Opening an index
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_open',
				),
				// The expected timeout
				30,
			),
			// Closing an index
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_close',
				),
				// The expected timeout
				30,
			),
			// Updating index settings
			array(
				// The $query object
				array(
					'url' => 'https://foo.com/index/type/_settings',
				),
				// The expected timeout
				30,
			),
		);
	}

	/**
	 * Test that we correctly calculate the HTTP request timeout value for ES requests
	 *
	 * @dataProvider vip_search_get_http_timeout_for_query_data()
	 */
	public function test__vip_search_get_http_timeout_for_query( $query, $expected_timeout ) {
		$timeout = $this->search_instance->get_http_timeout_for_query( $query, [ 'method' => 'POST' ] );

		$this->assertEquals( $expected_timeout, $timeout );
	}

	/**
	 * Test that instantiating the HealthJob works as expected (files are properly included, init is hooked), and that
	 * the health check is not enabled when not in production
	 */
	public function test__vip_search_setup_healthchecks() {
		Constant_Mocker::define( 'VIP_GO_ENV', '999' );
		$this->init_es();

		$this->search_instance->setup_cron_jobs();
		// Should not have fataled (class was included)

		// Ensure it returns the priority set. Easiest way to to ensure it's not false
		$this->assertTrue( false !== has_action( 'wp_loaded', [ $this->search_instance->healthcheck, 'init' ] ) );
		$this->assertFalse( $this->search_instance->healthcheck->is_enabled() );
	}

	public function vip_search_filter__ep_pre_request_host_passthrough_data() {
		return array(
			'endpoints not defined'  => array( null ),
			'empty endpoint list'    => array( array() ),
			'endpoints not an array' => array( 'Random string' ),
		);
	}

	/**
	 * Test that filter__ep_pre_request_host hands the last host back when there is no usable endpoint list
	 *
	 * @dataProvider vip_search_filter__ep_pre_request_host_passthrough_data
	 */
	public function test__vip_search_filter__ep_pre_request_host_passthrough( $endpoints ) {
		if ( null !== $endpoints ) {
			Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', $endpoints );
		}

		$this->assertEquals( 'test', $this->search_instance->filter__ep_pre_request_host( 'test', 0 ) );
	}

	/**
	 * Test that checks both single and multi-host retries
	 */
	public function test__vip_search_filter__ep_pre_request_host() {
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
	 * Test for making sure the round robin function returns the next array value
	 */
	public function test__vip_search_get_next_host() {
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

	public function vip_search_get_random_host_data() {
		$hosts = array( 'test0', 'test1', 'test2', 'test3' );

		return array(
			// Hosts, possible results
			'hosts'           => array( $hosts, $hosts ),
			'no hosts'        => array( array(), array( null ) ),
			'hosts not array' => array( false, array( null ) ),
		);
	}

	/**
	 * Test for making sure the load balance functionality works
	 *
	 * @dataProvider vip_search_get_random_host_data
	 */
	public function test__vip_search_get_random_host( $hosts, $possible_results ) {
		$this->assertContains( $this->search_instance->get_random_host( $hosts ), $possible_results );
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

	public function is_query_integration_enabled_data() {
		return array(
			// Constants, option enabled, `es` query param set, expected
			'default (no options/constants)' => array( array(), false, false, false ),
			'vip_enable_vip_search_query_integration option' => array( array(), true, false, true ),
			'VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION constant' => array( array( 'VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION' => true ), false, false, true ),
			'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION constant' => array( array( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION' => true ), false, false, true ),
			'query param'                    => array( array(), false, true, true ),
		);
	}

	/**
	 * Ensure is_query_integration_enabled() considers the option, constants and query param, and that
	 * es-wp-query is only loaded when query integration is enabled
	 *
	 * @dataProvider is_query_integration_enabled_data
	 */
	public function test__is_query_integration_enabled( $constants, $option, $query_param, $expected ) {
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		if ( $option ) {
			update_option( 'vip_enable_vip_search_query_integration', true );
		}

		if ( $query_param ) {
			$_GET[ Search::QUERY_INTEGRATION_FORCE_ENABLE_KEY ] = true;
		}

		try {
			$this->assertSame( $expected, Search::is_query_integration_enabled() );
			$this->assertSame( $expected, Search::should_load_es_wp_query() );
		} finally {
			unset( $_GET[ Search::QUERY_INTEGRATION_FORCE_ENABLE_KEY ] );
		}
	}

	public function is_network_mode_data() {
		return array(
			// EP_IS_NETWORK value (null to leave undefined), expected
			'default'        => array( null, false ),
			'constant true'  => array( true, true ),
			'constant false' => array( false, false ),
		);
	}

	/**
	 * @dataProvider is_network_mode_data
	 */
	public function test_is_network_mode( $constant, $expected ) {
		if ( null !== $constant ) {
			Constant_Mocker::define( 'EP_IS_NETWORK', $constant );
		}

		$this->assertSame( $expected, Search::is_network_mode() );
	}

	/*
	 * Ensure that filters disabling query integration are honored
	 */
	public function test__ep_skip_query_integration_filter() {
		// Set constants to enable query integration
		Constant_Mocker::define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );

		// We pass in `true` as the starting value for the filter, indicating it should be skipped. We expect that `true` comes back out,
		// even though query integration is enabled, which indicates that we're properly respecting other filters that have already decided
		// this query should be skipped
		$this->assertTrue( Search::ep_skip_query_integration( true ) );
	}

	/*
	 * Ensure that EP query integration is disabled by default
	 */
	public function test__ep_skip_query_integration_default() {
		$this->assertTrue( Search::ep_skip_query_integration( false ) );
	}

	/*
	 * Ensure ratelimiting works properly with ep_skip_query_integration filter
	 */
	public function test__rate_limit_ep_query_integration__triggers() {
		$es = new Search();
		$es->init();

		$this->assertFalse( $es->rate_limit_ep_query_integration( false ), 'the default value should be false' );
		$this->assertTrue( $es->rate_limit_ep_query_integration( true ), 'should honor filters that skip query integrations' );

		// Force ratelimiting to apply
		$es::$max_query_count = 0;

		// Force this request to be ratelimited
		$es::$query_db_fallback_value = 11;
		wp_cache_set( $this->search_instance::QUERY_COUNT_CACHE_KEY, 1, $this->search_instance::SEARCH_CACHE_GROUP );

		// ep_skip_query_integration should be true if ratelimited
		$this->assertTrue( $es->rate_limit_ep_query_integration( false ), 'should return true if the query is rate-limited' );
		wp_cache_delete( $this->search_instance::QUERY_COUNT_CACHE_KEY, $this->search_instance::SEARCH_CACHE_GROUP );
	}

	public function test__rate_limit_ep_query_integration__handles_start_correctly() {
		/** @var MockObject&Search */
		$partially_mocked_search = $this->getMockBuilder( Search::class )
			->onlyMethods( [ 'handle_query_limiting_start_timestamp', 'maybe_alert_for_prolonged_query_limiting' ] )
			->getMock();
		$partially_mocked_search->init();

		// Force rate-limiting to apply
		$partially_mocked_search::$max_query_count = 0;

		// Force this request to be rate-limited
		$partially_mocked_search::$query_db_fallback_value = 11;
		wp_cache_set( $this->search_instance::QUERY_COUNT_CACHE_KEY, 1, $this->search_instance::SEARCH_CACHE_GROUP );

		$partially_mocked_search->expects( $this->once() )->method( 'handle_query_limiting_start_timestamp' );
		$partially_mocked_search->expects( $this->once() )->method( 'maybe_alert_for_prolonged_query_limiting' );

		$partially_mocked_search->rate_limit_ep_query_integration( false );
		wp_cache_delete( $this->search_instance::QUERY_COUNT_CACHE_KEY, $this->search_instance::SEARCH_CACHE_GROUP );
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

		$should = Search::should_load_es_wp_query();

		$this->assertFalse( $should );
	}

	/**
	 * Ensure the incrementor for tracking request counts behaves properly
	 */
	public function test__query_count_incr() {
		$query_count_incr = get_class_method_as_public( Search::class, 'query_count_incr' );

		// Reset cache key
		wp_cache_delete( $this->search_instance::QUERY_COUNT_CACHE_KEY, $this->search_instance::SEARCH_CACHE_GROUP );

		$this->assertEquals( 1, $query_count_incr->invokeArgs( $this->search_instance, [] ), 'initial value should be 1' );

		for ( $i = 2; $i < 10; $i++ ) {
			$this->assertEquals( $i, $query_count_incr->invokeArgs( $this->search_instance, [] ), 'value should increment with loop' );
		}
	}

	public function test__truncate_search_string_length__user_no_cap() {
		$expected_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStX';
		$provided_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpggg5oMk1nWsx5fJ0B3bAFYKt1Y5dOA0Q4lQUqj8mf1LjcmR73wQwujc1GQfgCKj9X9Ktr6LrDtN5zAJFQboAJa7fZ9AiGxbJqUrLFs1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpggg5oMk1nWsx5fJ0B3bAFYKt1Y5dOA0Q4lQUqj8mf1LjcmR73wQwujc1GQfgCKj9X9Ktr6LrDtN5zAJFQboAJa7fZ9AiGxbJqUrLFs88';

		$wp_query_mock = new \WP_Query();

		$wp_query_mock->set( 's', $provided_search_string );
		$wp_query_mock->is_search = true;

		$this->search_instance->truncate_search_string_length( $wp_query_mock );

		$this->assertEquals( $expected_search_string, $wp_query_mock->get( 's' ) );
	}

	public function test__truncate_search_string_length__user_with_cap() {
		$admin_user = $this->factory()->user->create( [
			'user_email' => 'admin@automattic.com',
			'user_login' => 'vip_admin',
			'role'       => 'administrator',
		] );

		wp_set_current_user( $admin_user );

		$expected_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpggg5oMk1nWsx5fJ0B3bAFYKt1Y5dOA0Q4lQUqj8mf1LjcmR73wQwujc1GQfgCKj9X9Ktr6LrDtN5zAJFQboAJa7fZ9AiGxbJqUrLFs1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXB';
		$provided_search_string = '1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpggg5oMk1nWsx5fJ0B3bAFYKt1Y5dOA0Q4lQUqj8mf1LjcmR73wQwujc1GQfgCKj9X9Ktr6LrDtN5zAJFQboAJa7fZ9AiGxbJqUrLFs1nAtu5t4QRo9XmU5VeKFOCTfQN62FrbvvoQXkU1782KOThAlt50NipM7V4dZNGG4eO54HsOQlJaBPStXPRoxWPHqdrHGsGkNQJJshYseaePxCJuGmY7kYp941TUoNF3GhSBEzjajNu0iwdCWrPMLxSJ5XXBltNM9of2LKvwa1hNPOXLka1tyAi8PSZlS53RbGhv7egKOYPyyPpR6mZlzJhx6nXXlZ5t3BtRdQOIvGho6HjdYwdd1hMyHHv1qpggg5oMk1nWsx5fJ0B3bAFYKt1Y5dOA0Q4lQUqj8mf1LjcmR73wQwujc1GQfgCKj9X9Ktr6LrDtN5zAJFQboAJa7fZ9AiGxbJqUrLFs88';

		$wp_query_mock = new \WP_Query();

		$wp_query_mock->set( 's', $provided_search_string );
		$wp_query_mock->is_search = true;

		$this->search_instance->truncate_search_string_length( $wp_query_mock );

		$this->assertEquals( $expected_search_string, $wp_query_mock->get( 's' ) );
	}

	public function ep_total_field_limit_data() {
		return array(
			// Filtered field limit, expected limit, expect _doing_it_wrong()
			'absolute maximum is 20000'         => array( 1000000, 20000, true ),
			'values under the maximum are kept' => array( 777, 777, false ),
		);
	}

	/**
	 * @dataProvider ep_total_field_limit_data
	 */
	public function test__ep_total_field_limit( $field_limit, $expected, $expect_doing_it_wrong ) {
		if ( $expect_doing_it_wrong ) {
			$this->setExpectedIncorrectUsage( 'limit_field_limit' );
		}

		$this->init_es();

		add_filter( 'ep_total_field_limit', fn() => $field_limit );

		$this->assertEquals( $expected, apply_filters( 'ep_total_field_limit', 5000 ) );
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
		$this->init_es( false );

		$post = new stdClass();

		$filtered_taxonomies = apply_filters( 'ep_sync_taxonomies', $input_taxonomies, $post );

		$input_taxonomy_names    = wp_list_pluck( $input_taxonomies, 'name' );
		$filtered_taxonomy_names = wp_list_pluck( $filtered_taxonomies, 'name' );

		// No change expected
		$this->assertEquals( $input_taxonomy_names, $filtered_taxonomy_names );
	}

	public function test__filter__ep_sync_taxonomies_added() {
		$this->init_es( false );

		$post = new stdClass();

		$start_taxonomies = array(
			(object) array(
				'name' => 'category',
			),
		);

		add_filter(
			'vip_search_post_taxonomies_allow_list',
			function ( $taxonomies ) {
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
		$this->init_es();

		$post = new stdClass();

		$start_taxonomies = array(
			(object) array(
				'name' => 'category',
			),
			(object) array(
				'name' => 'post_tag',
			),
		);

		add_filter( 'vip_search_post_taxonomies_allow_list', fn() => [ 'post_tag' ] );

		$filtered_taxonomies = apply_filters( 'ep_sync_taxonomies', $start_taxonomies, $post );

		// Pull out just the names, for easier comparison
		$filtered_taxonomy_names = wp_list_pluck( $filtered_taxonomies, 'name' );
		$expected_taxonomy_names = [ 'post_tag' ];

		// Should now not include the removed taxonomies
		$this->assertEquals( $expected_taxonomy_names, $filtered_taxonomy_names );
	}

	public function is_jetpack_migration_data() {
		return array(
			// VIP_SEARCH_MIGRATION_SOURCE value (null to leave undefined), expected
			'jetpack'         => array( 'jetpack', true ),
			'no constant'     => array( null, false ),
			'different value' => array( 'foo', false ),
		);
	}

	/**
	 * @dataProvider is_jetpack_migration_data
	 */
	public function test__is_jetpack_migration( $source, $expected ) {
		if ( null !== $source ) {
			Constant_Mocker::define( 'VIP_SEARCH_MIGRATION_SOURCE', $source );
		}

		$this->assertSame( $expected, $this->search_instance->is_jetpack_migration() );
	}

	public function filter__ep_prepare_meta_data_allow_list_data() {
		return array(
			'list'              => array(
				array(
					'random_post_meta',
					'another_one',
					'third',
				),
			),
			// Only keys set to true are allowed
			'associative array' => array(
				array(
					'random_post_meta' => true,
					'another_one'      => true,
					'skipped'          => false,
					'skipped_another'  => 4,
					'skipped_string'   => 'Wooo',
					'third'            => true,
				),
			),
		);
	}

	/**
	 * @dataProvider filter__ep_prepare_meta_data_allow_list_data
	 */
	public function test__filter__ep_prepare_meta_data_allow_list_should_be_respected_by_default( $allow_list ) {
		add_filter( 'vip_search_post_meta_allow_list', fn() => $allow_list );

		$allowed_meta = array(
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

		$post_meta = array_merge(
			$allowed_meta,
			array(
				'skipped'                       => array( 'Skip' ),
				'skipped_another'               => array( 'Skip' ),
				'skipped_string'                => array( 'Skip' ),
				'random_thing_not_allow_listed' => array( 'Missing' ),
			)
		);

		$meta = $this->search_instance->filter__ep_prepare_meta_data( $post_meta, $this->stub_post() );

		$this->assertEquals( $allowed_meta, $meta );
	}

	/**
	 * This tests the correct implementation of the ep_$indexable_mapping filters, but note that these filters
	 * operate on the mapping and settings together - EP doesn't yet distinguish between them
	 */
	public function test__filter__ep_indexable_mapping() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'dfw' );
		$this->init_es();

		// Should apply to all indexables
		$indexables = Indexables::factory()->get_all();

		// Make sure the above worked
		$this->assertNotEmpty( $indexables, 'Indexables array was empty' );

		foreach ( $indexables as $indexable ) {
			$settings = $this->get_index_settings( $indexable );

			$this->assertEquals( 'dfw', $settings['index.routing.allocation.include.dc'], 'Indexable ' . $indexable->slug . ' has the wrong routing allocation' );
		}
	}

	public function test__filter__ep_indexable_mapping_invalid_datacenter() {
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'foo' );
		$this->init_es();

		// Should apply to all indexables
		$indexables = Indexables::factory()->get_all();

		// Make sure the above worked
		$this->assertNotEmpty( $indexables, 'Indexables array was empty' );

		foreach ( $indexables as $indexable ) {
			$settings = $this->get_index_settings( $indexable );

			// Datacenter was invalid, so it should not have added the allocation settings
			$this->assertArrayNotHasKey( 'index.routing.allocation.include.dc', $settings, 'Indexable ' . $indexable->slug . ' incorrectly defined the allocation settings' );
		}
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
	 */
	public function test__get_index_routing_allocation_include_dc_from_endpoints( $endpoints, $expected ) {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', $endpoints );

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
		$origin_dc = $this->search_instance->get_origin_dc_from_es_endpoint( $host );

		$this->assertEquals( $expected, $origin_dc );
	}

	public function get_post_meta_allow_list__combinations_data() {
		$jetpack_defaults = array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, Search::JETPACK_POST_META_DEFAULT_ALLOW_LIST );

		// Jetpack migration, keys added by the VIP Search filter, keys added by the Jetpack filter, expected
		return [
			'jetpack migration: no filters'             => [ true, null, null, $jetpack_defaults ],
			'jetpack migration: VIP filter'             => [ true, [ 'foo' ], null, array_merge( $jetpack_defaults, [ 'foo' ] ) ],
			'jetpack migration: VIP and JP filters'     => [ true, [ 'foo' ], [ 'bar' ], array_merge( $jetpack_defaults, [ 'bar', 'foo' ] ) ],
			'jetpack migration: empty VIP filter, JP filter' => [ true, [], [ 'bar' ], array_merge( $jetpack_defaults, [ 'bar' ] ) ],
			'jetpack migration: JP filter'              => [ true, null, [ 'bar' ], array_merge( $jetpack_defaults, [ 'bar' ] ) ],
			'not jetpack migration: no filters'         => [ false, null, null, Search::POST_META_DEFAULT_ALLOW_LIST ],
			'not jetpack migration: VIP filter'         => [ false, [ 'foo' ], null, array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, [ 'foo' ] ) ],
			'not jetpack migration: VIP and JP filters' => [ false, [ 'foo' ], [ 'bar' ], array_merge( Search::POST_META_DEFAULT_ALLOW_LIST, [ 'foo' ] ) ],
			'not jetpack migration: empty VIP filter, JP filter' => [ false, [], [ 'bar' ], Search::POST_META_DEFAULT_ALLOW_LIST ],
			'not jetpack migration: JP filter'          => [ false, null, [ 'bar' ], Search::POST_META_DEFAULT_ALLOW_LIST ],
		];
	}

	/**
	 * @dataProvider get_post_meta_allow_list__combinations_data
	 */
	public function test__get_post_meta_allow_list__combinations( $is_jetpack_migration, $vip_search_keys, $jetpack_added, $expected ) {
		if ( $is_jetpack_migration ) {
			Constant_Mocker::define( 'VIP_SEARCH_MIGRATION_SOURCE', 'jetpack' );
		}

		remove_all_filters( 'vip_search_post_meta_allow_list' );
		remove_all_filters( 'jetpack_sync_post_meta_whitelist' );
		$this->init_es();

		if ( is_array( $vip_search_keys ) ) {
			add_filter( 'vip_search_post_meta_allow_list', function ( $post_meta ) use ( $vip_search_keys ) {
				return array_merge( $post_meta, $vip_search_keys );
			});
		}

		if ( is_array( $jetpack_added ) ) {
			add_filter( 'jetpack_sync_post_meta_whitelist', function ( $post_meta ) use ( $jetpack_added ) {
				return array_merge( $post_meta, $jetpack_added );
			});
		}

		$result = $this->search_instance->get_post_meta_allow_list( $this->stub_post() );

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
	 * @dataProvider get_post_meta_allow_list__processing_array_data
	 */
	public function test__get_post_meta_allow_list__processing_array( $returned_by_filter, $expected ) {
		add_filter( 'vip_search_post_meta_allow_list', function () use ( $returned_by_filter ) {
			return $returned_by_filter;
		}, 0);

		$result = $this->search_instance->get_post_meta_allow_list( $this->stub_post() );

		$this->assertEquals( $expected, $result );
	}

	public function ep_skip_post_meta_sync_data() {
		return array(
			// Value from previous filters, filtered allow list (null for the default), expected
			'meta not in allow list'         => array( false, null, true ),
			'meta in allow list'             => array( false, [ 'random_key' ], false ),
			'a previous filter skipped sync' => array( true, [ 'random_key' ], true ),
		);
	}

	/**
	 * @dataProvider ep_skip_post_meta_sync_data
	 */
	public function test__ep_skip_post_meta_sync_filter( $previous_skip, $allow_list, $expected ) {
		if ( null !== $allow_list ) {
			add_filter( 'vip_search_post_meta_allow_list', fn() => $allow_list );
		}

		$this->init_es();

		$this->assertSame( $expected, apply_filters( 'ep_skip_post_meta_sync', $previous_skip, $this->stub_post(), 40, 'random_key', 'random_value' ) );
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
	 * @dataProvider filter__ep_prepare_meta_allowed_protected_keys__should_use_post_meta_allow_list_data
	 */
	public function test__filter__ep_prepare_meta_allowed_protected_keys__should_use_post_meta_allow_list( $default_ep_protected_keys, $added_keys, $expected ) {
		self::assertFalse( defined( 'VIP_SEARCH_MIGRATION_SOURCE' ) );

		add_filter( 'vip_search_post_meta_allow_list', function ( $meta_keys ) use ( $added_keys ) {
			return array_merge( $meta_keys, $added_keys );
		}, 0);

		$this->init_es();

		$result = \apply_filters( 'ep_prepare_meta_allowed_protected_keys', $default_ep_protected_keys, $this->stub_post() );

		$this->assertEquals( $expected, $result );
	}

	public function test__maybe_alert_for_average_queue_time__sends_notification() {
		$application_id      = 123;
		$application_url     = 'http://example.org';
		$average_queue_value = 3601;
		$queue_count_value   = 1;
		$longest_queue_value = $average_queue_value;
		$expected_message    = "Average index queue wait time for application {$application_id} - {$application_url} is currently {$average_queue_value} seconds. There are {$queue_count_value} items in the queue and the oldest item is {$longest_queue_value} seconds old";
		$expected_level      = 2;

		$alerts_mocked   = $this->mock_alerts( $this->search_instance );
		$queue_mocked    = $this->createMock( Queue::class );
		$indexables_mock = $this->createMock( Indexables::class );

		$this->search_instance->queue      = $queue_mocked;
		$this->search_instance->indexables = $indexables_mock;

		$indexables_mock->method( 'get' )
			->willReturn( $this->createMock( Indexable::class ) );

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

		/** @var MockObject&Search */
		$partially_mocked_search = $this->getMockBuilder( Search::class )
			->onlyMethods( [ 'get_current_field_count' ] )
			->getMock();

		$alerts_mocked   = $this->mock_alerts( $partially_mocked_search );
		$indexables_mock = $this->createMock( Indexables::class );

		$partially_mocked_search->indexables = $indexables_mock;

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
	 */
	public function test__maybe_alert_for_prolonged_query_limiting( $difference, $should_alert ) {
		$expected_level = 2;

		$time = time();

		if ( false !== $difference ) {
			$query_limited_start = $time - $difference;
			wp_cache_set( Search::QUERY_RATE_LIMITED_START_CACHE_KEY, $query_limited_start, Search::SEARCH_CACHE_GROUP );
		}

		$this->search_instance->set_time( $time );

		$alerts_mocked = $this->mock_alerts( $this->search_instance );

		$alerts_mocked->expects( $should_alert ? $this->once() : $this->never() )
			->method( 'send_to_chat' )
			->with( '#vip-go-es-alerts', $this->anything(), $expected_level );

		// trigger_error is only called if an alert should happen
		if ( $should_alert ) {
			$this->expectException( \Exception::class );
			$this->expectExceptionMessage(
				sprintf(
					'Application 123 - http://example.org has had its Elasticsearch queries rate-limited for %d seconds. Half of traffic is diverted to the database when queries are rate-limited.',
					$difference
				)
			);
		}

		$this->search_instance->maybe_alert_for_prolonged_query_limiting();
		$this->search_instance->reset_time();
	}

	public function vip_search_ratelimiting_filter_data() {
		return array(
			// Filter, filtered value, expected _doing_it_wrong() message
			'period: not numeric'            => [ 'vip_search_ratelimit_period', '30.ffr', 'vip_search_ratelimit_period should be an integer.' ],
			'period: too low'                => [ 'vip_search_ratelimit_period', 0, 'vip_search_ratelimit_period should not be set below 60 seconds.' ],
			'period: too high'               => [ 'vip_search_ratelimit_period', PHP_INT_MAX, 'vip_search_ratelimit_period should not be set above 7200 seconds.' ],
			'max query count: not numeric'   => [ 'vip_search_max_query_count', '30.ffr', 'vip_search_max_query_count should be an integer.' ],
			'max query count: too low'       => [ 'vip_search_max_query_count', 0, 'vip_search_max_query_count should not be below 10 queries per second.' ],
			'max query count: too high'      => [ 'vip_search_max_query_count', PHP_INT_MAX, 'vip_search_max_query_count should not exceed 500 queries per second.' ],
			'db fallback value: not numeric' => [ 'vip_search_query_db_fallback_value', '30.ffr', 'vip_search_query_db_fallback_value should be an integer.' ],
			'db fallback value: too low'     => [ 'vip_search_query_db_fallback_value', 0, 'vip_search_query_db_fallback_value should be between 1 and 10.' ],
			'db fallback value: too high'    => [ 'vip_search_query_db_fallback_value', PHP_INT_MAX, 'vip_search_query_db_fallback_value should be between 1 and 10.' ],
		);
	}

	/**
	 * @dataProvider vip_search_ratelimiting_filter_data
	 */
	public function test__filter__vip_search_ratelimiting_validation( $filter, $value, $expected_message ) {
		add_filter( $filter, fn() => $value );

		$this->setExpectedIncorrectUsage( 'add_filter' );
		$messages = $this->get_doing_it_wrong_messages( [ $this->search_instance, 'apply_settings' ] );

		$this->assertContains( $expected_message, $messages );
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
		$this->mock_logger()->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( 'error' ),
				$this->equalTo( 'search_query_error' ),
				$this->equalTo( $expected_message ),
				$this->anything()
			);

		$this->search_instance->ep_handle_failed_request( null, $response, [], null, null, '' );
	}

	/**
	 * Ensure when actions from the skiplist are called, they do not get logged as a failed request.
	 */
	public function test__ep_handle_failed_request__skiplist() {
		$this->mock_logger()->expects( $this->never() )->method( 'log' );

		$skiplist = [
			'index_exists',
			'get',
		];

		foreach ( $skiplist as $item ) {
			$this->search_instance->ep_handle_failed_request( null, 404, [], $item, null, '' );
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
		wp_cache_set( $this->search_instance::QUERY_RATE_LIMITED_START_CACHE_KEY, time(), $this->search_instance::SEARCH_CACHE_GROUP );

		$this->mock_logger()->expects( $this->never() )->method( 'log' );

		$this->search_instance->maybe_log_query_ratelimiting_start();
	}

	public function test__maybe_log_query_ratelimiting_start_should_log_if_ratelimiting_not_already_started() {
		$this->init_es();

		$this->mock_logger()->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( 'warning' ),
				$this->equalTo( 'search_query_rate_limiting' ),
				$this->equalTo(
					'Application 123 - http://example.org has triggered Elasticsearch query rate-limiting, which will last up to 300 seconds. Subsequent or repeat occurrences are possible. Half of traffic is diverted to the database when queries are rate-limited.'
				),
				$this->anything()
			);

		$this->search_instance->maybe_log_query_ratelimiting_start();
	}

	public function test__ep_indexable_post_types_should_return_the_passed_value_if_not_array() {
		// Ensure ElasticPress is ready
		do_action( 'plugins_loaded' );

		// Protected content must be active before init() for the filter to be registered
		Features::factory()->activate_feature( 'protected_content' );

		$es = new Search();
		$es->init();

		$this->assertSame( 9999, has_filter( 'ep_indexable_post_types', [ $es, 'add_attachment_to_ep_indexable_post_types' ] ) );

		$this->assertEquals( 'testing', apply_filters( 'ep_indexable_post_types', 'testing' ) );
		$this->assertEquals( 65, apply_filters( 'ep_indexable_post_types', 65 ) );
		$this->assertEquals( null, apply_filters( 'ep_indexable_post_types', null ) );
		$this->assertEquals( new stdClass(), apply_filters( 'ep_indexable_post_types', new stdClass() ) );
	}

	public function test__ep_indexable_post_types_should_append_attachment_to_array() {
		// Ensure ElasticPress is ready
		do_action( 'plugins_loaded' );

		Features::factory()->activate_feature( 'protected_content' );

		$es = new Search();
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

		Features::factory()->activate_feature( 'protected_content' );

		$this->assertTrue( $this->search_instance->is_protected_content_enabled() );
	}

	public function test__maybe_enable_ep_query_logging_no_cap() {
		wp_set_current_user( 0 );

		$this->init_es();

		$this->assertFalse( Constant_Mocker::defined( 'WP_EP_DEBUG' ) );
	}

	public function test__maybe_enable_ep_query_logging_has_cap() {
		$super_admin = $this->factory()->user->create_and_get( array( 'role' => 'administrator' ) );
		wp_set_current_user( $super_admin->ID );

		$this->init_es();

		$this->assertTrue( Constant_Mocker::defined( 'WP_EP_DEBUG' ) );
		$this->assertTrue( Constant_Mocker::constant( 'WP_EP_DEBUG' ) );
	}

	public function test__maybe_enable_ep_query_logging_filtered_cap() {
		add_filter( 'vip_search_dev_tools_cap', function () {
			return 'edit_posts';
		} );
		$editor = $this->factory()->user->create_and_get( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor->ID );

		$this->init_es();

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
			[
				'input'    => 10001,
				'expected' => Search::MAX_RESULT_WINDOW,
			],
			[
				'input'    => 1000000,
				'expected' => Search::MAX_RESULT_WINDOW,
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

	public function are_es_constants_defined_data() {
		return array(
			'no constants'   => array( array(), false ),
			'all constants'  => array(
				array(
					'VIP_ELASTICSEARCH_ENDPOINTS' => [ 'endpoint' ],
					'VIP_ELASTICSEARCH_USERNAME'  => 'foo',
					'VIP_ELASTICSEARCH_PASSWORD'  => 'bar',
				),
				true,
			),
			'empty password' => array(
				array(
					'VIP_ELASTICSEARCH_ENDPOINTS' => [ 'endpoint' ],
					'VIP_ELASTICSEARCH_USERNAME'  => 'foo',
					'VIP_ELASTICSEARCH_PASSWORD'  => '',
				),
				false,
			),
			'no username'    => array(
				array(
					'VIP_ELASTICSEARCH_ENDPOINTS' => [ 'endpoint' ],
					'VIP_ELASTICSEARCH_PASSWORD'  => 'bar',
				),
				false,
			),
			'no endpoints'   => array(
				array(
					'VIP_ELASTICSEARCH_ENDPOINTS' => [],
					'VIP_ELASTICSEARCH_USERNAME'  => 'foo',
					'VIP_ELASTICSEARCH_PASSWORD'  => 'bar',
				),
				false,
			),
		);
	}

	/**
	 * @dataProvider are_es_constants_defined_data
	 */
	public function test__are_es_constants_defined( $constants, $expected ) {
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		$this->assertSame( $expected, Search::are_es_constants_defined() );
	}

	public function filter_ep_enable_do_weighting_data() {
		return array(
			// Filter added to ep_weighting_configuration_for_search, weight config, expected
			'default, no weighting'  => array( null, [], false ),
			'anonymous function'     => array( static fn( $weight_config ) => $weight_config, [], true ),
			'class method'           => array(
				[
					new class() {
						public function filter( $weight_config ) {
							return $weight_config;
						}
					},
					'filter',
				],
				[],
				true,
			),
			'weight config provided' => array( null, [ 'foo' => 'bar' ], true ),
		);
	}

	/**
	 * @dataProvider filter_ep_enable_do_weighting_data
	 */
	public function test__filter_ep_enable_do_weighting( $weighting_filter, $weight_config, $expected ) {
		$this->search_instance->init();

		if ( $weighting_filter ) {
			add_filter( 'ep_weighting_configuration_for_search', $weighting_filter );
		}

		$this->assertSame( $expected, apply_filters( 'ep_enable_do_weighting', true, $weight_config, [], [] ) );
	}

	public function filter_ep_enable_do_weighting_custom_search_results_data() {
		return array(
			// Cached custom results existence, expected
			'no custom search results' => array( '0', false ),
			'custom search results'    => array( '1', true ),
		);
	}

	/**
	 * @dataProvider filter_ep_enable_do_weighting_custom_search_results_data
	 */
	public function test__filter_ep_enable_do_weighting__custom_search_results( $custom_results_existence, $expected ) {
		// Ensure ElasticPress is ready
		do_action( 'plugins_loaded' );

		$this->search_instance->init();

		Features::factory()->activate_feature( 'searchordering' );
		update_option( 'vip_custom_results_existence', $custom_results_existence );

		$this->assertSame( $expected, apply_filters( 'ep_enable_do_weighting', true, [], [], [] ) );
	}

	public function test__set_custom_results_existence_cache() {
		// Ensure ElasticPress is ready
		do_action( 'plugins_loaded' );

		$this->search_instance->init();

		Features::factory()->activate_feature( 'searchordering' );
		// Activation updates settings; post type registration normally runs on init.
		Features::factory()->get_registered_feature( 'searchordering' )->register_post_type();

		try {
			$post = wp_insert_post( [
				'post_type'   => 'ep-pointer',
				'post_status' => 'publish',
				'post_title'  => 'Test CSR',
			] );

			$this->assertGreaterThan( 0, $post );
			$this->assertEquals( '1', get_option( 'vip_custom_results_existence' ) );

			wp_trash_post( $post );

			$this->assertEquals( '0', get_option( 'vip_custom_results_existence' ) );

			wp_insert_post( [
				'post_type'   => 'ep-pointer',
				'post_status' => 'publish',
				'post_title'  => 'Test CSR 2',
			] );
			$post3 = wp_insert_post( [
				'post_type'   => 'ep-pointer',
				'post_status' => 'publish',
				'post_title'  => 'Test CSR 3',
			] );
			wp_trash_post( $post3 );

			$this->assertEquals( '1', get_option( 'vip_custom_results_existence' ) );
		} finally {
			// Post types and taxonomies are not reset between tests.
			unregister_taxonomy( SearchOrdering::TAXONOMY_NAME );
			unregister_post_type( SearchOrdering::POST_TYPE_NAME );
		}
	}

	public function filter__strips_ngram_analysis_data() {
		$ngram_filters = array(
			'ep_ngram_filter' => array(
				'type'     => 'ngram',
				'min_gram' => 3,
				'max_gram' => 15,
			),
			'edge_ngram'      => array(
				'type'     => 'edge_ngram',
				'min_gram' => 3,
				'max_gram' => 10,
			),
		);

		return array(
			// Hook, analysis settings, expected remaining names per analysis section
			'config mapping: ngram filter'    => array(
				'ep_config_mapping',
				array( 'filter' => $ngram_filters ),
				array( 'filter' => array( 'edge_ngram' ) ),
			),
			// Custom names: filters are matched by type and analyzers by the filters they reference, not by name.
			'config mapping: analyzers referencing removed filters' => array(
				'ep_config_mapping',
				array(
					'filter'   => array(
						'custom_ngram_filter' => $ngram_filters['ep_ngram_filter'],
					),
					'analyzer' => array(
						// Removed, because it references custom_ngram_filter
						'my_custom_analyzer' => array(
							'type'      => 'custom',
							'tokenizer' => 'standard',
							'filter'    => array( 'lowercase', 'asciifolding', 'custom_ngram_filter' ),
						),
						'ep_ngram_search'    => array(
							'type'      => 'custom',
							'tokenizer' => 'standard',
							'filter'    => array( 'lowercase', 'asciifolding' ),
						),
						'default'            => array(
							'tokenizer' => 'standard',
							'filter'    => array( 'lowercase' ),
						),
					),
				),
				array(
					'filter'   => array(),
					'analyzer' => array( 'ep_ngram_search', 'default' ),
				),
			),
			'config mapping: ngram tokenizer' => array(
				'ep_config_mapping',
				array(
					'tokenizer' => array(
						'custom_ngram_tokenizer' => array(
							'type'     => 'ngram',
							'min_gram' => 2,
							'max_gram' => 10,
						),
						'standard'               => array(
							'type' => 'standard',
						),
					),
				),
				array( 'tokenizer' => array( 'standard' ) ),
			),
			'config mapping: analyzers referencing removed tokenizers' => array(
				'ep_config_mapping',
				array(
					'tokenizer' => array(
						'my_ngram_tokenizer' => array(
							'type'     => 'ngram',
							'min_gram' => 2,
							'max_gram' => 10,
						),
					),
					'analyzer'  => array(
						'custom_analyzer' => array(
							'type'      => 'custom',
							'tokenizer' => 'my_ngram_tokenizer',
							'filter'    => array( 'lowercase' ),
						),
						'default'         => array(
							'tokenizer' => 'standard',
							'filter'    => array( 'lowercase' ),
						),
					),
				),
				array( 'analyzer' => array( 'default' ) ),
			),
			'indexable mapping: ngram filter' => array(
				'ep_post_mapping',
				array( 'filter' => $ngram_filters ),
				array( 'filter' => array( 'edge_ngram' ) ),
			),
		);
	}

	/**
	 * @dataProvider filter__strips_ngram_analysis_data
	 */
	public function test__filter__strips_ngram_analysis( $hook, $analysis, $expected_names ) {
		// A valid datacenter keeps filter__ep_indexable_mapping() from alerting
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'dfw' );
		$this->init_es();

		$filtered = apply_filters( $hook, array( 'settings' => array( 'analysis' => $analysis ) ), 'test-index' );

		foreach ( $expected_names as $section => $names ) {
			$this->assertSame( $names, array_keys( $filtered['settings']['analysis'][ $section ] ), "Unexpected {$section} entries left" );
		}
	}

	public function filter__ep_post_mapping_strips_ngram_field_data() {
		$ngram_field = array(
			'type'            => 'text',
			'analyzer'        => 'ep_ngram',
			'search_analyzer' => 'ep_ngram_search',
		);

		return array(
			// post_content mapping, expected post_content mapping
			'ngram field is removed'                   => array(
				array(
					'type'   => 'text',
					'fields' => array(
						'ngram' => $ngram_field,
						'raw'   => array(
							'type' => 'keyword',
						),
					),
				),
				array(
					'type'   => 'text',
					'fields' => array(
						'raw' => array(
							'type' => 'keyword',
						),
					),
				),
			),
			'fields key removed when only ngram field' => array(
				array(
					'type'   => 'text',
					'fields' => array(
						'ngram' => $ngram_field,
					),
				),
				array( 'type' => 'text' ),
			),
			'no fields'                                => array(
				array( 'type' => 'text' ),
				array( 'type' => 'text' ),
			),
		);
	}

	/**
	 * @dataProvider filter__ep_post_mapping_strips_ngram_field_data
	 */
	public function test__filter__ep_post_mapping_strips_ngram_field( $post_content, $expected ) {
		Constant_Mocker::define( 'VIP_GO_ENV', 'production' );
		Constant_Mocker::define( 'VIP_ORIGIN_DATACENTER', 'dfw' );
		$this->init_es();

		$mapping = array(
			'settings' => array(),
			'mappings' => array(
				'properties' => array(
					'post_content' => $post_content,
				),
			),
		);

		$filtered = apply_filters( 'ep_post_mapping', $mapping );

		$this->assertSame( $expected, $filtered['mappings']['properties']['post_content'] );
	}

	public function test__vip_search_query_warning_observes_successful_search_response(): void {
		wp_cache_flush();
		$this->init_es();
		$body     = wp_json_encode( [
			'query' => [ 'match_all' => [] ],
			'size'  => 10,
		] );
		$response = $this->es_response( 200, [
			'took' => 21,
			'hits' => [
				'total' => [ 'value' => 2 ],
				'hits'  => [ [ '_id' => '1' ], [ '_id' => '2' ] ],
			],
		] );

		self::$mock_global_functions->expects( $this->once() )
			->method( 'mock_vip_safe_wp_remote_request' )
			->willReturn( $response );

		$warning = $this->createMock( Query_Warning::class );
		$warning->expects( $this->once() )
			->method( 'maybe_emit' )
			->with(
				$body,
				json_decode( $response['body'], true ),
				$this->callback( static fn( $duration ): bool => is_float( $duration ) && $duration >= 0.0 ),
				null,
				strlen( $response['body'] )
			)
			->willReturn( true );
		$this->search_instance->query_warning = $warning;

		$this->assertSame( $response, $this->intercept_query( '/vip-123-post-1/_search', $body ) );
	}

	public function test__vip_search_query_warning_is_not_initialized_during_search_setup(): void {
		$this->init_es();

		$this->assertNull( $this->search_instance->query_warning );
	}

	public function test__vip_search_query_warning_is_not_called_for_failed_search_response(): void {
		wp_cache_flush();
		$this->init_es();
		$response = $this->es_response( 500, '{}' );
		self::$mock_global_functions->expects( $this->once() )
			->method( 'mock_vip_safe_wp_remote_request' )
			->willReturn( $response );

		$warning = $this->createMock( Query_Warning::class );
		$warning->expects( $this->never() )->method( 'maybe_emit' );
		$this->search_instance->query_warning = $warning;

		$this->assertSame( $response, $this->intercept_query( '/vip-123-post-1/_search' ) );
	}

	public function test__vip_search_query_warning_is_not_called_for_non_search_query_type(): void {
		wp_cache_flush();
		$this->init_es();
		$response = $this->es_response( 200, '{}' );
		self::$mock_global_functions->expects( $this->once() )
			->method( 'mock_vip_safe_wp_remote_request' )
			->willReturn( $response );

		$warning = $this->createMock( Query_Warning::class );
		$warning->expects( $this->never() )->method( 'maybe_emit' );
		$this->search_instance->query_warning = $warning;

		$this->assertSame( $response, $this->intercept_query( '/vip-123-post-1/_mget' ) );
	}

	public function test__vip_search_query_warning_is_not_called_for_cache_hit(): void {
		wp_cache_flush();
		$this->init_es();
		$url       = '/vip-123-post-1/_search';
		$body      = wp_json_encode( [ 'query' => [ 'match_all' => [] ] ] );
		$response  = $this->es_response( 200, '{}' );
		$cache_key = 'es_query_cache:' . md5( $url . $body ) . ':' . wp_cache_get_last_changed( Search::SEARCH_CACHE_GROUP );
		wp_cache_set( $cache_key, $response, Search::SEARCH_CACHE_GROUP, 300 );

		self::$mock_global_functions->expects( $this->never() )->method( 'mock_vip_safe_wp_remote_request' );
		$warning = $this->createMock( Query_Warning::class );
		$warning->expects( $this->never() )->method( 'maybe_emit' );
		$this->search_instance->query_warning = $warning;

		$this->assertSame( $response, $this->intercept_query( $url, $body ) );
	}

	public function test__vip_search_query_warning_failure_does_not_change_response(): void {
		wp_cache_flush();
		$this->init_es();
		$response = $this->es_response( 200, [
			'took' => 21,
			'hits' => [
				'total' => [ 'value' => 0 ],
				'hits'  => [],
			],
		] );
		self::$mock_global_functions->expects( $this->once() )
			->method( 'mock_vip_safe_wp_remote_request' )
			->willReturn( $response );

		$warning = $this->createMock( Query_Warning::class );
		$warning->expects( $this->once() )
			->method( 'maybe_emit' )
			->willThrowException( new \RuntimeException( 'diagnostic failure' ) );
		$this->search_instance->query_warning = $warning;

		$this->assertSame( $response, $this->intercept_query( '/vip-123-post-1/_search' ) );
	}

	/**
	 * Sends a `query` type request through filter__ep_do_intercept_request().
	 */
	private function intercept_query( string $url, string $body = '{}' ) {
		return $this->search_instance->filter__ep_do_intercept_request(
			[],
			[ 'url' => $url ],
			[
				'method' => 'POST',
				'body'   => $body,
			],
			0,
			'query'
		);
	}

	/**
	 * Builds a remote request response, as returned by vip_safe_wp_remote_request().
	 *
	 * @param int          $code Response code.
	 * @param array|string $body Response body, JSON-encoded if an array.
	 */
	private function es_response( int $code, $body ): array {
		return http_response( $code, is_array( $body ) ? wp_json_encode( $body ) : $body );
	}

	private function stub_post(): WP_Post {
		$post     = new WP_Post( new stdClass() );
		$post->ID = 0;

		return $post;
	}

	/**
	 * Replaces the search instance's logger with a mock.
	 */
	private function mock_logger(): MockObject {
		$this->search_instance->logger = $this->getMockBuilder( \Automattic\VIP\Logstash\Logger::class )
			->onlyMethods( [ 'log' ] )
			->getMock();

		return $this->search_instance->logger;
	}

	/**
	 * Replaces the given search instance's alerts with a mock.
	 */
	private function mock_alerts( Search $search ): MockObject {
		$search->alerts = $this->createMock( Alerts::class );

		return $search->alerts;
	}

	private function get_index_settings( Indexable $indexable ): array {
		if ( method_exists( $indexable, 'build_settings' ) ) {
			return $indexable->build_settings();
		}

		$mapping = $indexable->generate_mapping();

		return $mapping['settings'];
	}

	private function define_es_credentials( array $endpoints ): void {
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', $endpoints );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_USERNAME', 'foo' );
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_PASSWORD', 'bar' );
	}

	/**
	 * Bulk indexes a new post as an administrator, sending the requests through the mocked transport.
	 */
	private function bulk_index_test_post(): void {
		// The transport expectations include an uncached index-exists request.
		delete_site_option( 'es_index_exists_vip-123-post-1' );

		$test_user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $test_user_id );

		$this->init_es();
		$indexable = Indexables::factory()->get( 'post' );

		$post_id = $this->factory()->post->create( array(
			'post_title'  => 'Test Post',
			'post_status' => 'publish',
		) );

		$indexable->bulk_index( [ $post_id ] );
	}

	/**
	 * Collects the messages passed to _doing_it_wrong() while running the callback.
	 */
	private function get_doing_it_wrong_messages( callable $callback ): array {
		$messages = [];
		$listener = function ( $function_name, $message ) use ( &$messages ) {
			$messages[] = $message;
		};

		add_action( 'doing_it_wrong_run', $listener, 10, 2 );
		$callback();
		remove_action( 'doing_it_wrong_run', $listener, 10 );

		return $messages;
	}

	private function register_stub_feature( string $slug ): Feature {
		$feature = new class( $slug ) extends Feature {
			public function __construct( string $slug ) {
				$this->slug = $slug;
				parent::__construct();
			}

			public function setup() {}

			public function output_feature_box_long() {}
		};

		Features::factory()->register_feature( $feature );

		return $feature;
	}

	/**
	 * Helper function to set required constant, initialize the search instance, and do required action for setting up EP indexables.
	 *
	 * @return void
	 */
	private function init_es( $run_init = true ) {
		Constant_Mocker::undefine( 'EP_DASHBOARD_SYNC' );
		Constant_Mocker::define( 'EP_DASHBOARD_SYNC', false );

		if ( $run_init ) {
			remove_all_actions( 'init' );
		}

		$this->search_instance->init();

		do_action( 'plugins_loaded' );

		if ( $run_init ) {
			do_action( 'init' );
		}
	}
}

/**
 * Overrides the global function so that Search_Test can mock remote requests.
 *
 * These shims apply to the whole Automattic\VIP\Search namespace once this file is loaded,
 * so outside of Search_Test (where the mock is null) they defer to the real function.
 * Other test classes must mock HTTP via `pre_http_request`.
 */
function vip_safe_wp_remote_request( ...$args ) {
	return is_null( Search_Test::$mock_global_functions ) ? \vip_safe_wp_remote_request( ...$args ) : Search_Test::$mock_global_functions->mock_vip_safe_wp_remote_request( ...$args );
}

/**
 * @see vip_safe_wp_remote_request()
 */
function wp_remote_request( ...$args ) {
	return is_null( Search_Test::$mock_global_functions ) ? \wp_remote_request( ...$args ) : Search_Test::$mock_global_functions->mock_wp_remote_request( ...$args );
}
