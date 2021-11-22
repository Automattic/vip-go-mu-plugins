<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Logstash\Logger;
use Automattic\VIP\StatsD;
use Automattic\VIP\Utils\Alerts;
use ElasticPress\Indexable;
use ElasticPress\Indexables;
use PHPUnit\Framework\MockObject\MockObject;
use WP_Error;
use WP_UnitTestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/mock-header.php';
require_once __DIR__ . '/../../../../search/search.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-versioning.php';
require_once __DIR__ . '/../../../../search/elasticpress/elasticpress.php';

class Search_Mocked_Test extends WP_UnitTestCase {
	use ExpectPHPException;

	public static $mock_global_functions;
	public $test_index_name = 'vip-1234-post-0-v3';

	public function setUp(): void {
		self::$mock_global_functions = $this->getMockBuilder( self::class )
			->setMethods( [ 'mock_vip_safe_wp_remote_request' ] )
			->getMock();

		$cache_key = Search::INDEX_EXISTENCE_CACHE_KEY_PREFIX . $this->test_index_name;
		wp_cache_delete( $cache_key, Search::SEARCH_CACHE_GROUP );

		header_remove();
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

	public function test__rate_limit_ep_query_integration__clears_start_correctly() {
		/** @var MockObject&Search */
		$partially_mocked_search = $this->getMockBuilder( Search::class )
			->setMethods( [ 'clear_query_limiting_start_timestamp' ] )
			->getMock();
		$partially_mocked_search->init();

		$partially_mocked_search->expects( $this->once() )->method( 'clear_query_limiting_start_timestamp' );

		$partially_mocked_search->rate_limit_ep_query_integration( false );
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

		/** @var MockObject&Search */
		$partially_mocked_search = $this->getMockBuilder( Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_increment_stat', 'maybe_send_timing_stat' ] )
			->getMock();

		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );

		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_vip_safe_wp_remote_request' )
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

		/** @var MockObject&Search */
		$partially_mocked_search = $this->getMockBuilder( Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_send_timing_stat' ] )
			->getMock();
		$partially_mocked_search->method( 'is_bulk_url' )
			->willReturn( true );
		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );
		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_vip_safe_wp_remote_request' )
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
				'code' => 400,
			],
		];

		$statsd_mock = $this->createMock( StatsD::class );

		/** @var MockObject&Search */
		$partially_mocked_search = $this->getMockBuilder( Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_increment_stat' ] )
			->getMock();
		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );
		$partially_mocked_search->statsd = $statsd_mock;
		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_vip_safe_wp_remote_request' )
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

		$statsd_mock = $this->createMock( StatsD::class );

		/** @var MockObject&Search */
		$partially_mocked_search = $this->getMockBuilder( Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_increment_stat' ] )
			->getMock();

		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );

		$partially_mocked_search->statsd = $statsd_mock;

		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_vip_safe_wp_remote_request' )
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
		$mocked_response = new WP_Error( 'code1', 'curl error 28' );

		/** @var MockObject&Search */
		$partially_mocked_search = $this->getMockBuilder( Search::class )
			->setMethods( [ 'get_statsd_request_mode_for_request', 'get_statsd_prefix', 'is_bulk_url', 'maybe_increment_stat' ] )
			->getMock();

		$partially_mocked_search->method( 'get_statsd_prefix' )
			->willReturn( $stats_prefix );

		$partially_mocked_search->init();

		self::$mock_global_functions->method( 'mock_vip_safe_wp_remote_request' )
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

		$es = new Search();
		$es->init();

		$alerts_mocked   = $this->createMock( Alerts::class );
		$queue_mocked    = $this->createMock( Queue::class );
		$indexables_mock = $this->createMock( Indexables::class );

		$es->queue      = $queue_mocked;
		$es->indexables = $indexables_mock;
		$es->alerts     = $alerts_mocked;

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

		$es->maybe_alert_for_average_queue_time();
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
			->setMethods( [ 'get_current_field_count' ] )
			->getMock();
		$partially_mocked_search->init();

		$alerts_mocked   = $this->createMock( Alerts::class );
		$indexables_mock = $this->createMock( Indexables::class );

		$partially_mocked_search->indexables = $indexables_mock;
		$partially_mocked_search->alerts     = $alerts_mocked;

		$indexables_mock->method( 'get' )
			->willReturn( $this->createMock( Indexable::class ) );

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

