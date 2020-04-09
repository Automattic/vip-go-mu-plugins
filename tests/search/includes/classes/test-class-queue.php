<?php

namespace Automattic\VIP\Search;

class Queue_Test extends \WP_UnitTestCase {
	public function setUp() {
		global $wpdb;

		wp_cache_flush();

		if ( ! defined( 'VIP_SEARCH_ENABLE_ASYNC_INDEXING' ) ) {
			define( 'VIP_SEARCH_ENABLE_ASYNC_INDEXING', true );
		}

		require_once __DIR__ . '/../../../../search/search.php';

		$this->es = \Automattic\VIP\Search\Search::instance();

		$this->queue = $this->es->queue;

		$this->queue->schema->prepare_table();

		$this->queue->empty_queue();
	}

	public function test_deduplication_of_repeat_indexing() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = array(
			array(
				'id' => 1,
				'type' => 'post',
			),
			array(
				'id' => 1,
				'type' => 'user',
			),
		);

		// How many times to requeue each object
		$times = 10;

		foreach ( $objects as $object ) {
			for ( $i = 0; $i < $times; $i++ ) {
				$this->queue->queue_object( $object['id'], $object['type'] );
			}

			// Now it should only exist once
			$results = $wpdb->get_results( 
				$wpdb->prepare( 
					"SELECT * FROM `{$table_name}` WHERE `object_id` = %d AND `object_type` = %s AND `status` = 'queued'", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object['id'],
					$object['type']
				)
			);

			$this->assertCount( 1, $results );
		}
	}

	public function test_rate_limiting_of_repeat_indexing() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = array(
			array(
				'id' => 1,
				'type' => 'post',
			),
			array(
				'id' => 1,
				'type' => 'user',
			),
		);

		foreach ( $objects as $object ) {
			$now = time();

			// Insert a job, and set it to running
			$this->queue->queue_object( $object['id'], $object['type'] );
			$this->queue->get_batch_jobs( 10 ); // Sets it to running
			$this->queue->set_last_index_time( $object['id'], $object['type'], $now );
			
			// Requeue the job
			$this->queue->queue_object( $object['id'], $object['type'] );

			// Since it was already running, we should now have a new queued entry with a start_time
			// that is now() + min interval - this is the rate limit

			$row = $wpdb->get_row( 
				$wpdb->prepare( 
					"SELECT `start_time` FROM `{$table_name}` WHERE `object_id` = %d AND `object_type` = %s AND `status` = 'queued'", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object['id'],
					$object['type']
				)
			);

			$expected_start_time = gmdate( 'Y-m-d H:i:s', $now + $this->queue->get_index_interval_time( $object['id'], $object['type'] ) );

			$this->assertEquals( $expected_start_time, $row->start_time );
		}
	}

	public function test_get_batch_jobs() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = array(
			array(
				'id' => 1,
				'type' => 'post',
			),
			array(
				'id' => 2,
				'type' => 'post',
			),
			array(
				'id' => 3,
				'type' => 'post',
			),
			array(
				'id' => 1000,
				'type' => 'user',
			),
		);

		$now = time();

		// Insert first job, set it to running, so that we get some queued objects that are rate limited
		$this->queue->queue_object( $objects[0]['id'], $objects[0]['type'] );
		$this->queue->set_last_index_time( $objects[0]['id'], $objects[0]['type'], $now );
		$this->queue->update_job( $objects[0]['id'], array( 'status' => 'running' ) );

		// Insert the jobs
		foreach ( $objects as $object ) {
			$this->queue->queue_object( $object['id'], $object['type'] );
		}
			
		$jobs = $this->queue->get_batch_jobs( 10 );

		$object_ids = wp_list_pluck( $jobs, 'object_id' );

		// Should not have received post 1, because it is currently running, then scheduled again for the future
		$expected_object_ids = array( 2, 3, 1000 );

		$this->assertEquals( $expected_object_ids, $object_ids );

		// And each of those should be now marked as "running"
		$ids_escaped = array_map( 'esc_sql', $expected_object_ids );

		$ids_where_string = implode( ', ', $ids_escaped );

		$not_running_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE `id` IN ({$ids_where_string}) AND `status` != 'running'" ); // Cannot prepare table name, already escaped. @codingStandardsIgnoreLine

		// There should be 1 that now isn't marked as running, and that's post 1 which was rescheduled again for the future (rate limited)
		$this->assertEquals( 1, $not_running_count );
	}

	public function test_offload_indexing_to_queue() {
		$mock_sync_manager = (object) array( 'sync_queue' => [ 1, 2, 3 ] );

		// Make sure we're not already bailing on EP indexing, otherwise the test isn't doing anything
		$current_bail = apply_filters( 'pre_ep_index_sync_queue', false, $mock_sync_manager, 'post' );

		$this->assertFalse( $current_bail );

		// Offload indexing
		$this->queue->offload_indexing_to_queue();

		// Now the filter should return true to bail early from EP indexing
		$current_bail = apply_filters( 'pre_ep_index_sync_queue', false, $mock_sync_manager, 'post' );

		$this->assertTrue( $current_bail );
	}

	public function test_update_job() {
		$this->queue->queue_object( 1, 'post' );

		$job = $this->queue->get_next_job_for_object( 1, 'post' );

		$this->assertEquals( 'queued', $job->status );

		$this->queue->update_job( $job->id, array( 'start_time' => '2020-01-01 00:00:00' ) );

		$job = $this->queue->get_next_job_for_object( 1, 'post' );

		$this->assertEquals( 'queued', $job->status );
	}

	public function test_update_jobs() {
		$this->queue->queue_object( 1, 'post' );
		$this->queue->queue_object( 2, 'post' );

		$job1 = $this->queue->get_next_job_for_object( 1, 'post' );
		$job2 = $this->queue->get_next_job_for_object( 2, 'post' );

		$this->assertEquals( 'queued', $job1->status );
		$this->assertEquals( 'queued', $job2->status );

		$this->queue->update_jobs( array( $job1->id, $job2->id ), array( 'start_time' => '2040-01-01 00:00:00' ) );

		$job1 = $this->queue->get_next_job_for_object( 1, 'post' );
		$job2 = $this->queue->get_next_job_for_object( 2, 'post' );

		$this->assertEquals( '2040-01-01 00:00:00', $job1->start_time );
		$this->assertEquals( '2040-01-01 00:00:00', $job2->start_time );
	}

	public function test_delete_jobs() {
		$this->queue->queue_object( 1, 'post' );
		$this->queue->queue_object( 2, 'post' );
	
		$job1 = $this->queue->get_next_job_for_object( 1, 'post' );
		$job2 = $this->queue->get_next_job_for_object( 2, 'post' );

		$this->queue->delete_jobs( array( $job1, $job2 ) );

		$count = $this->queue->count_jobs( 'queued', 'post' );

		$this->assertEquals( 0, $count );
	}

	public function test_empty_queue() {
		$this->queue->queue_object( 1, 'post' );

		$count = $this->queue->count_jobs( 'queued', 'post' );

		$this->assertEquals( 1, $count );

		$this->queue->empty_queue();

		$count = $this->queue->count_jobs( 'queued', 'post' );

		$this->assertEquals( 0, $count );
	}

	public function test_count_jobs() {
		$this->queue->queue_object( 1, 'post' );
		$this->queue->queue_object( 2, 'post' );
		$this->queue->queue_object( 3, 'post' );

		$count = $this->queue->count_jobs( 'queued', 'post' );

		$this->assertEquals( 3, $count );
	}

	public function test_count_jobs_due_now() {
		$this->queue->queue_object( 1, 'post' );
		$this->queue->queue_object( 2, 'post' );
		$this->queue->queue_object( 3, 'post' );

		// Set the first job to be for the future
		$job1 = $this->queue->get_next_job_for_object( 1, 'post' );

		$this->queue->update_job( $job1->id, array( 'start_time' => '2040-01-01 00:00:00' ) );

		$count = $this->queue->count_jobs_due_now( 'post' );

		// Should only have 2 due now, since job 1 was scheduled for far future
		$this->assertEquals( 2, $count );
	}

	public function test_get_next_job_for_object() {
		$this->queue->queue_object( 1, 'post' );

		$job = $this->queue->get_next_job_for_object( 1, 'post' );

		$this->assertEquals( 1, $job->object_id );
		$this->assertEquals( 'post', $job->object_type );
		$this->assertEquals( 'queued', $job->status );
		$this->assertEquals( null, $job->start_time );
	}

	public function test_process_batch_jobs() {
		// TODO
	}

	public function test_intercept_ep_sync_manager_indexing() {
		$post_ids = array( 1, 2, 1000 );

		$mock_sync_manager = (object) array(
			'sync_queue' => array_fill_keys( $post_ids, true ), // EP stores in id => true format
		);

		$this->queue->intercept_ep_sync_manager_indexing( false, $mock_sync_manager, 'post' );

		// Now the jobs should be queued up in the table
		foreach( $post_ids as $post_id ) {
			$job = $this->queue->get_next_job_for_object( $post_id, 'post' );

			$this->assertEquals( 'queued', $job->status, "Wrong job status ($job->status) for post $post_id" );
		}

		// And the SyncManager's queue should have been emptied
		$this->assertEmpty( $mock_sync_manager->sync_queue );
	}
}
