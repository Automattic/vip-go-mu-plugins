<?php

namespace Automattic\VIP\Search;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;

use \WP_Query as WP_Query;
use \WP_User_Query as WP_User_Query;
use \WP_Error as WP_Error;

class Queue {
	const CACHE_GROUP = 'vip-search-index-queue';
	const OBJECT_LAST_INDEX_TIMESTAMP_TTL = 120; // Must be at least longer than the rate limit intervals

	public $schema;

	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		require_once( __DIR__ . '/Queue/class-schema.php' );

		$this->schema = new Queue\Schema();
		$this->schema->init();
	}

	public function is_enabled() {
		$enabled_by_constant = defined( 'VIP_SEARCH_ENABLE_ASYNC_INDEXING' ) && true === VIP_SEARCH_ENABLE_ASYNC_INDEXING;

		return $enabled_by_constant;
	}

	/**
	 * Queue an object for re-indexing
	 * 
	 * If the object is already queued, it will not be queued again
	 * 
	 * If the object is being re-indexed too frequently, it will be queued but with a start_time
	 * in the future representing the earliest time the queue processor can index the object
	 * 
	 * @param int $object_id The id of the object
	 * @param string $object_type The type of object
	 */
	public function queue_object( $object_id, $object_type = 'post' ) {
		global $wpdb;

		$next_index_time = $this->get_next_index_time( $object_id, $object_type );

		if ( is_int( $next_index_time ) ) {
			$next_index_time = date( 'Y-m-d H:i:s', $next_index_time );
		} else {
			$next_index_time = null;
		}

		$table_name = $this->schema->get_table_name();

		// Have to escape this separately so we can insert NULL if no start time specified
		$start_time_escaped = $next_index_time ? $wpdb->prepare( '%s', array( $next_index_time ) ) : 'NULL';

		$original_suppress = $wpdb->suppress_errors;

		$wpdb->suppress_errors( true );

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $table_name ( `object_id`, `object_type`, `start_time`, `status` ) VALUES ( %d, %s, {$start_time_escaped}, %s )",
				$object_id,
				$object_type,
				'queued'
			)
		);

		$wpdb->suppress_errors( $original_suppress );

		// TODO handle errors other than duplicate entry
	}

	/**
	 * Retrieve the unix timestamp representing the soonest time that a given object can be indexed
	 * 
	 * This provides rate limiting by checking the cached timestamp of the last successful indexing operation
	 * and applying the defined minimum interval between successive indexing jobs
	 * 
	 * @param int $object_id The id of the object
	 * @param string $object_type The type of object
	 * 
	 * @return int The soonest unix timestamp when the object can be indexed again
	 */
	public function get_next_index_time( $object_id, $object_type ) {
		$last_index_time = $this->get_last_index_time( $object_id, $object_type );

		$next_index_time = null;

		if ( is_int( $last_index_time ) && $last_index_time ) {
			// Next index time is last index time + interval
			$next_index_time = $last_index_time + $this->get_index_interval_time( $object_id, $object_type );
		}

		return $next_index_time;
	}

	/**
	 * Retrieve the unix timestamp representing the most recent time an object was indexed
	 * 
	 * @param int $object_id The id of the object
	 * @param string $object_type The type of object
	 * 
	 * @return int The unix timestamp when the object was last indexed
	 */
	public function get_last_index_time( $object_id, $object_type ) {
		$cache_key = $this->get_last_index_time_cache_key( $object_id, $object_type );

		$last_index_time = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( ! is_int( $last_index_time ) ) {
			$last_index_time = null;
		}

		return $last_index_time;
	}

	/**
	 * Set the unix timestamp when the object was last indexed
	 * 
	 * @param int $object_id The id of the object
	 * @param string $object_type The type of object
	 * @param int $time Unix timestamp when the object was last indexed
	 */
	public function set_last_index_time( $object_id, $object_type, $time ) {
		$cache_key = $this->get_last_index_time_cache_key( $object_id, $object_type );

		wp_cache_set( $cache_key, $time, self::CACHE_GROUP, self::OBJECT_LAST_INDEX_TIMESTAMP_TTL );
	}

	/**
	 * Get the cache key used to track an object's last indexed timestamp
	 *
	 * @param int $object_id The id of the object
	 * @param string $object_type The type of object
	 * 
	 * @return string The cache key to use for the object's last indexed timestamp
	 */
	public function get_last_index_time_cache_key( $object_id, $object_type ) {
		return sprintf( '%s-%d', $object_type, $object_id );
	}

	/**
	 * Get the interval between successive index operations on a given object
	 *
	 * @param int $object_id The id of the object
	 * @param string $object_type The type of object
	 * 
	 * @return int Minimum number of seconds between re-indexes
	 */
	public function get_index_interval_time( $object_id, $object_type ) {
		return 60;
	}

	public function update_job( $id, $data ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->update( $table_name, $data, array( 'id' => $id ) );
	}

	public function update_jobs( $jobs, $data ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$escaped_fields = [];

		foreach( $data as $column => $value ) {
			$escaped_fields[] = $wpdb->prepare( "{$column} = %s", $value );
		}

		$escaped_fields = implode( ', ', $escaped_fields );

		$ids = wp_list_pluck( $jobs, 'id' );

		$escaped_ids = implode( ', ', array_map( 'intval', $ids ) );

		$sql = "UPDATE {$table_name} SET {$escaped_fields} WHERE id IN ( {$escaped_ids} )";

		return $wpdb->get_results( $sql );
	}

	public function delete_jobs( $jobs ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$ids = wp_list_pluck( $jobs, 'id' );

		$escaped_ids = implode( ', ', array_map( 'intval', $ids ) );

		$sql = "DELETE FROM {$table_name} WHERE id IN ( {$escaped_ids} )";

		return $wpdb->get_results( $sql );
	}

	public function empty_queue() {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->query( "TRUNCATE TABLE {$table_name}" );
	}

	public function count_jobs( $status, $object_type = 'post' ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE `status` = %s AND `object_type` = %s",
				$status,
				$object_type
			)
		);
	}

	public function count_jobs_due_now( $status, $object_type = 'post' ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE `status` = %s AND `object_type` = %s AND ( `start_time` <= NOW() OR `start_time` IS NULL )",
				$status,
				$object_type
			)
		);
	}

	public function get_next_job_for_object( $object_id, $object_type ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE `object_id` = %d AND `object_type` = %s AND `status` = 'queued' LIMIT 1",
				$object_id,
				$object_type
			)
		);

		return $job;
	}

	public function get_batch_jobs( $count = 250 ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE ( `start_time` <= NOW() OR `start_time` IS NULL ) AND `status` = 'queued' LIMIT %d",
				$count
			)
		);
	}
}
