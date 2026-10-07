<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Logstash\Logger;
use ElasticPress\Indexables;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use wpdb;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\get_static_property_as_public;

require_once __DIR__ . '/trait-es-http-mock.php';
require_once __DIR__ . '/trait-search-test-bootstrap.php';

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class Queue_Test extends WP_UnitTestCase {
	use ES_HTTP_Mock;
	use Search_Test_Bootstrap;

	/** @var Search */
	private $es;

	/** @var Queue */
	private $queue;

	/** @var SyncManager */
	private $sync_manager;

	/** @var Search|null */
	private $search_singleton;

	public function setUp(): void {
		parent::setUp();

		// Users indexable is registered because we have tests that queue user objects
		$this->es = $this->boot_search( [
			'VIP_ELASTICSEARCH_ENDPOINTS'      => array( 'https://elasticsearch:9200' ),
			'VIP_SEARCH_ENABLE_ASYNC_INDEXING' => true,
		], true );

		// Rate limited indexing reports to Prometheus through Search::instance() once the collector is loaded.
		// Without an instance, that call would boot a second Search whose init() resets the queue's static limits.
		$singleton              = get_static_property_as_public( Search::class, 'instance' );
		$this->search_singleton = $singleton->getValue();
		$singleton->setValue( null, $this->es );

		$this->queue = $this->es->queue;
		$this->queue->schema->prepare_table();
		$this->queue->empty_queue();

		add_filter( 'ep_do_intercept_request', [ $this, 'filter_index_exists_request_ok' ], PHP_INT_MAX, 5 );
		$this->add_es_http_mock();

		$indexable          = Indexables::factory()->get( 'post' );
		$this->sync_manager = $indexable->sync_manager;
	}

	public function tearDown(): void {
		$this->remove_es_http_mock();
		get_static_property_as_public( Search::class, 'instance' )->setValue( null, $this->search_singleton );
		parent::tearDown();
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
		$objects = array(
			array(
				'id'   => 1,
				'type' => 'post',
			),
			array(
				'id'   => 1,
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
			$this->assertCount( 1, $this->get_queued_rows( $object['type'], $object['id'] ) );
		}
	}

	public function test_rate_limiting_of_repeat_indexing() {
		$objects = array(
			array(
				'id'   => 1,
				'type' => 'post',
			),
			array(
				'id'   => 1,
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

			$rows = $this->get_queued_rows( $object['type'], $object['id'] );

			$expected_start_time = gmdate( 'Y-m-d H:i:s', $now + $this->queue->get_index_interval_time() );

			$this->assertCount( 1, $rows );
			$this->assertEquals( $expected_start_time, $rows[0]->start_time );
		}
	}

	public function test_job_priorities(): void {
		$this->queue->queue_object( 1, 'post', [ 'priority' => 2 * Queue::INDEX_DEFAULT_PRIORITY ] );
		$this->queue->queue_object( 2, 'post', [ 'priority' => 1 ] );
		$this->queue->queue_object( 3, 'post' );

		$jobs = $this->queue->checkout_jobs();

		self::assertIsArray( $jobs );
		self::assertCount( 3, $jobs );

		$expected = [ 2, 3, 1 ];
		$actual   = array_column( $jobs, 'object_id' );
		self::assertEquals( $expected, $actual );
	}

	public function test_requeue_with_different_priority(): void {
		$this->queue->queue_object( 1, 'post', [ 'priority' => 4 * Queue::INDEX_DEFAULT_PRIORITY ] );
		$this->queue->queue_object( 1, 'post', [ 'priority' => 2 * Queue::INDEX_DEFAULT_PRIORITY ] );

		$jobs = $this->queue->checkout_jobs();

		self::assertIsArray( $jobs );
		self::assertCount( 1, $jobs );
		self::assertArrayHasKey( 0, $jobs );

		$job = $jobs[0];
		self::assertIsObject( $job );
		self::assertTrue( property_exists( $job, 'priority' ) );
		self::assertSame( 2 * Queue::INDEX_DEFAULT_PRIORITY, (int) $job->priority );
	}

	public function test_checkout_jobs() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = array(
			array(
				'id'   => 1,
				'type' => 'post',
			),
			array(
				'id'   => 2,
				'type' => 'post',
			),
			array(
				'id'   => 3,
				'type' => 'post',
			),
			array(
				'id'   => 1000,
				'type' => 'user',
			),
		);

		$now = time();

		// Insert first job, set it to scheduled, so that we get some queued objects that are rate limited
		$this->queue->queue_object( $objects[0]['id'], $objects[0]['type'] );
		$this->queue->set_last_index_time( $objects[0]['id'], $objects[0]['type'], $now );
		$job_id = $this->get_min_object_id_from_queue();
		$this->queue->update_job( $job_id, array( 'status' => 'scheduled' ) );

		$expected_job_ids = [];

		// Insert the jobs
		foreach ( $objects as $object ) {
			$expected_job_ids[] = $this->queue->queue_object( $object['id'], $object['type'] );
		}

		$expected_scheduled_time_low  = gmdate( 'Y-m-d H:i:s' );
		$jobs                         = $this->queue->checkout_jobs( 10 );
		$expected_scheduled_time_high = gmdate( 'Y-m-d H:i:s' );

		// Should have the right status and scheduled_time set
		foreach ( $jobs as $job ) {
			self::assertEquals( 'scheduled', $job->status, "Job $job->job_id was expected to have status 'scheduled'" );
			self::assertGreaterThanOrEqual( $expected_scheduled_time_low, $job->scheduled_time, "Job {$job->job_id} has the wrong scheduled_time" );
			self::assertLessThanOrEqual( $expected_scheduled_time_high, $job->scheduled_time, "Job {$job->job_id} has the wrong scheduled_time" );
		}

		$object_ids = wp_list_pluck( $jobs, 'object_id' );

		// Should not have received post 1, because it is currently running, then scheduled again for the future
		$expected_object_ids = array( 2, 3, 1000 );

		$this->assertEquals( $expected_object_ids, $object_ids, 'Checked out jobs ids do not match what was expected' );

		$ids_where_string = implode( ', ', $expected_job_ids );

		$not_scheduled_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE `job_id` IN ({$ids_where_string}) AND `status` != 'scheduled'" );

		// There should be 1 that now isn't marked as running, and that's post 1 which was rescheduled again for the future (rate limited)
		$this->assertEquals( 1, $not_scheduled_count );
	}

	public function test_offload_indexing_to_queue() {
		$this->add_posts_to_queue( range( 1, 3 ) );

		// Make sure we're not already bailing on EP indexing, otherwise the test isn't doing anything
		$current_bail = apply_filters( 'pre_ep_index_sync_queue', false, $this->sync_manager, 'post' );

		$this->assertFalse( $current_bail );

		// Offload indexing
		$this->queue->offload_indexing_to_queue();

		// Now the filter should return true to bail early from EP indexing
		$current_bail = apply_filters( 'pre_ep_index_sync_queue', false, $this->sync_manager, 'post' );

		$this->assertTrue( $current_bail );
	}

	public function test_update_job() {
		$this->queue->queue_object( 1, 'post' );

		$job = $this->queue->get_next_job_for_object( 1, 'post' );

		$this->assertEquals( 'queued', $job->status );
		$this->assertNotEquals( '2020-01-01 00:00:00', $job->start_time );

		$updated = $this->queue->update_job( $job->job_id, array( 'start_time' => '2020-01-01 00:00:00' ) );

		$this->assertSame( 1, $updated );

		$job = $this->queue->get_next_job_for_object( 1, 'post' );

		$this->assertEquals( 'queued', $job->status );
		$this->assertEquals( '2020-01-01 00:00:00', $job->start_time );
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

	public function test_update_jobs_with_where_condition() {
		$this->queue->queue_object( 1, 'post' );
		$this->queue->queue_object( 2, 'post' );

		$job1 = $this->queue->get_next_job_for_object( 1, 'post' );
		$job2 = $this->queue->get_next_job_for_object( 2, 'post' );

		// Set job1 to 'scheduled' status
		$this->queue->update_job( $job1->job_id, array( 'status' => 'scheduled' ) );

		// Try to update both jobs to 'running', but only if status is 'scheduled'
		// job1 is 'scheduled', job2 is 'queued', so only job1 should be updated
		$this->queue->update_jobs( array( $job1->job_id, $job2->job_id ), array( 'status' => 'running' ), 'scheduled' );

		// Verify job1 was updated
		$job1_updated = $this->queue->get_jobs_by_ids( array( $job1->job_id ) );
		$this->assertEquals( 'running', $job1_updated[0]->status );

		// Verify job2 was NOT updated (still 'queued')
		$job2_updated = $this->queue->get_jobs_by_ids( array( $job2->job_id ) );
		$this->assertEquals( 'queued', $job2_updated[0]->status );
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

		$count_default   = $this->queue->count_jobs( 'queued', 'post' );
		$count_version_2 = $this->queue->count_jobs( 'queued', 'post', array( 'index_version' => 2 ) );

		$this->assertEquals( 1, $count_default, 'Wrong count for default index version' );
		$this->assertEquals( 2, $count_version_2, 'Wrong count for index version 2' );
	}

	public function get_next_job_for_object_data() {
		return [
			'current index version'  => [ array(), 1 ],
			'specific index version' => [ array( 'index_version' => 2 ), 2 ],
		];
	}

	/**
	 * @dataProvider get_next_job_for_object_data
	 */
	public function test_get_next_job_for_object( $options, $expected_index_version ) {
		$this->queue->queue_object( 1, 'post' );
		$this->queue->queue_object( 1, 'post', array( 'index_version' => 2 ) );

		$job = $this->queue->get_next_job_for_object( 1, 'post', $options );

		$this->assertEquals( 1, $job->object_id );
		$this->assertEquals( 'post', $job->object_type );
		$this->assertEquals( 'queued', $job->status );
		$this->assertEquals( null, $job->start_time );
		$this->assertEquals( $expected_index_version, $job->index_version );
	}

	public function test_process_jobs() {
		/** @var wpdb $wpdb */
		global $wpdb;

		$object_ids = self::factory()->post->create_many( 3, array(
			'post_status'  => 'publish',
			'post_title'   => 'Queue indexing title',
			'post_content' => 'Queue indexing content',
		) );

		$bulk_requests = array();
		$capture_bulk  = static function ( $preempt, $args, $url ) use ( &$bulk_requests ) {
			if ( false !== strpos( $url, '/_bulk' ) ) {
				$bulk_requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $capture_bulk, 10, 3 );

		// Add some jobs to the queue
		$this->queue->queue_objects( $object_ids );

		$min_id = $this->get_min_object_id_from_queue();
		$max_id = $min_id + count( $object_ids ) - 1;

		// Have to get by job id and not by object id
		$jobs = $this->queue->get_jobs_by_range( $min_id, $max_id );

		$job_count = $this->queue->count_jobs( 'all' );

		$this->assertEquals( $job_count, count( $object_ids ), 'job count in database should match jobs added to queue' );

		// Set jobs to 'scheduled' status before processing
		$job_ids = wp_list_pluck( $jobs, 'job_id' );
		$this->queue->update_jobs( $job_ids, array( 'status' => 'scheduled' ) );

		try {
			$this->queue->process_jobs( $jobs );
		} finally {
			remove_filter( 'pre_http_request', $capture_bulk, 10 );
		}

		$this->assertNotEmpty( $bulk_requests, 'Processing must send the documents to Elasticsearch.' );
		foreach ( $bulk_requests as $request ) {
			$this->assertSame( $bulk_requests[0]['args']['body'], $request['args']['body'], 'Mirrored requests must index the same documents.' );
		}
		$this->assertSame( 'POST', $bulk_requests[0]['args']['method'] );
		$lines      = preg_split( '/\n+/', trim( $bulk_requests[0]['args']['body'] ) );
		$index_name = Indexables::factory()->get( 'post' )->get_index_name();
		$this->assertCount( 2 * count( $object_ids ), $lines );
		$documents  = array();
		$line_count = count( $lines );
		for ( $offset = 0; $offset < $line_count; $offset += 2 ) {
			$action                  = json_decode( $lines[ $offset ], true );
			$object_id               = (int) $action['index']['_id'];
			$documents[ $object_id ] = json_decode( $lines[ $offset + 1 ], true );
		}
		$indexed_ids = array_keys( $documents );
		sort( $indexed_ids );
		sort( $object_ids );
		$this->assertSame( $object_ids, $indexed_ids );
		$this->assertStringContainsString( '/' . $index_name . '/', $bulk_requests[0]['url'] );
		foreach ( $documents as $document ) {
			$this->assertSame( 'Queue indexing title', $document['post_title'] );
			$this->assertSame( 'Queue indexing content', $document['post_content'] );
		}

		$jobs = $this->queue->get_jobs_by_range( $min_id, $max_id );

		$this->assertEmpty( $jobs, 'jobs should be gone after being processed' );
	}

	public function test_intercept_ep_sync_manager_indexing() {
		$this->add_posts_to_queue( [ 1, 2, 1000 ] );

		$this->queue->intercept_ep_sync_manager_indexing( false, $this->sync_manager, 'post' );

		// And the SyncManager's queue should have been emptied
		$this->assertEmpty( $this->sync_manager->get_sync_queue() );
	}

	public function test_get_jobs_by_range() {
		$this->queue->queue_object( 1000, 'post' );
		$this->queue->queue_object( 2000, 'post' );

		$min_id = $this->get_min_object_id_from_queue();
		$max_id = $min_id + 1;

		$jobs = $this->queue->get_jobs_by_range( $min_id, $max_id );

		$expected_object_ids = array( 1000, 2000 );
		$actual_object_ids   = wp_list_pluck( $jobs, 'object_id' );

		$this->assertEquals( $expected_object_ids, $actual_object_ids );
	}

	public function test_queue_invalid_indexable_type() {
		$id = $this->queue->queue_object( 1000, 'invalid_indexable_type' );

		$this->assertWPError( $id, 'Indexable not found for type invalid_indexable_type' );
	}

	public function test_queue_objects_not_array() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$this->queue->queue_objects( 'Test' );
		$this->queue->queue_objects( 42 );

		$results = $wpdb->get_results( "SELECT * FROM `{$table_name}` WHERE 1", \ARRAY_N );

		$this->assertEquals( 0, count( $results ), 'shouldn\'t add objects to queue if object id list isn\'t an array' );
	}

	public function test_queue_objects_with_specific_index_version() {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		$objects = range( 10, 20 );

		$this->queue->queue_objects( $objects, 'post', array( 'index_version' => 2 ) );

		$results = \wp_list_pluck( $wpdb->get_results( "SELECT object_id FROM `{$table_name}` WHERE `index_version` = 2" ), 'object_id' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertEquals( $objects, $results, 'ids of objects sent to queue don\'t match ids of objects found in the database' );
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
			'status'         => 'scheduled',
			'scheduled_time' => gmdate( 'Y-m-d H:i:s', $deadlocked_time ),
		) );

		// Set the second job to have been scheduled in the far past, to be flagged as deadlocked
		$job2 = $this->queue->get_next_job_for_object( 3000, 'post' );

		$deadlocked_time = time() - $this->queue::DEADLOCK_TIME - ( 3 * DAY_IN_SECONDS );

		$this->queue->update_job( $job2->job_id, array(
			'status'         => 'running',
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
		global $wpdb;

		$original_schema = $this->queue->schema;
		$table_name      = $wpdb->prefix . 'vip_queue_recovery_' . wp_generate_password( 12, false );
		$source_table    = $original_schema->get_table_name();
		$schema          = $this->getMockBuilder( Queue\Schema::class )->onlyMethods( array( 'get_table_name' ) )->getMock();
		$schema->method( 'get_table_name' )->willReturn( $table_name );

		// The ordinary copy preserves production indexes and permits the recovery self-join.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		try {
			$this->assertNotFalse( $wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $table_name, $source_table ) ) );
			$this->queue->schema = $schema;
			$fixtures            = array(
				array( 1000, 'post', 1, 'running' ),
				array( 1000, 'post', 1, 'queued' ),
				array( 1000, 'post', 2, 'scheduled' ),
				array( 2000, 'post', 1, 'queued' ),
				array( 2000, 'user', 1, 'running' ),
				array( 3000, 'post', 1, 'scheduled' ),
				array( 3000, 'post', 1, 'running' ),
			);
			$expected            = array();
			$duplicate_ids       = array();
			foreach ( $fixtures as $offset => $fixture ) {
				list( $object_id, $object_type, $version, $status ) = $fixture;
				$options = array( 'index_version' => $version );
				$this->queue->queue_object( $object_id, $object_type, $options );
				$job = $this->queue->get_next_job_for_object( $object_id, $object_type, $options );
				if ( 'queued' !== $status ) {
					$this->queue->update_job( $job->job_id, array(
						'status'         => $status,
						'scheduled_time' => gmdate( 'Y-m-d H:i:s', time() - Queue::DEADLOCK_TIME - 1 ),
					) );
				}
				if ( 3000 === $object_id ) {
					$duplicate_ids[] = (int) $job->job_id;
				}
				// Only the same-object/type/version duplicates should be removed.
				if ( ! in_array( $offset, array( 0, 6 ), true ) ) {
					$expected[] = array(
						'job_id'        => 3000 === $object_id ? 0 : (int) $job->job_id,
						'object_id'     => $object_id,
						'object_type'   => $object_type,
						'index_version' => $version,
						'status'        => 'queued',
					);
				}
			}

			$this->queue->free_deadlocked_jobs();
			$actual = $wpdb->get_results( $wpdb->prepare( 'SELECT job_id, object_id, object_type, index_version, status FROM %i ORDER BY job_id', $table_name ), ARRAY_A );
			foreach ( $actual as &$job ) {
				$job['job_id']        = (int) $job['job_id'];
				$job['object_id']     = (int) $job['object_id'];
				$job['index_version'] = (int) $job['index_version'];
				if ( 3000 === $job['object_id'] ) {
					$this->assertContains( $job['job_id'], $duplicate_ids );
					$job['job_id'] = 0;
				}
			}
			unset( $job );
			$this->assertSame( $expected, $actual );
		} finally {
			$this->queue->schema = $original_schema;
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
			add_filter( 'query', array( $this, '_create_temporary_tables' ) );
			add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		}
	}

	/**
	 * Ensure that the value passed into the filter is returned if the indexable_slug is not 'post'
	 */
	public function test__ratelimit_indexing_should_pass_bail_if_not_post() {
		$this->assertTrue( $this->queue->ratelimit_indexing( true, '', 'hippo' ), 'should return true since true was passed in' );
		$this->assertFalse( $this->queue->ratelimit_indexing( false, '', 'hippo' ), 'should return false since false was passed in' );
		$this->assertFalse( wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count shouldn\'t exist if function calls all returned early' );
	}

	/**
	 * Ensure that the value passed into the filter is returned if the sync queue is empty
	 */
	public function test__ratelimit_indexing_should_pass_bail_if_sync_queue_empty() {
		$this->assertTrue( $this->queue->ratelimit_indexing( true, $this->sync_manager, 'post' ), 'should return true since true was passed in' );
		$this->assertFalse( $this->queue->ratelimit_indexing( false, $this->sync_manager, 'post' ), 'should return false since false was passed in' );
		$this->assertFalse( wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count shouldn\'t exist if function calls all returned early' );
	}

	/**
	 * Ensure that the queue isn't populated if ratelimiting isn't triggered
	 */
	public function test_ratelimit_indexing_queue_should_be_empty_if_no_ratelimiting() {
		$this->add_posts_to_queue( range( 3, 9 ) );

		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
		$this->queue::$max_indexing_op_count = PHP_INT_MAX; // Ensure ratelimiting is disabled

		$this->queue->ratelimit_indexing( true, $this->sync_manager, 'post' );

		$this->assertEquals( 7, wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count should be 7' );
		$this->assertSame( [], $this->get_post_ids_in_queue_table(), 'no post should be in the queue table' );

		$this->sync_manager->reset_sync_queue();

		$this->add_posts_to_queue( range( 10, 20 ) );

		$this->queue->ratelimit_indexing( true, $this->sync_manager, 'post' );

		$this->assertEquals( 18, wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count should be 18' );
		$this->assertSame( [], $this->get_post_ids_in_queue_table(), 'no post should be in the queue table' );
	}

	/**
	 * Ensure that the queue is populated if ratelimiting is triggered
	 */
	public function test_ratelimit_indexing_queue_should_be_populated_if_ratelimiting_enabled() {
		$this->add_posts_to_queue( range( 3, 9 ) );

		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
		$this->queue::$max_indexing_op_count = 0; // Ensure ratelimiting is enabled

		$this->queue->ratelimit_indexing( true, $this->sync_manager, 'post' );

		$this->assertEquals( 7, wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count should be 7' );
		$this->assertSame( range( 3, 9 ), $this->get_post_ids_in_queue_table(), 'each post should be in the queue table once' );

		$this->add_posts_to_queue( range( 10, 20 ) );

		$this->queue->ratelimit_indexing( true, $this->sync_manager, 'post' );

		$this->assertEquals( 18, wp_cache_get( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP ), 'indexing ops count should be 18' );
		$this->assertSame( range( 3, 20 ), $this->get_post_ids_in_queue_table(), 'each post should be in the queue table once' );
		// Once ratelimiting is on, new jobs wait in the queue instead of being scheduled right away.
		$queued = array_map( 'intval', wp_list_pluck( $this->get_queued_rows( 'post' ), 'object_id' ) );
		sort( $queued );
		$this->assertSame( range( 10, 20 ), $queued, 'ratelimited posts should be queued' );
	}

	public function test__ratelimit_indexing__handles_start_correctly() {
		/** @var MockObject&Queue */
		$partially_mocked_queue = $this->getMockBuilder( Queue::class )
			->onlyMethods( [
				'handle_index_limiting_start_timestamp',
				'maybe_alert_for_prolonged_index_limiting',
				'intercept_ep_sync_manager_indexing',
			] )
			->getMock();

		/** @var MockObject&Logger */
		$partially_mocked_queue->logger = $this->getMockBuilder( Logger::class )
			->onlyMethods( [ 'log' ] )
			->getMock();

		$partially_mocked_queue->logger->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( 'warning' ),
				$this->equalTo( 'search_indexing_rate_limiting' ),
				$this->equalTo(
					'Application 123 - http://example.org has triggered Elasticsearch indexing rate limiting, which will last for 300 seconds. Large batch indexing operations are being queued for indexing in batches over time.'
				),
				$this->anything()
			);

		$post_ids = range( 3, 9 );
		$this->add_posts_to_queue( $post_ids );

		$partially_mocked_queue::$max_indexing_op_count = 0; // Ensure ratelimiting is enabled

		$partially_mocked_queue->expects( $this->once() )->method( 'handle_index_limiting_start_timestamp' );
		$partially_mocked_queue->expects( $this->once() )->method( 'maybe_alert_for_prolonged_index_limiting' );

		$partially_mocked_queue->ratelimit_indexing( true, $this->sync_manager, 'post' );
	}

	public function test__ratelimit_indexing__clears_start_correctly() {
		/** @var MockObject&Queue */
		$partially_mocked_queue = $this->getMockBuilder( Queue::class )
			->onlyMethods( [
				'clear_index_limiting_start_timestamp',
			] )
			->getMock();

		$partially_mocked_queue->expects( $this->once() )->method( 'clear_index_limiting_start_timestamp' );

		$post_ids = range( 3, 9 );
		$this->add_posts_to_queue( $post_ids );

		$partially_mocked_queue->ratelimit_indexing( true, $this->sync_manager, 'post' );
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
		$index_count_incr = get_class_method_as_public( Queue::class, 'index_count_incr' );

		// Reset cache key
		wp_cache_delete( $this->queue::INDEX_COUNT_CACHE_KEY, $this->queue::INDEX_COUNT_CACHE_GROUP );

		$this->assertEquals( 1, $index_count_incr->invokeArgs( $this->queue, [] ), 'initial value should be 1' );

		for ( $i = 2; $i < 10; $i++ ) {
			$this->assertEquals( $i, $index_count_incr->invokeArgs( $this->queue, [] ), 'value should increment with loop' );
		}

		$this->assertEquals( 14, $index_count_incr->invokeArgs( $this->queue, [ 5 ] ), 'should increment properly without using the default increment of 1' );
	}

	public function test__count_jobs_all_statuses_should_return_proper_count_by_object_type() {
		$this->assertEquals( 0, $this->queue->count_jobs( 'all', 'all' ), 'queue should be empty before inserting jobs' );

		// Add junk rows that shouldn't be picked up in count_jobs
		$this->insert_raw_jobs( array_map( fn( $object_id ) => [ 'object_id' => $object_id ], range( 0, 9 ) ) );
		$this->insert_raw_jobs( array_map( fn( $object_id ) => [
			'object_id'   => $object_id,
			'object_type' => 'random object type',
		], range( 0, 2 ) ) );

		$this->assertEquals( 13, $this->queue->count_jobs( 'all', 'all' ), 'total queue size should be 13' );
		$this->assertEquals( 3, $this->queue->count_jobs( 'all', 'random object type' ), "queue size for 'random object type' should be 3" );
	}

	public function organize_jobs_by_index_version_and_type_data() {
		return array(
			array(
				// Input
				array(
					(object) array(
						'object_id'     => 1,
						'object_type'   => 'post',
						'index_version' => 1,
					),
					(object) array(
						'object_id'     => 2,
						'object_type'   => 'post',
						'index_version' => 1,
					),
					(object) array(
						'object_id'     => 3,
						'object_type'   => 'post',
						'index_version' => 2,
					),
					(object) array(
						'object_id'     => 4,
						'object_type'   => 'post',
						'index_version' => 2,
					),
					(object) array(
						'object_id'     => 1,
						'object_type'   => 'user',
						'index_version' => 1,
					),
					(object) array(
						'object_id'     => 2,
						'object_type'   => 'user',
						'index_version' => 2,
					),
				),
				// Expected
				array(
					1 => array(
						'post' => array(
							(object) array(
								'object_id'     => 1,
								'object_type'   => 'post',
								'index_version' => 1,
							),
							(object) array(
								'object_id'     => 2,
								'object_type'   => 'post',
								'index_version' => 1,
							),
						),
						'user' => array(
							(object) array(
								'object_id'     => 1,
								'object_type'   => 'user',
								'index_version' => 1,
							),
						),
					),
					2 => array(
						'post' => array(
							(object) array(
								'object_id'     => 3,
								'object_type'   => 'post',
								'index_version' => 2,
							),
							(object) array(
								'object_id'     => 4,
								'object_type'   => 'post',
								'index_version' => 2,
							),
						),
						'user' => array(
							(object) array(
								'object_id'     => 2,
								'object_type'   => 'user',
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
				'id'      => 1,
				'type'    => 'post',
				'version' => 1,
			),
			array(
				'id'      => 2,
				'type'    => 'post',
				'version' => 1,
			),
			array(
				'id'      => 3,
				'type'    => 'post',
				'version' => 2,
			),
			array(
				'id'      => 4,
				'type'    => 'post',
				'version' => 3,
			),
		);

		$this->insert_raw_jobs( array_map( fn( $object ) => [
			'object_id'     => $object['id'],
			'object_type'   => $object['type'],
			'status'        => 'queued',
			'index_version' => $object['version'],
			'queued_time'   => '2020-10-31 00:00:00',
		], $objects ) );

		$this->queue->delete_jobs_for_index_version( 'post', 2 );

		$results = $wpdb->get_results( "SELECT object_id, object_type, priority, start_time, status, index_version, queued_time, scheduled_time FROM {$table_name} WHERE 1 ORDER BY job_id", 'ARRAY_A' );

		$this->assertEquals(
			array(
				array(
					'object_id'      => '1',
					'object_type'    => 'post',
					'priority'       => '5',
					'start_time'     => null,
					'status'         => 'queued',
					'index_version'  => '1',
					'queued_time'    => '2020-10-31 00:00:00',
					'scheduled_time' => null,
				),
				array(
					'object_id'      => '2',
					'object_type'    => 'post',
					'priority'       => '5',
					'start_time'     => null,
					'status'         => 'queued',
					'index_version'  => '1',
					'queued_time'    => '2020-10-31 00:00:00',
					'scheduled_time' => null,
				),
				array(
					'object_id'      => '4',
					'object_type'    => 'post',
					'priority'       => '5',
					'start_time'     => null,
					'status'         => 'queued',
					'index_version'  => '3',
					'queued_time'    => '2020-10-31 00:00:00',
					'scheduled_time' => null,
				),
			),
			$results,
			'should match what you\'d expect from deleting index version 2'
		);

		$this->queue->delete_jobs_for_index_version( 'post', 1 );

		$results = $wpdb->get_results( "SELECT object_id, object_type, priority, start_time, status, index_version, queued_time, scheduled_time FROM {$table_name} WHERE 1 ORDER BY job_id", 'ARRAY_A' );

		$this->assertEquals(
			array(
				array(
					'object_id'      => '4',
					'object_type'    => 'post',
					'priority'       => '5',
					'start_time'     => null,
					'status'         => 'queued',
					'index_version'  => '3',
					'queued_time'    => '2020-10-31 00:00:00',
					'scheduled_time' => null,
				),
			),
			$results,
			'should match what you\'d expect from deleting index version 1 and index version 2'
		);
	}

	public function vip_search_ratelimiting_filter_data() {
		$bounds_messages = array(
			'vip_search_index_count_period'          => array(
				'vip_search_index_count_period should not be set below 60 seconds.',
				'vip_search_index_count_period should not be set above 7200 seconds.',
			),
			'vip_search_max_indexing_op_count'       => array(
				'vip_search_max_indexing_op_count should not be below 10 queries per second.',
				'vip_search_max_indexing_op_count should not exceed 250 queries per second.',
			),
			'vip_search_index_ratelimiting_duration' => array(
				'vip_search_index_ratelimiting_duration should not be set below 60 seconds.',
				'vip_search_index_ratelimiting_duration should not be set above 1200 seconds.',
			),
			'vip_search_max_indexing_count'          => array(
				'vip_search_max_sync_indexing_count should not be below 2500.',
				'vip_search_max_sync_indexing_count should not be above 25000.',
			),
		);

		$data = array();
		foreach ( $bounds_messages as $filter => list( $too_low_message, $too_high_message ) ) {
			$data[ "{$filter} not numeric" ] = array( $filter, '30.ffr', "{$filter} should be an integer." );
			$data[ "{$filter} too low" ]     = array( $filter, 0, $too_low_message );
			$data[ "{$filter} too high" ]    = array( $filter, PHP_INT_MAX, $too_high_message );
		}

		return $data;
	}

	/**
	 * @dataProvider vip_search_ratelimiting_filter_data
	 */
	public function test__filter__vip_search_ratelimiting_validation( $filter, $value, $expected_message ) {
		add_filter(
			$filter,
			function () use ( $value ) {
				return $value;
			}
		);

		$this->setExpectedIncorrectUsage( 'add_filter' );
		$messages = $this->get_doing_it_wrong_messages( [ $this->queue, 'apply_settings' ] );

		$this->assertContains( $expected_message, $messages );
	}

	public function test__no_index_queueing() {
		remove_filter( 'ep_do_intercept_request', [ $this, 'filter_index_exists_request_ok' ], PHP_INT_MAX );
		add_filter( 'ep_do_intercept_request', [ $this, 'filter_index_exists_request_bad' ], PHP_INT_MAX, 5 );

		$result = $this->queue->queue_object( 1000, 'post' );
		$this->assertWPError( $result );

		$object_ids = array(
			'12',
			'45',
			'89',
			'246',
		);
		$result     = $this->queue->queue_objects( $object_ids );
		$this->assertNull( $result );

		remove_filter( 'ep_do_intercept_request', [ $this, 'filter_index_exists_request_bad' ], PHP_INT_MAX );
	}

	/**
	 * We need to fake the bad response from the ES server to avoid the actual request.
	 */
	public function filter_index_exists_request_bad( $request, $query, $args, $failures, $type ) {
		if ( 'index_exists' === $type ) {
			return [
				'response' => [ 'code' => 404 ],
				'body'     => [],
			];
		}
		return $request;
	}

	/**
	 * Helper function for adding an array of post objects to the sync manager queue.
	 */
	protected function add_posts_to_queue( $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			$this->sync_manager->add_to_queue( $post_id );
		}
	}

	/**
	 * Returns the queued jobs of an object type, optionally only those of one object.
	 */
	private function get_queued_rows( string $object_type, ?int $object_id = null ): array {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();
		$where      = $wpdb->prepare( '`object_type` = %s AND `status` = %s', $object_type, 'queued' );
		if ( null !== $object_id ) {
			$where .= $wpdb->prepare( ' AND `object_id` = %d', $object_id );
		}

		return $wpdb->get_results( "SELECT * FROM `{$table_name}` WHERE {$where}" );
	}

	/**
	 * Returns the sorted object IDs of all post jobs in the queue table, whatever their status.
	 *
	 * @return int[]
	 */
	private function get_post_ids_in_queue_table(): array {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();

		return array_map( 'intval', $wpdb->get_col( "SELECT `object_id` FROM `{$table_name}` WHERE `object_type` = 'post' ORDER BY `object_id`" ) );
	}

	/**
	 * Inserts rows straight into the queue table, bypassing Queue::queue_object().
	 *
	 * @param array[] $rows Column => value maps.
	 */
	private function insert_raw_jobs( array $rows ): void {
		global $wpdb;

		$table_name = $this->queue->schema->get_table_name();
		foreach ( $rows as $row ) {
			$wpdb->insert( $table_name, $row );
		}
	}

	/**
	 * @global wpdb $wpdb
	 */
	private function get_min_object_id_from_queue() {
		/** @var wpdb $wpdb */
		global $wpdb;

		$table = $this->queue->schema->get_table_name();
		return $wpdb->get_var( "SELECT MIN(job_id) FROM {$table}" );
	}
}
