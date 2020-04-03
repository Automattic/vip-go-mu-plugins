<?php

namespace Automattic\VIP\Search;

class Queue_Test extends \WP_UnitTestCase {
	public function setUp() {
		global $wpdb;

		$wpdb->hide_errors();

		wp_cache_flush();

		require_once __DIR__ . '/../../../../search/search.php';

		$this->es = new \Automattic\VIP\Search\Search();
		$this->es->init();

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
			for( $i = 0; $i < $times; $i++ ) {
				$this->queue->queue_object( $object['id'], $object['type'] );
			}

			// Now it should only exist once
			$results = $wpdb->get_results( 
				$wpdb->prepare( 
					"SELECT * FROM `{$table_name}` WHERE `object_id` = %d AND `object_type` = %s AND `status` = 'queued'",
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
			$this->queue->update_job( $object['id'], array( 'status' => 'running' ) );
			$this->queue->set_last_index_time( $object['id'], $object['type'], $now );
			
			// Requeue the job
			$this->queue->queue_object( $object['id'], $object['type'] );

			// Since it was already running, we should now have a new queued entry with a start_time
			// that is now() + min interval - this is the rate limit

			$row = $wpdb->get_row( 
				$wpdb->prepare( 
					"SELECT `start_time` FROM `{$table_name}` WHERE `object_id` = %d AND `object_type` = %s AND `status` = 'queued'",
					$object['id'],
					$object['type']
				)
			);

			$expected_start_time = date( 'Y-m-d H:i:s', $now + $this->queue->get_index_interval_time( $object['id'], $object['type'] ) );

			$this->assertEquals( $expected_start_time, $row->start_time );
		}
	}
}