		$es = new Search();
		$es->init();
		$es->set_time( $time );

		$alerts_mocked = $this->createMock( Alerts::class );

		$es->alerts = $alerts_mocked;

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

		$es->maybe_alert_for_prolonged_query_limiting();
		$es->reset_time();
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

	public function test__maybe_increment_stat_sampling_keep() {
		$es = new Search();
		$es->init();

		$es::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( StatsD::class );

		$es->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->once() )
			->method( 'increment' )
			->with( 'test' );

		$es->maybe_increment_stat( 'test' );
	}

	public function test__maybe_increment_stat_sampling_drop() {
		$es = new Search();
		$es->init();

		$es::$stat_sampling_drop_value = 0; // Guarantee a sampling drop

		$statsd_mocked = $this->createMock( StatsD::class );

		$es->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'increment' );

		$es->maybe_increment_stat( 'test' );
	}

	/**
	 * @dataProvider stat_sampling_invalid_stat_param_data
	 */
	public function test__maybe_increment_stat_sampling_invalid_stat_param( $stat ) {
		$es = new Search();
		$es->init();

		$es::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( StatsD::class );

		$es->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'increment' );

		$es->maybe_increment_stat( $stat );
	}

	public function test__maybe_send_timing_stat_sampling_keep() {
		$es = new Search();
		$es->init();

		$es::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( StatsD::class );

		$es->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->once() )
			->method( 'timing' )
			->with( 'test', 50 );

		$es->maybe_send_timing_stat( 'test', 50 );
	}

	public function test__maybe_send_timing_stat_sampling_drop() {
		$es = new Search();
		$es->init();

		$es::$stat_sampling_drop_value = 0; // Guarantee a sampling drop

		$statsd_mocked = $this->createMock( StatsD::class );

		$es->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'timing' );

		$es->maybe_send_timing_stat( 'test', 50 );
	}

	/**
	 * @dataProvider stat_sampling_invalid_stat_param_data
	 */
	public function test__maybe_send_timing_stat_sampling_invalid_stat_param( $stat ) {
		$es = new Search();
		$es->init();

		$es::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( StatsD::class );

		$es->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'timing' );

		$es->maybe_send_timing_stat( $stat, 50 );
	}

	/**
	 * @dataProvider stat_sampling_invalid_value_param_data
	 */
	public function test__maybe_send_timing_stat_sampling_invalid_duration_param( $value ) {
		$es = new Search();
		$es->init();

		$es::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( StatsD::class );

		$es->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'timing' );

		$es->maybe_send_timing_stat( 'test', $value );
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
					'body' => '{ "error": {} }',
				],
				'Unknown Elasticsearch query error',
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
		$es = new Search();
		$es->init();

		$es->logger = $this->getMockBuilder( Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$es->logger->expects( $this->once() )
				->method( 'log' )
				->with(
					$this->equalTo( 'error' ),
					$this->equalTo( 'search_query_error' ),
					$this->equalTo( $expected_message ),
					$this->anything()
				);

		$es->ep_handle_failed_request( null, $response, [], '', null );
	}

	/**
	 * Ensure when index_exists() is called and there is no index, it does not get logged as a failed request.
	 */
	public function test__ep_handle_failed_request__index_exists() {
		$es = new Search();
		$es->init();

		$es->logger = $this->getMockBuilder( Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$es->logger->expects( $this->never() )->method( 'log' );

		$es->ep_handle_failed_request( null, 404, [], 0, 'index_exists' );
	}

	/**
	 * Ensure when actions from the skiplist are called, they do not get logged as a failed request.
	 */
	public function test__ep_handle_failed_request__skiplist() {
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$es->logger = $this->getMockBuilder( \Automattic\VIP\Logstash\Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$es->logger->expects( $this->never() )->method( 'log' );

		$skiplist = [
			'index_exists',
			'get',
		];

		foreach ( $skiplist as $item ) {
			$es->ep_handle_failed_request( null, 404, [], 0, $item );
		}
	}

	public function test__maybe_log_query_ratelimiting_start_should_do_nothing_if_ratelimiting_already_started() {
		$es = new Search();
		$es->init();

		wp_cache_set( $es::QUERY_RATE_LIMITED_START_CACHE_KEY, time(), $es::SEARCH_CACHE_GROUP );

		$es->logger = $this->getMockBuilder( Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$es->logger->expects( $this->never() )->method( 'log' );

		$es->maybe_log_query_ratelimiting_start();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_log_query_ratelimiting_start_should_log_if_ratelimiting_not_already_started() {
		$es = new Search();
		$es->init();

		$es->logger = $this->getMockBuilder( Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$es->logger->expects( $this->once() )
				->method( 'log' )
				->with(
					$this->equalTo( 'warning' ),
					$this->equalTo( 'search_query_rate_limiting' ),
					$this->equalTo(
						'Application 123 - http://example.org has triggered Elasticsearch query rate limiting, which will last up to 300 seconds. Subsequent or repeat occurrences are possible. Half of traffic is diverted to the database when queries are rate limited.'
					),
					$this->anything()
				);

		$es->maybe_log_query_ratelimiting_start();
	}

	public function ensure_index_existence__bail_on_unneeded_method_data() {
		return [
			[ 'DELETE' ],
			[ 'HEAD' ],
			[ 'heAD' ], // case insensitive
			[ 'get' ],
		];
	}

	/**
	 * @dataProvider ensure_index_existence__bail_on_unneeded_method_data
	 */
	public function test__ensure_index_existence__bail_on_unneeded_method( $method ) {
		$indexables_mock = $this->createMock( Indexables::class );
		$indexable_mock  = $this->createMock( Indexable::class );

		$indexables_mock->method( 'get' )->willReturn( $indexable_mock );

		$search             = new Search();
		$search->indexables = $indexables_mock;

		$indexable_mock->expects( $this->never() )->method( 'index_exists' );

		$args   = [ 'method' => $method ];
		$result = $search->ensure_index_existence( 'url', $args );
		$this->assertTrue( $result );
	}

	public function test__ensure_index_existence__bail_on_no_index_in_url() {
		$indexables_mock = $this->createMock( Indexables::class );
		$indexable_mock  = $this->createMock( Indexable::class );
		$url             = 'https://elastic:1234/_all';

		$indexables_mock->method( 'get' )->willReturn( $indexable_mock );

		$search             = new Search();
		$search->indexables = $indexables_mock;

		$indexable_mock->expects( $this->never() )->method( 'index_exists' );

		$result = $search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$this->assertTrue( $result );
	}

	public function ensure_index_existence__bail_on_index_operation_data() {
		return [
			[ 'https://elastic:1234/' . $this->test_index_name ],
			[ 'https://elastic:1234/' . $this->test_index_name . '/' ],
			[ ' https://elastic:1234/' . $this->test_index_name . '/   ' ],
		];
	}

	/**
	 * @dataProvider ensure_index_existence__bail_on_index_operation_data
	 */
	public function test__ensure_index_existence__bail_on_index_operation( $url ) {
		$indexables_mock = $this->createMock( Indexables::class );
		$indexable_mock  = $this->createMock( Indexable::class );

		$indexables_mock->method( 'get' )->willReturn( $indexable_mock );

		$search             = new Search();
		$search->indexables = $indexables_mock;

		$indexable_mock->expects( $this->never() )->method( 'index_exists' );

		$result = $search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$this->assertTrue( $result );
	}

	public function test__ensure_index_existence__bail_on_no_indexable() {
		$indexables_mock = $this->createMock( Indexables::class );
		$indexable_mock  = $this->createMock( Indexable::class );
		$versioning_mock = $this->createMock( Versioning::class );

		$url = 'https://elastic:1234/' . $this->test_index_name . '/_doc';

		$indexables_mock->method( 'get' )->willReturn( null );

		$search             = new Search();
		$search->indexables = $indexables_mock;
		$search->versioning = $versioning_mock;

		$indexable_mock->expects( $this->never() )->method( 'index_exists' );

		$result = $search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$this->assertTrue( $result );
	}

	public function test__ensure_index_existence__bail_on_non_matching_index_names() {
		$indexables_mock = $this->createMock( Indexables::class );
		$indexable_mock  = $this->createMock( Indexable::class );
		$versioning_mock = $this->createMock( Versioning::class );

		$different_index_name = 'vip-203-post-42';
		$url                  = 'https://elastic:1234/' . $this->test_index_name . '/_doc';

		$indexables_mock->method( 'get' )->willReturn( $indexable_mock );
		$indexable_mock->method( 'get_index_name' )->willReturn( $different_index_name );

		$search             = new Search();
		$search->indexables = $indexables_mock;
		$search->versioning = $versioning_mock;

		$indexable_mock->expects( $this->never() )->method( 'index_exists' );

		$result = $search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$this->assertTrue( $result );
	}

	public function ensure_index_existence__put_mapping_data() {
		return [
			[ true ],
			[ false ],
		];
	}

	/**
	 * @dataProvider ensure_index_existence__put_mapping_data
	 */
	public function test__ensure_index_existence__put_mapping_result_is_returned( $put_mapping_result ) {
		$indexables_mock = $this->createMock( Indexables::class );
		$indexable_mock  = $this->createMock( Indexable::class );
		$versioning_mock = $this->createMock( Versioning::class );

		$url = 'https://elastic:1234/' . $this->test_index_name . '/_doc';

		$indexables_mock->method( 'get' )->willReturn( $indexable_mock );
		$indexable_mock->method( 'get_index_name' )->willReturn( $this->test_index_name );

		$search             = new Search();
		$search->indexables = $indexables_mock;
		$search->versioning = $versioning_mock;

		$indexable_mock->expects( $this->once() )->method( 'index_exists' );
		$indexable_mock->expects( $this->once() )->method( 'put_mapping' )->willReturn( $put_mapping_result );

		$result = $search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$this->assertEquals( $put_mapping_result, $result );
	}

	public function test__ensure_index_existence__ix_exists_skip_check_on_second_pass() {
		$indexables_mock = $this->createMock( Indexables::class );
		$indexable_mock  = $this->createMock( Indexable::class );
		$versioning_mock = $this->createMock( Versioning::class );

		$url = 'https://elastic:1234/' . $this->test_index_name . '/_doc';

		$indexables_mock->method( 'get' )->willReturn( $indexable_mock );
		$indexable_mock->method( 'get_index_name' )->willReturn( $this->test_index_name );

		$search             = new Search();
		$search->indexables = $indexables_mock;
		$search->versioning = $versioning_mock;

		$indexable_mock->expects( $this->once() )->method( 'index_exists' )->willReturn( true );
		$indexable_mock->expects( $this->never() )->method( 'put_mapping' );

		$search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$second_pass_result = $search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$this->assertTrue( $second_pass_result );
	}

	public function test__ensure_index_existence__ix_initialized_skip_check_on_second_pass() {
		$indexables_mock = $this->createMock( Indexables::class );
		$indexable_mock  = $this->createMock( Indexable::class );
		$versioning_mock = $this->createMock( Versioning::class );

		$index_name = 'vip-203-post-1';
		$url        = 'https://elastic:1234/' . $index_name . '/_doc';

		$indexables_mock->method( 'get' )->willReturn( $indexable_mock );
		$indexable_mock->method( 'get_index_name' )->willReturn( $index_name );

		$search             = new Search();
		$search->indexables = $indexables_mock;
		$search->versioning = $versioning_mock;

		$indexable_mock->expects( $this->once() )->method( 'index_exists' )->willReturn( false );
		$indexable_mock->expects( $this->once() )->method( 'put_mapping' )->willReturn( true );

		$search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$second_pass_result = $search->ensure_index_existence( $url, [ 'method' => 'POST' ] );
		$this->assertTrue( $second_pass_result );
	}

	public function mock_vip_safe_wp_remote_request() {
		/* Empty */
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
	 * @dataProvider get_post_meta_allow_list__combinations_for_jetpack_migration_data
	 */
	public function test__get_post_meta_allow_list__combinations_for_jetpack_migration( $vip_search_keys, $jetpack_added, $expected ) {
		/** @var MockObject&Search */
		$es = $this->getMockBuilder( Search::class )
			->setMethods( [ 'is_jetpack_migration' ] )
			->getMock();

		// Mock `define( 'VIP_SEARCH_MIGRATION_SOURCE', 'jetpack' );` definition
		$es->method( 'is_jetpack_migration' )->willReturn( true );
		
		remove_all_filters( 'vip_search_post_meta_allow_list' );
		remove_all_filters( 'jetpack_sync_post_meta_whitelist' );
		$es->init();

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
}

/**
 * Overwriting global function so that no real remote request is called
 */
function vip_safe_wp_remote_request() {
	return is_null( Search_Mocked_Test::$mock_global_functions ) ? null : Search_Mocked_Test::$mock_global_functions->mock_vip_safe_wp_remote_request();
}
