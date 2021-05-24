<?php

namespace Automattic\VIP\Search;

class Queue_Test extends \WP_UnitTestCase {
	/**
	* Make tests run in separate processes since we're testing state
	* related to plugin init, including various constants.
	*/
	protected $preserveGlobalState = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	protected $runTestInSeparateProcess = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	public static function setUpBeforeClass() {
		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) {
			define( 'VIP_ELASTICSEARCH_ENDPOINTS', array( 'https://elasticsearch:9200' ) );
		}

		require_once __DIR__ . '/../../../../search/search.php';

		\Automattic\VIP\Search\Search::instance();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		// Users indexable doesn't get registered by default, but we have tests that queue user objects
		\ElasticPress\Indexables::factory()->register( new \ElasticPress\Indexable\User\User() );
	}

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

	public function get_index_version_number_from_options_data() {
		return array(
			// Specified in options
			array(
				// Object type
				'post',
				// Options
				array(
					'index_version' => 2,
				),
				// Expected
				2,
			),

			// Not specified, defaults to current index version
			array(
				// Object type
				'post',
				// Options
				array(),
				// Expected
				1,
			),
		);
	}

	/**
	 * @dataProvider get_index_version_number_from_options_data
	 */
	public function test_get_index_version_number_from_options( $object_type, $options, $expected_version_number ) {
		$version_number = $this->queue->get_index_version_number_from_options( $object_type, $options );

		$this->assertEquals( $expected_version_number, $version_number );
	}

	public function get_last_index_time_cache_key_data() {
		return array(
			// Index version specified in options
			array(
				// Object id
				1,
				// Object type
				'post',
				// Options
				array(
					'index_version' => 2,
				),
				// Expected
				'post-1-v2',
			),

			// Index version not specified, defaults to current index version
			array(
				// Object id
				9999,
				// Object type
				'post',
				// Options
				array(),
				// Expected
				'post-9999-v1',
			),
		);
	}

	/**
	 * @dataProvider get_last_index_time_cache_key_data
	 */
	public function test_get_last_index_time_cache_key( $object_id, $object_type, $options, $expected_cache_key ) {
		$cache_key = $this->queue->get_last_index_time_cache_key( $object_id, $object_type, $options );

		$this->assertEquals( $expected_cache_key, $cache_key );
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
			$this->queue->checkout_jobs( 10 ); // Sets it to running
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

	public function test_checkout_jobs() {
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

		// Insert first job, set it to scheduled, so that we get some queued objects that are rate limited
		$this->queue->queue_object( $objects[0]['id'], $objects[0]['type'] );
		$this->queue->set_last_index_time( $objects[0]['id'], $objects[0]['type'], $now );
		$this->queue->update_job( $objects[0]['id'], array( 'status' => 'scheduled' ) );

		// Insert the jobs
		foreach ( $objects as $object ) {
			$this->queue->queue_object( $object['id'], $object['type'] );
		}

		$expected_scheduled_time = gmdate( 'Y-m-d H:i:s' );

		$jobs = $this->queue->checkout_jobs( 10 );

		// Should have the right status and scheduled_time set
		foreach ( $jobs as $job ) {
			$this->assertEquals( 'scheduled', $job->status, "Job $job->job_id was expected to have status 'scheduled'" );
			$this->assertEquals( $expected_scheduled_time, $job->scheduled_time, "Job $job->job_id has the wrong scheduled_time" );
		}

		$object_ids = wp_list_pluck( $jobs, 'object_id' );

		// Should not have received post 1, because it is currently running, then scheduled again for the future
		$expected_object_ids = array( 2, 3, 1000 );

		$this->assertEquals( $expected_object_ids, $object_ids, 'Checked out jobs ids do not match what was expected' );

		// And each of those should be now marked as "running"
		$ids_escaped = array_map( 'esc_sql', $expected_object_ids );

		$ids_where_string = implode( ', ', $ids_escaped );

		$not_scheduled_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE `job_id` IN ({$ids_where_string}) AND `status` != 'scheduled'" ); // Cannot prepare table name, already escaped. @codingStandardsIgnoreLine

		// There should be 1 that now isn't marked as running, and that's post 1 which was rescheduled again for the future (rate limited)
		$this->assertEquals( 1, $not_scheduled_count );
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

		$this->queue->update_job( $job->job_id, array( 'start_time' => '2020-01-01 00:00:00' ) );

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

		$this->queue->update_jobs( array( $job1->job_id, $job2->job_id ), array( 'start_time' => '2040-01-01 00:00:00' ) );

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

		$this->queue->update_job( $job1->job_id, array( 'start_time' => '2040-01-01 00:00:00' ) );

		$count = $this->queue->count_jobs_due_now( 'post' );

		// Should only have 2 due now, since job 1 was scheduled for far future
		$this->assertEquals( 2, $count );
	}

	public function test_count_jobs_by_version() {
		$this->queue->queue_object( 1, 'post', array( 'index_version' => 2 ) );
		$this->queue->queue_object( 2, 'post', array( 'index_version' => 2 ) );
		$this->queue->queue_object( 3, 'post', array( 'index_version' => 1 ) );

		$count_default = $this->queue->count_jobs( 'queued', 'post' );
		$count_version_2 = $this->queue->count_jobs( 'queued', 'post', array( 'index_version' => 2 ) );

		$this->assertEquals( 1, $count_default, 'Wrong count for default index version' );
		$this->assertEquals( 2, $count_version_2, 'Wrong count for index version 2' );
	}

	public function test_get_next_job_for_object() {
		$this->queue->queue_object( 1, 'post' );

		$job = $this->queue->get_next_job_for_object( 1, 'post' );

		$this->assertEquals( 1, $job->object_id );
		$this->assertEquals( 'post', $job->object_type );
		$this->assertEquals( 'queued', $job->status );
		$this->assertEquals( null, $job->start_time );
	}

	public function test_get_next_job_for_object_with_version() {
		$this->queue->queue_object( 1, 'post' );
		$this->queue->queue_object( 1, 'post', array( 'index_version' => 2 ) );

		$job = $this->queue->get_next_job_for_object( 1, 'post', array( 'index_version' => 2 ) );

		$this->assertEquals( 2, $job->job_id );
		$this->assertEquals( 1, $job->object_id );
		$this->assertEquals( 'post', $job->object_type );
		$this->assertEquals( 'queued', $job->status );
		$this->assertEquals( null, $job->start_time );
		$this->assertEquals( 2, $job->index_version );
	}

	public function test_process_jobs() {
		$job_ids = array(
			'12',
			'45',
			'89',
			'246',
		);

		// Add some jobs to the queue
		$this->queue->queue_objects( $job_ids );

		// Have to get by job id and not by object id
		$jobs = $this->queue->get_jobs( array_keys( $job_ids ) );

		$job_count = $this->queue->count_jobs( 'all' );

		$this->assertEquals( $job_count, count( $job_ids ), 'job count in database should match jobs added to queue' );

		$this->queue->process_jobs( $jobs );

		$jobs = $this->queue->get_jobs( array_keys( $job_ids ) );

		$this->assertEmpty( $jobs, 'jobs should be gone after being processed' );
	}

	public function test_intercept_ep_sync_manager_indexing() {
		$post_ids = array( 1, 2, 1000 );

		$mock_sync_manager = (object) array(
			'sync_queue' => array_fill_keys( $post_ids, true ), // EP stores in id => true format
		);

		$this->queue->intercept_ep_sync_manager_indexing( false, $mock_sync_manager, 'post' );

		// And the SyncManager's queue should have been emptied
		$this->assertEmpty( $mock_sync_manager->sync_queue );
	}

	public function test_get_jobs() {
		$this->queue->queue_object( 1000, 'post' );
		$this->queue->queue_object( 2000, 'post' );

		$jobs = $this->queue->get_jobs( array( 1, 2 ) );

		$expected_object_ids = array( 1000, 2000 );
		$actual_object_ids = wp_list_pluck( $jobs, 'object_id' );

		$this->assertEquals( $expected_object_ids, $actual_object_ids );
	}

	public function test_get_jobs_with_empty() {
		$jobs = $this->queue->get_jobs( array() );

		$this->assertEquals( array(), $jobs );
	}

	public function test_queue_objects_not_array() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$this->queue->queue_objects( 'Test' );
		$this->queue->queue_objects( 42 );

		$results = $wpdb->get_results( "SELECT * FROM `{$table_name}` WHERE 1", \ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertEquals( 0, count( $results ), 'shouldn\'t add objects to queue if object id list isn\'t an array' );
	}

	public function test_queue_objects_should_match_database() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = range( 10, 20 );

		$this->queue->queue_objects( $objects );

		$results = \wp_list_pluck( $wpdb->get_results( "SELECT object_id FROM `{$table_name}` WHERE 1" ), 'object_id' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertEquals( $objects, $results, 'ids of objects sent to queue doesn\'t match ids of objects found in the database' );
	}

	public function test_queue_objects_with_specific_index_version() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = range( 10, 20 );

		$this->queue->queue_objects( $objects, 'post', array( 'index_version' => 2 ) );

		$results = \wp_list_pluck( $wpdb->get_results( "SELECT object_id FROM `{$table_name}` WHERE `index_version` = 2" ), 'object_id' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertEquals( $objects, $results, 'ids of objects sent to queue doesn\'t match ids of objects found in the database' );
	}

	public function test__action__ep_after_bulk_index_validation() {
		$this->assertFalse( $this->queue->action__ep_after_bulk_index( 'not_array', 'post', false ), 'should return false when $document_ids is not an array' );
		$this->assertFalse( $this->queue->action__ep_after_bulk_index( array(), 'not post', false ), 'should return false when $slug is not set to \'post\'' );
		$this->assertFalse( $this->queue->action__ep_after_bulk_index( array(), 'post', true ), 'should return false when $return is not false' );
		$this->assertTrue( $this->queue->action__ep_after_bulk_index( array(), 'post', false ), 'should return true if the queue is enabled, $document_ids is an array, $slug is \'post\', and $return is false' );
	}

	public function test__action__ep_after_bulk_index_functionality() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = range( 10, 20 );

		$this->queue->action__ep_after_bulk_index( $objects, 'post', false );

		$results = \wp_list_pluck( $wpdb->get_results( "SELECT object_id FROM `{$table_name}` WHERE 1" ), 'object_id' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertEquals( $objects, $results, 'ids of objects sent to queue doesn\'t match ids of objects found in the database' );
	}

	public function test_get_deadlocked_jobs() {
		$this->queue->queue_object( 1000, 'post' );
		$this->queue->queue_object( 2000, 'post' );
		$this->queue->queue_object( 3000, 'post' );

		// Set the first job to have been scheduled in the recent past, to be flagged as deadlocked
		$job1 = $this->queue->get_next_job_for_object( 1000, 'post' );

		$deadlocked_time = time() - $this->queue::DEADLOCK_TIME;

		$this->queue->update_job( $job1->job_id, array(
			'status' => 'scheduled',
			'scheduled_time' => gmdate( 'Y-m-d H:i:s', $deadlocked_time ),
		) );

		// Set the second job to have been scheduled in the far past, to be flagged as deadlocked
		$job2 = $this->queue->get_next_job_for_object( 3000, 'post' );

		$deadlocked_time = time() - $this->queue::DEADLOCK_TIME - ( 3 * DAY_IN_SECONDS );

		$this->queue->update_job( $job2->job_id, array(
			'status' => 'running',
			'scheduled_time' => gmdate( 'Y-m-d H:i:s', $deadlocked_time ),
		) );

		// Now both jobs 1 and 2 should be considered deadlocked
		$deadlocked_jobs = $this->queue->get_deadlocked_jobs();

		$deadlocked_job_ids = wp_list_pluck( $deadlocked_jobs, 'job_id' );

		$expected_deadlocked_job_ids = array(
			$job1->job_id,
			$job2->job_id,
		);

		$this->assertEquals( $expected_deadlocked_job_ids, $deadlocked_job_ids );
	}

	public function test_free_deadlocked_jobs() {
		$this->queue->queue_object( 1000, 'post' );
		$this->queue->queue_object( 2000, 'post' );
		$this->queue->queue_object( 3000, 'post' );

		// Set the first job to have been scheduled in the recent past, to be flagged as deadlocked
		$job1 = $this->queue->get_next_job_for_object( 1000, 'post' );

		$deadlocked_time = time() - $this->queue::DEADLOCK_TIME;

		$this->queue->update_job( $job1->job_id, array(
			'status' => 'scheduled',
			'scheduled_time' => gmdate( 'Y-m-d H:i:s', $deadlocked_time ),
		) );

		// Set the second job to have been scheduled in the far past, to be flagged as deadlocked
		$job2 = $this->queue->get_next_job_for_object( 3000, 'post' );

		$deadlocked_time = time() - $this->queue::DEADLOCK_TIME - ( 3 * DAY_IN_SECONDS );

		$this->queue->update_job( $job2->job_id, array(
			'status' => 'scheduled',
			'scheduled_time' => gmdate( 'Y-m-d H:i:s', $deadlocked_time ),
		) );

		// Now free the deadlocked jobs
		$this->queue->free_deadlocked_jobs();

		// And all jobs should be back to being queued
		$count = $this->queue->count_jobs_due_now( 'post' );

		$this->assertEquals( 3, $count );
	}


	public function test_free_deadlocked_jobs_handle_duplicates() {
		$first_job = [
			'job_id' => 1,
			'object_id' => 10,
			'object_type' => 'post',
			'index_version' => 1,
		];
		$second_job = [
			'job_id' => 2,
			'object_id' => 10,
			'object_type' => 'post',
			'index_version' => 1,
		];

		$partially_mocked_queue = $this->getMockBuilder( \Automattic\VIP\Search\Queue::class )
			->setMethods( [
				'get_deadlocked_jobs',
				'update_jobs',
				'delete_jobs',
			] )
			->getMock();

		$partially_mocked_queue
			->method( 'get_deadlocked_jobs' )
			->willReturnOnConsecutiveCalls(
				[ $first_job, $second_job ],
				[],
				[],
				[],
				[]
			);

		$partially_mocked_queue->expects( $this->once() )
			->method( 'update_jobs' )
			->with(
				$this->equalTo( [ 1 ] ),
				$this->equalTo( [
					'status' => 'queued',
					'scheduled_time' => null,
				] )
			);

		$partially_mocked_queue->expects( $this->once() )
			->method( 'delete_jobs' )
			->with( [ $second_job ] );

		$partially_mocked_queue->free_deadlocked_jobs();
	}

	/**
	 * Ensure that the value passed into the filter is returned if the indexable_slug is not 'post'
	 */
	public function test__ratelimit_indexing_should_pass_bail_if_not_post() {
		$this->assertTrue( $this->queue->ratelimit_indexing( true, '', 'hippo' ), 'should return true since true was passed in' );
		$this->assertFalse( $this->queue->ratelimit_indexing( false, '', 'hippo' ), 'should return false since false was passed in' );
	}

	/**
	 * Ensure that the value passed into the filter is returned if the sync queue is empty
	 */
	public function test__ratelimit_indexing_should_pass_bail_if_sync_queue_empty() {
		$sync_manager = new \stdClass();
		$sync_manager->sync_queue = array();

		$this->assertTrue( $this->queue->ratelimit_indexing( true, $sync_manager, 'post' ), 'should return true since true was passed in' );
		$this->assertFalse( $this->queue->ratelimit_indexing( false, $sync_manager, 'post' ), 'should return false since false was passed in' );
	}

	/**
	 * Ensure that the count in the cache doesn't exist on load
	 */
	public function test_ratelimit_indexing_cache_count_should_not_exist_onload() {
		$this->assertFalse( wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count shouldn\'t exist prior to first function call' );
	}

	/**
	 * Ensure that the count in the cache doesn't exist if the ratelimit_indexing returns early
	 */
	public function test_ratelimit_indexing_cache_count_should_not_exists_if_early_return() {
		$sync_manager = new \stdClass();
		$sync_manager->sync_queue = array();

		$this->queue->ratelimit_indexing( true, '', 'hippo' );
		$this->queue->ratelimit_indexing( true, $sync_manager, 'post' );

		$this->assertFalse( wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count shouldn\'t exist if function calls all returned early' );
	}

	/**
	 * Ensure that the queue isn't populated if ratelimiting isn't triggered
	 */
	public function test_ratelimit_indexing_queue_should_be_empty_if_no_ratelimiting() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$sync_manager = new \stdClass();
		$sync_manager->sync_queue = range( 3, 9 );

		$this->queue::$max_indexing_op_count = PHP_INT_MAX; // Ensure ratelimiting is disabled

		$this->queue->ratelimit_indexing( true, $sync_manager, 'post' );

		$this->assertEquals( 7, wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count should be 7' );

		foreach ( $sync_manager->sync_queue as $object_id ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE `object_id` = %d AND `object_type` = 'post' AND `status` = 'queued'", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object_id
				)
			);

			$this->assertCount( 0, $results, "should be 0 occurrences of post id #$object_id in queue table" );
		}

		$sync_manager->sync_queue = range( 10, 20 );

		$this->queue->ratelimit_indexing( true, $sync_manager, 'post' );

		$this->assertEquals( 18, wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count should be 18' );

		foreach ( $sync_manager->sync_queue as $object_id ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE `object_id` = %d AND `object_type` = 'post' AND `status` = 'queued'", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object_id
				)
			);

			$this->assertCount( 0, $results, "should be 0 occurrences of post id #$object_id in queue table" );
		}
	}

	/**
	 * Ensure that the queue is populated if ratelimiting is triggered
	 */
	public function test_ratelimit_indexing_queue_should_be_populated_if_ratelimiting_enabled() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$sync_manager = new \stdClass();
		$sync_manager->sync_queue = range( 3, 9 );

		$this->queue::$max_indexing_op_count = 0; // Ensure ratelimiting is enabled

		$this->queue->ratelimit_indexing( true, $sync_manager, 'post' );

		$this->assertEquals( 7, wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count should be 7' );

		foreach ( $sync_manager->sync_queue as $object_id ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE `object_id` = %d AND `object_type` = 'post' AND `status` = 'queued'", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object_id
				)
			);

			$this->assertCount( 1, $results, "should be 1 occurrence of post id #$object_id in queue table" );
		}

		$sync_manager->sync_queue = range( 10, 20 );

		$this->queue->ratelimit_indexing( true, $sync_manager, 'post' );

		$this->assertEquals( 18, wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count should be 18' );

		foreach ( $sync_manager->sync_queue as $object_id ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE `object_id` = %d AND `object_type` = 'post' AND `status` = 'queued'", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object_id
				)
			);

			$this->assertCount( 1, $results, "should be 0 occurrences of post id #$object_id in queue table" );
		}
	}

	public function test__ratelimit_indexing__handles_start_correctly() {
		$partially_mocked_queue = $this->getMockBuilder( \Automattic\VIP\Search\Queue::class )
			->setMethods( [
				'handle_index_limiting_start_timestamp',
				'maybe_alert_for_prolonged_index_limiting',
				'record_ratelimited_stat',
				'intercept_ep_sync_manager_indexing',
			] )
			->getMock();

		$partially_mocked_queue->logger = $this->getMockBuilder( \Automattic\VIP\Logstash\Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$partially_mocked_queue->logger->expects( $this->once() )
				->method( 'log' )
				->with(
					$this->equalTo( 'warning' ),
					$this->equalTo( 'vip_search_indexing_rate_limiting' ),
					$this->equalTo(
						'Application 123 - http://example.org has triggered Elasticsearch indexing rate limiting, which will last for 300 seconds. Large batch indexing operations are being queued for indexing in batches over time.'
					),
					$this->anything()
				);

		$sync_manager = new \stdClass();
		$sync_manager->sync_queue = range( 3, 9 );

		$partially_mocked_queue::$max_indexing_op_count = 0; // Ensure ratelimiting is enabled

		$partially_mocked_queue->expects( $this->once() )->method( 'handle_index_limiting_start_timestamp' );
		$partially_mocked_queue->expects( $this->once() )->method( 'maybe_alert_for_prolonged_index_limiting' );

		$partially_mocked_queue->ratelimit_indexing( true, $sync_manager, 'post' );
	}

	public function test__ratelimit_indexing__clears_start_correctly() {
		$partially_mocked_queue = $this->getMockBuilder( \Automattic\VIP\Search\Queue::class )
			->setMethods( [
				'clear_index_limiting_start_timestamp',
			] )
			->getMock();

		$partially_mocked_queue->expects( $this->once() )->method( 'clear_index_limiting_start_timestamp' );

		$sync_manager = new \stdClass();
		$sync_manager->sync_queue = range( 3, 9 );

		$partially_mocked_queue->ratelimit_indexing( true, $sync_manager, 'post' );
	}

	public function test__record_ratelimited_stat__records_statsd() {
		$increment = 14;
		$indexable_slug = 'post';

		$partially_mocked_queue = $this->getMockBuilder( \Automattic\VIP\Search\Queue::class )
			->setMethods( [ 'maybe_update_stat' ] )
			->getMock();

		$indexables_mock = $this->createMock( \ElasticPress\Indexables::class );

		$indexables_mock->method( 'get' )
			->willReturn( $this->createMock( \ElasticPress\Indexable::class ) );

		$partially_mocked_queue->expects( $this->once() )
			->method( 'maybe_update_stat' )
			->with( 'com.wordpress.elasticsearch.unknown.unknown.index_ratelimited', $increment );

		$partially_mocked_queue->init();
		$partially_mocked_queue->indexables = $indexables_mock;

		$partially_mocked_queue->record_ratelimited_stat( $increment, $indexable_slug );
	}

	/**
	 * Ensure the logic for checking if index ratelimiting is turned on works
	 */
	public function test__is_indexing_ratelimited() {
		$this->assertFalse( $this->queue::is_indexing_ratelimited(), 'should be false since the object cache should be empty' );

		$this->queue::turn_on_index_ratelimiting();

		$this->assertTrue( $this->queue::is_indexing_ratelimited(), 'should be true since Queue::turn_on_index_ratelimiting was called' );
	}

	/**
	 * Ensure the incrementor for tracking indexing operations counts behaves properly
	 */
	public function test__index_count_incr() {
		$index_count_incr = self::get_method( 'index_count_incr' );

		// Reset cache key
		wp_cache_delete( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP );

		$this->assertEquals( 1, $index_count_incr->invokeArgs( $this->queue, [] ), 'initial value should be 1' );

		for ( $i = 2; $i < 10; $i++ ) {
			$this->assertEquals( $i, $index_count_incr->invokeArgs( $this->queue, [] ), 'value should increment with loop' );
		}

		$this->assertEquals( 14, $index_count_incr->invokeArgs( $this->queue, [ 5 ] ), 'should increment properly without using the default increment of 1' );
	}

	public function test__count_jobs_all_should_be_0_by_default() {
		$this->assertEquals( 0, $this->queue->count_jobs( 'all', 'all' ) );
	}

	public function test__count_jobs_all_should_return_the_queue_count() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		foreach ( range( 0, 9 ) as $object_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table_name ( `object_id` ) VALUES ( %d )", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object_id
				)
			);
		}

		$this->assertEquals( 10, $this->queue->count_jobs( 'all', 'all' ) );
	}

	public function test__count_jobs_all_statuses_should_return_proper_count_by_object_type() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		// Add junk rows that shouldn't be picked up in count_jobs
		foreach ( range( 0, 9 ) as $object_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table_name ( `object_id` ) VALUES ( %d )", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object_id
				)
			);
		}

		foreach ( range( 0, 2 ) as $object_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table_name ( `object_id`, `object_type` ) VALUES ( %d, %s )", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object_id,
					'random object type'
				)
			);
		}

		$this->assertEquals( 13, $this->queue->count_jobs( 'all', 'all' ), 'total queue size should be 13' );
		$this->assertEquals( 3, $this->queue->count_jobs( 'all', 'random object type' ), "queue size for 'random object type' should be 3" );
	}

	public function organize_jobs_by_index_version_and_type_data() {
		return array(
			array(
				// Input
				array(
					(object) array(
						'object_id' => 1,
						'object_type' => 'post',
						'index_version' => 1,
					),
					(object) array(
						'object_id' => 2,
						'object_type' => 'post',
						'index_version' => 1,
					),
					(object) array(
						'object_id' => 3,
						'object_type' => 'post',
						'index_version' => 2,
					),
					(object) array(
						'object_id' => 4,
						'object_type' => 'post',
						'index_version' => 2,
					),
					(object) array(
						'object_id' => 1,
						'object_type' => 'user',
						'index_version' => 1,
					),
					(object) array(
						'object_id' => 2,
						'object_type' => 'user',
						'index_version' => 2,
					),
				),
				// Expected
				array(
					1 => array(
						'post' => array(
							(object) array(
								'object_id' => 1,
								'object_type' => 'post',
								'index_version' => 1,
							),
							(object) array(
								'object_id' => 2,
								'object_type' => 'post',
								'index_version' => 1,
							),
						),
						'user' => array(
							(object) array(
								'object_id' => 1,
								'object_type' => 'user',
								'index_version' => 1,
							),
						),
					),
					2 => array(
						'post' => array(
							(object) array(
								'object_id' => 3,
								'object_type' => 'post',
								'index_version' => 2,
							),
							(object) array(
								'object_id' => 4,
								'object_type' => 'post',
								'index_version' => 2,
							),
						),
						'user' => array(
							(object) array(
								'object_id' => 2,
								'object_type' => 'user',
								'index_version' => 2,
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @dataProvider organize_jobs_by_index_version_and_type_data
	 */
	public function test_organize_jobs_by_index_version_and_type( $input, $expected ) {
		$organized = $this->queue->organize_jobs_by_index_version_and_type( $input );

		$this->assertEquals( $expected, $organized );
	}

	public function test__delete_jobs_for_index_version() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = array(
			array(
				'id' => 1,
				'type' => 'post',
				'version' => 1,
			),
			array(
				'id' => 2,
				'type' => 'post',
				'version' => 1,
			),
			array(
				'id' => 3,
				'type' => 'post',
				'version' => 2,
			),
			array(
				'id' => 4,
				'type' => 'post',
				'version' => 3,
			),
		);

		foreach ( $objects as $object ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table_name ( `object_id`, `object_type`, `status`, `index_version`, `queued_time` ) VALUES ( %d, %s, %s, %d, %s )", // @codingStandardsIgnoreLine
					$object['id'],
					$object['type'],
					'queued',
					$object['version'],
					'2020-10-31 00:00:00'
				)
			);
		}

		$this->queue->delete_jobs_for_index_version( 'post', 2 );

		$results = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE 1", 'ARRAY_A' ); // @codingStandardsIgnoreLine

		$this->assertEquals(
			array(
				array(
					'job_id' => '1',
					'object_id' => '1',
					'object_type' => 'post',
					'priority' => '5',
					'start_time' => null,
					'status' => 'queued',
					'index_version' => '1',
					'queued_time' => '2020-10-31 00:00:00',
					'scheduled_time' => null,
				),
				array(
					'job_id' => '2',
					'object_id' => '2',
					'object_type' => 'post',
					'priority' => '5',
					'start_time' => null,
					'status' => 'queued',
					'index_version' => '1',
					'queued_time' => '2020-10-31 00:00:00',
					'scheduled_time' => null,
				),
				array(
					'job_id' => '4',
					'object_id' => '4',
					'object_type' => 'post',
					'priority' => '5',
					'start_time' => null,
					'status' => 'queued',
					'index_version' => '3',
					'queued_time' => '2020-10-31 00:00:00',
					'scheduled_time' => null,
				),
			),
			$results,
			'should match what you\'d expect from deleting index version 2'
		);

		$this->queue->delete_jobs_for_index_version( 'post', 1 );

		$results = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE 1", 'ARRAY_A' ); // @codingStandardsIgnoreLine

		$this->assertEquals(
			array(
				array(
					'job_id' => '4',
					'object_id' => '4',
					'object_type' => 'post',
					'priority' => '5',
					'start_time' => null,
					'status' => 'queued',
					'index_version' => '3',
					'queued_time' => '2020-10-31 00:00:00',
					'scheduled_time' => null,
				),
			),
			$results,
			'should match what you\'d expect from deleting index version 1 and index version 2'
		);
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
				'vip_search_index_count_period',
				'vip_search_index_count_period should not be set below 60 seconds.',
				'vip_search_index_count_period should not be set above 7200 seconds.',
			],
			[
				'vip_search_max_indexing_op_count',
				'vip_search_max_indexing_op_count should not be below 10 queries per second.',
				'vip_search_max_indexing_op_count should not exceed 250 queries per second.',
			],
			[
				'vip_search_index_ratelimiting_duration',
				'vip_search_index_ratelimiting_duration should not be set below 60 seconds.',
				'vip_search_index_ratelimiting_duration should not be set above 1200 seconds.',
			],
			[
				'vip_search_max_indexing_count',
				'vip_search_max_sync_indexing_count should not be below 2500.',
				'vip_search_max_sync_indexing_count should not be above 25000.',
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

		$this->expectException( 'PHPUnit_Framework_Error_Notice' );
		$this->expectExceptionMessage(
			sprintf(
				'add_filter was called <strong>incorrectly</strong>. %s should be an integer. Please see <a href="https://wordpress.org/support/article/debugging-in-wordpress/">Debugging in WordPress</a> for more information. (This message was added in version 5.5.3.)',
				$filter
			)
		);

		$this->queue->apply_settings();
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

		$this->expectException( 'PHPUnit_Framework_Error_Notice' );
		$this->expectExceptionMessage(
			sprintf(
				'add_filter was called <strong>incorrectly</strong>. %s Please see <a href="https://wordpress.org/support/article/debugging-in-wordpress/">Debugging in WordPress</a> for more information. (This message was added in version 5.5.3.)',
				$too_low_message
			)
		);

		$this->queue->apply_settings();
	}

	/**
	 * @dataProvider vip_search_ratelimiting_filter_data
	 */
	public function test__filter__vip_search_ratelimiting_too_high_validation( $filter, $too_low_message, $too_high_message ) {
		if ( empty( $too_high_message ) ) {
			$this->markTestSkipped( "$filter doesn't have a too high message" );
		}

		add_filter(
			$filter,
			function() {
				return PHP_INT_MAX;
			}
		);

		$this->expectException( 'PHPUnit_Framework_Error_Notice' );
		$this->expectExceptionMessage(
			sprintf(
				'add_filter was called <strong>incorrectly</strong>. %s Please see <a href="https://wordpress.org/support/article/debugging-in-wordpress/">Debugging in WordPress</a> for more information. (This message was added in version 5.5.3.)',
				$too_high_message
			)
		);

		$this->queue->apply_settings();
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
	public function test__maybe_update_stat_sampling_keep() {
		$this->queue::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->queue->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->once() )
			->method( 'update_stats' )
			->with( 'test', 5, 1, 'c' );

		$this->queue->maybe_update_stat( 'test', 5 );
	}

	/**
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_update_stat_sampling_drop() {
		$this->queue::$stat_sampling_drop_value = 0; // Guarantee a sampling drop

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->queue->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'update_stats' );

		$this->queue->maybe_update_stat( 'test', 5 );
	}

	/**
	 * @dataProvider stat_sampling_invalid_stat_param_data
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_update_stat_sampling_invalid_stat_param( $stat ) {
		$this->queue::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->queue->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'update_stats' );

		$this->queue->maybe_update_stat( $stat, 5 );
	}

	/**
	 * @dataProvider stat_sampling_invalid_value_param_data
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_update_stat_sampling_invalid_value_param( $value ) {
		$this->queue::$stat_sampling_drop_value = 11; // Guarantee a sampling keep

		$statsd_mocked = $this->createMock( \Automattic\VIP\StatsD::class );

		$this->queue->statsd = $statsd_mocked;

		$statsd_mocked->expects( $this->never() )
			->method( 'update_stats' );

		$this->queue->maybe_update_stat( 'test', $value );
	}

	public function test__log_index_ratelimiting_start() {
		$this->queue->logger = $this->getMockBuilder( \Automattic\VIP\Logstash\Logger::class )
				->setMethods( [ 'log' ] )
				->getMock();

		$this->queue->logger->expects( $this->once() )
				->method( 'log' )
				->with(
					$this->equalTo( 'warning' ),
					$this->equalTo( 'vip_search_indexing_rate_limiting' ),
					$this->equalTo(
						'Application 123 - http://example.org has triggered Elasticsearch indexing rate limiting, which will last for 300 seconds. Large batch indexing operations are being queued for indexing in batches over time.'
					),
					$this->anything()
				);

		$this->queue->log_index_ratelimiting_start();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\Queue' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}
}
