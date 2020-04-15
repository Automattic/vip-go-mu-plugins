<?php

namespace Automattic\VIP\Search\Queue;

use Automattic\VIP\Search\Queue\Cron as Cron;

class Cron_Test extends \WP_UnitTestCase {
	public function setUp() {
		global $wpdb;

		if ( ! defined( 'VIP_SEARCH_ENABLE_ASYNC_INDEXING' ) ) {
			define( 'VIP_SEARCH_ENABLE_ASYNC_INDEXING', true );
		}

		require_once __DIR__ . '/../../../../../search/search.php';

		$this->es = \Automattic\VIP\Search\Search::instance();

		$this->queue = $this->es->queue;

		$this->queue->schema->prepare_table();

		$this->queue->empty_queue();

		$this->cron = $this->queue->cron;
	}

	public function test_filter_cron_schedules() {
		$schedules = wp_get_schedules();

		$this->assertEquals( $schedules[ Cron::SWEEPER_CRON_INTERVAL_NAME ]['interval'], Cron::SWEEPER_CRON_INTERVAL );
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

		$this->assertTrue( (boolean) $next, 'After Cron::schedule_sweeper_job(), job was not found' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_disable_sweeper_job() {
		// Make sure it already exists
		$this->cron->schedule_sweeper_job();

		$existing = wp_next_scheduled( Cron::SWEEPER_CRON_EVENT_NAME );
		
		$this->assertTrue( (boolean) $existing, 'Sweeper cron event not scheduled, cannot test deletion' );

		$this->cron->disable_sweeper_job();

		$next = wp_next_scheduled( Cron::SWEEPER_CRON_EVENT_NAME );

		$this->assertFalse( $next, 'After Cron:disable_sweeper_job(), job was still found' );
	}

	public function test_process_jobs() {
		$mock_queue = $this->getMockBuilder( Queue::class )
			->setMethods( [ 'get_jobs', 'process_jobs' ] )
			->getMock();

		$mock_job_ids = array( 1, 2 );

		$mock_jobs = array(
			(object) array(
				'job_id' => 1,
				'object_id' => 1,
				'object_type' => 'post',
			),
			(object) array(
				'job_id' => 2,
				'object_id' => 2,
				'object_type' => 'user',
			),
		);
		
		// Should call Queue::get_jobs() with the right job_ids
		$mock_queue->expects( $this->once() )
			->method( 'get_jobs' )
			->with( $mock_job_ids )
			->will( $this->returnValue( $mock_jobs ) );

		// Then it should process those jobs
		$mock_queue->expects( $this->once() )
			->method( 'process_jobs' )
			->with( $mock_jobs )
			->will( $this->returnValue( true ) );

		$original_queue = $this->cron->queue;
		$this->cron->queue = $mock_queue;

		$this->cron->process_jobs( $mock_job_ids );

		// Restore original Queue to not affect other tests
		$this->cron->queue = $original_queue;
	}

	public function test_schedule_batch_job() {
		$mock_queue = $this->getMockBuilder( Queue::class )
			->setMethods( [ 'checkout_jobs' ] )
			->getMock();

		$mock_job_ids = array( 1, 2 );

		$mock_jobs = array(
			(object) array(
				'job_id' => 1,
				'object_id' => 1,
				'object_type' => 'post',
			),
			(object) array(
				'job_id' => 2,
				'object_id' => 2,
				'object_type' => 'user',
			),
		);
		
		$mock_queue->expects( $this->once() )
			->method( 'checkout_jobs' )
			->will( $this->returnValue( $mock_jobs ) );

		$original_queue = $this->cron->queue;
		$this->cron->queue = $mock_queue;

		$now = time();

		$this->cron->sweep_jobs();

		$expected_cron_event_args = array(
			$mock_job_ids,
		);

		// Should have scheduled 1 cron event to process the posts
		$cron_event_time = wp_next_scheduled( Cron::PROCESSOR_CRON_EVENT_NAME, $expected_cron_event_args );

		$this->assertEquals( $now, $cron_event_time );

		// Restore original Queue to not affect other tests
		$this->cron->queue = $original_queue;

		// Unschedule event to not pollute other tests
		wp_unschedule_event( $now, Cron::PROCESSOR_CRON_EVENT_NAME, $expected_cron_event_args );
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
}
