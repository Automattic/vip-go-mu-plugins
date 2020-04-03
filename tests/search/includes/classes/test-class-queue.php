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
}