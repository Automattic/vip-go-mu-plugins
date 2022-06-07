<?php

namespace Automattic\VIP\Search\Queue;

use Automattic\VIP\Search\Queue\Cron;
use Automattic\VIP\Search\Search;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use wpdb;

class Cron_Test extends WP_UnitTestCase {
	/** @var Search */
	private $es;

	public function setUp(): void {
		if ( ! defined( 'VIP_SEARCH_ENABLE_ASYNC_INDEXING' ) ) {
			define( 'VIP_SEARCH_ENABLE_ASYNC_INDEXING', true );
		}

		require_once __DIR__ . '/../../../../../search/search.php';

		$this->es = Search::instance();
		$this->es->init();

		$this->queue = $this->es->queue;

		$this->queue->schema->prepare_table();

		$this->queue->empty_queue();

		$this->cron = $this->queue->cron;
	}

	public function test_filter_cron_schedules() {
		$schedules = wp_get_schedules();

		self::assertArrayHasKey( Cron::SWEEPER_CRON_INTERVAL_NAME, $schedules );
		self::assertArrayHasKey( 'interval', $schedules[ Cron::SWEEPER_CRON_INTERVAL_NAME ] );
		self::assertEquals( $schedules[ Cron::SWEEPER_CRON_INTERVAL_NAME ]['interval'], Cron::SWEEPER_CRON_INTERVAL );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_schedule_sweeper_job() {
		// Make sure it's not already scheduled
		$this->cron->disable_sweeper_job();

		$existing = wp_next_scheduled( Cron::SWEEPER_CRON_EVENT_NAME );

		$this->assertFalse( $existing, 'Existing cron event, wp_clear_scheduled_hook() failed' );

		$this->cron->schedule_sweeper_job();

		$next = wp_next_scheduled( Cron::SWEEPER_CRON_EVENT_NAME );

		$this->assertTrue( (bool) $next, 'After Cron::schedule_sweeper_job(), job was not found' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_disable_sweeper_job() {
		// Make sure it already exists
		$this->cron->schedule_sweeper_job();

		$existing = wp_next_scheduled( Cron::SWEEPER_CRON_EVENT_NAME );

		$this->assertTrue( (bool) $existing, 'Sweeper cron event not scheduled, cannot test deletion' );

		$this->cron->disable_sweeper_job();

		$next = wp_next_scheduled( Cron::SWEEPER_CRON_EVENT_NAME );

		$this->assertFalse( $next, 'After Cron:disable_sweeper_job(), job was still found' );
	}

	public function test_process_jobs() {
		$mock_queue = $this->getMockBuilder( Queue::class )
			->setMethods( [ 'get_jobs_by_range', 'process_jobs' ] )
			->getMock();


		$options = [
			'min_id' => 1,
			'max_id' => 2,
		];

		$mock_jobs = array(
			(object) array(
				'job_id'      => 1,
				'object_id'   => 1,
				'object_type' => 'post',
			),
			(object) array(
				'job_id'      => 2,
				'object_id'   => 2,
				'object_type' => 'user',
			),
		);

		// Should call Queue::get_jobs_by_range() with the right job_ids
		$mock_queue->expects( $this->once() )
			->method( 'get_jobs_by_range' )
			->with( 1, 2 )
			->will( $this->returnValue( $mock_jobs ) );

		// Then it should process those jobs
		$mock_queue->expects( $this->once() )
			->method( 'process_jobs' )
			->with( $mock_jobs )
			->will( $this->returnValue( true ) );

		$original_queue    = $this->cron->queue;
		$this->cron->queue = $mock_queue;

		$this->cron->process_jobs( $options );

		// Restore original Queue to not affect other tests
		$this->cron->queue = $original_queue;
	}

	public function test_schedule_batch_job() {
		/** @var wpdb $wpdb */
		global $wpdb;

		/** @var Cron&MockObject */
		$partially_mocked_cron = $this->getMockBuilder( Cron::class )
			->setMethods( [ 'get_processor_job_count', 'get_max_concurrent_processor_job_count' ] )
			->getMock();
		$mock_queue            = $this->getMockBuilder( Queue::class )
			->setMethods( [ 'checkout_jobs', 'free_deadlocked_jobs' ] )
			->getMock();

		$mock_jobs = array(
			(object) array(
				'job_id'      => 1,
				'object_id'   => 1,
				'object_type' => 'post',
			),
			(object) array(
				'job_id'      => 2,
				'object_id'   => 2,
				'object_type' => 'user',
			),
		);

		$mock_queue->expects( $this->once() )
			->method( 'checkout_jobs' )
			->willReturn( $mock_jobs );

		// Only schedule once as the second count is already maximum
		$partially_mocked_cron->method( 'get_processor_job_count' )->willReturnOnConsecutiveCalls( 1, 5 );
		$partially_mocked_cron->method( 'get_max_concurrent_processor_job_count' )->willReturn( 5 );

		$partially_mocked_cron->queue = $mock_queue;

		$now = time();

		$partially_mocked_cron->sweep_jobs();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$option_name = $wpdb->get_var( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'vip:esqp\_%'" );
		self::assertIsString( $option_name );

		$expected_cron_event_args = [
			[
				'option' => $option_name,
			],
		];

		$expected_job_ids = [ 1, 2 ];

		// Should have scheduled 1 cron event to process the posts
		$cron_event_time = wp_next_scheduled( Cron::PROCESSOR_CRON_EVENT_NAME, $expected_cron_event_args );

		$this->assertEqualsWithDelta( $now, $cron_event_time, 1 );

		$job_ids = get_option( $option_name );
		self::assertEquals( $expected_job_ids, $job_ids );

		self::assertTrue( delete_option( $option_name ) );

		// Unschedule event to not pollute other tests
		wp_unschedule_event( $now, Cron::PROCESSOR_CRON_EVENT_NAME, $expected_cron_event_args );
	}

	public function schedule_batch_job__scheduling_limits_data() {
		return [
			[
				[ 0, 1, 2, 3, 4, 5 ],
				3,
			],
			[
				[ new \WP_Error( 'not-good' ) ],
				0,
			],
			[
				[ 0, 1, new \WP_Error( 'not-good' ) ],
				2,
			],
		];
	}

	/**
	 * @dataProvider schedule_batch_job__scheduling_limits_data
	 */
	public function test_schedule_batch_job__scheduling_limits( $job_counts, $expected_shedule_count ) {
		/** @var Cron&MockObject */
		$partially_mocked_cron = $this->getMockBuilder( Cron::class )
			->setMethods( [ 'get_processor_job_count', 'schedule_batch_job', 'get_max_concurrent_processor_job_count' ] )
			->getMock();


		$partially_mocked_cron->queue = $this->createMock( \Automattic\VIP\Search\Queue::class );

		$partially_mocked_cron->expects( $this->exactly( $expected_shedule_count ) )
			->method( 'schedule_batch_job' )
			->willReturn( true );

		$partially_mocked_cron->method( 'get_processor_job_count' )
			->willReturnOnConsecutiveCalls( ...$job_counts );
		$partially_mocked_cron->method( 'get_max_concurrent_processor_job_count' )
			->willReturn( 3 );

		$partially_mocked_cron->sweep_jobs();
	}


	/**
	 * Test if cron is enabled or disabled
	 *
	 * Currently this is always true
	 */
	public function test_is_enabled() {
		$enabled = $this->cron->is_enabled();

		$this->assertTrue( $enabled );
	}

	public function configure_concurrency_data() {
		return [
			[ // min 1
				1,
				[ 'vip_search_queue_processor' => 1 ],
			],
			[ // max 25 %
				10,
				[ 'vip_search_queue_processor' => 3 ],
			],
			[
				20,
				[ 'vip_search_queue_processor' => 3 ],
			],
			[ // max of 3 takes over
				30,
				[ 'vip_search_queue_processor' => 3 ],
			],
		];
	}

	/**
	 * @dataProvider configure_concurrency_data
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_configure_concurrency( $cron_limit, $expected ) {
		define( 'Automattic\WP\Cron_Control\JOB_CONCURRENCY_LIMIT', $cron_limit );

		$result = $this->cron->configure_concurrency( [] );

		$this->assertEquals( $expected, $result );
	}
}
