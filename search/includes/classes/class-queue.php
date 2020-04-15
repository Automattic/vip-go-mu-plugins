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

	const MAX_BATCH_SIZE = 1000;

	public $schema;

	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		require_once( __DIR__ . '/queue/class-schema.php' );
		require_once( __DIR__ . '/queue/class-cron.php' );

		$this->schema = new Queue\Schema();
		$this->schema->init();

		$this->cron = new Queue\Cron();
		$this->cron->init();
		$this->cron->queue = $this;

		$this->setup_hooks();
	}

	public function is_enabled() {
		$enabled_by_constant = defined( 'VIP_SEARCH_ENABLE_ASYNC_INDEXING' ) && true === VIP_SEARCH_ENABLE_ASYNC_INDEXING;

		$option_value = get_option( 'vip_enable_search_indexing_queue' );
		$is_enabled_by_option = in_array( $option_value, array( true, 'true', 'yes', 1, '1' ), true );

		return $enabled_by_constant || $is_enabled_by_option;
	}

	public function setup_hooks() {
		add_action( 'edit_terms', [ $this, 'offload_indexing_to_queue' ] );
		add_action( 'pre_delete_term', [ $this, 'offload_indexing_to_queue' ] );
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
			$next_index_time = gmdate( 'Y-m-d H:i:s', $next_index_time );
		} else {
			$next_index_time = null;
		}

		$table_name = $this->schema->get_table_name();

		// Have to escape this separately so we can insert NULL if no start time specified
		$start_time_escaped = $next_index_time ? $wpdb->prepare( '%s', array( $next_index_time ) ) : 'NULL';

		$original_suppress = $wpdb->suppress_errors;

		// Suppress errors because duplicate key errors are expected, as we use the UNIQUE index
		// to de-duplicate queued jobs without first querying to see if the object is queued
		$wpdb->suppress_errors( true );

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $table_name ( `object_id`, `object_type`, `start_time`, `status` ) VALUES ( %d, %s, {$start_time_escaped}, %s )", // Cannot prepare table name. @codingStandardsIgnoreLine
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

	public function update_job( $job_id, $data ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->update( $table_name, $data, array( 'job_id' => $job_id ) );
	}

	public function update_jobs( $job_ids, $data ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$escaped_fields = [];

		foreach ( $data as $column => $value ) {
			$escaped_fields[] = $wpdb->prepare( "{$column} = %s", $value ); // Cannot prepare column name. @codingStandardsIgnoreLine
		}

		$escaped_fields = implode( ', ', $escaped_fields );

		$escaped_ids = implode( ', ', array_map( 'intval', $job_ids ) );

		$sql = "UPDATE {$table_name} SET {$escaped_fields} WHERE `job_id` IN ( {$escaped_ids} )"; // Cannot prepare table name. @codingStandardsIgnoreLine

		return $wpdb->get_results( $sql ); // Already escaped. @codingStandardsIgnoreLine
	}

	public function delete_jobs( $jobs ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$ids = wp_list_pluck( $jobs, 'job_id' );

		$escaped_ids = implode( ', ', array_map( 'intval', $ids ) );

		$sql = "DELETE FROM {$table_name} WHERE `job_id` IN ( {$escaped_ids} )"; // Cannot prepare table name. @codingStandardsIgnoreLine

		return $wpdb->get_results( $sql ); // Already escaped. @codingStandardsIgnoreLine
	}

	public function empty_queue() {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->query( "TRUNCATE TABLE {$table_name}" ); // Cannot prepare table name. @codingStandardsIgnoreLine
	}

	public function count_jobs( $status, $object_type = 'post' ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE `status` = %s AND `object_type` = %s", // Cannot prepare table name. @codingStandardsIgnoreLine
				$status,
				$object_type
			)
		);
	}

	public function count_jobs_due_now( $object_type = 'post' ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE `status` = 'queued' AND `object_type` = %s AND ( `start_time` <= NOW() OR `start_time` IS NULL )", // Cannot prepare table name. @codingStandardsIgnoreLine
				$object_type
			)
		);
	}

	public function get_next_job_for_object( $object_id, $object_type ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE `object_id` = %d AND `object_type` = %s AND `status` = 'queued' LIMIT 1", // Cannot prepare table name. @codingStandardsIgnoreLine
				$object_id,
				$object_type
			)
		);

		return $job;
	}

	public function get_jobs( $job_ids ) {
		global $wpdb;

		if ( empty( $job_ids ) ) {
			return array();
		}

		$table_name = $this->schema->get_table_name();

		$escaped_ids = array_map( 'intval', $job_ids );
		$escaped_ids = implode( ', ', $job_ids );

		$jobs = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE `job_id` IN ( {$escaped_ids} )" ); // Cannot prepare table name, ids already escaped. @codingStandardsIgnoreLine
		
		return $jobs;
	}

	/**
	 * Grab $count jobs that are due now and mark them as running
	 * 
	 * @param {int} $count How many jobs to check out
	 * @return array Array of jobs (db rows) that were checked out
	 */
	public function checkout_jobs( $count = 250 ) {
		// Enforce a reasonable limit on batch size
		$count = min( $count, self::MAX_BATCH_SIZE );
		$count = max( $count, 1 );

		global $wpdb;

		$table_name = $this->schema->get_table_name();

		// TODO transaction
		// TODO only find objects that aren't already running

		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE ( `start_time` <= NOW() OR `start_time` IS NULL ) AND `status` = 'queued' LIMIT %d", // Cannot prepare table name. @codingStandardsIgnoreLine
				$count
			)
		);

		if ( empty( $jobs ) ) {
			return array();
		}

		// Set them as running
		$job_ids = wp_list_pluck( $jobs, 'job_id' );

		$this->update_jobs( $job_ids, array( 'status' => 'running' ) );

		// Set right status on the already queried jobs objects
		foreach ( $jobs as &$job ) {
			$job->status = 'running';
		}

		return $jobs;
	}

	public function process_jobs( $jobs ) {
		$indexables = \ElasticPress\Indexables::factory();
	
		// Organize by object type
		$jobs_by_type = array();

		foreach ( $jobs as $job ) {
			if ( ! isset( $jobs_by_type[ $job->object_type ] ) ) {
				$jobs_by_type[ $job->object_type ] = array();
			}

			$jobs_by_type[ $job->object_type ][] = $job;
		}
		
		// Batch process each type using the indexable
		foreach ( $jobs_by_type as $type => $jobs ) {
			$indexable = $indexables->get( $type );

			$ids = wp_list_pluck( $jobs, 'object_id' );

			$indexable->bulk_index( $ids );

			// TODO handle errors

			// Mark all as being indexed just now, for rate limiting
			foreach ( $jobs as $job ) {
				$this->set_last_index_time( $job->object_id, $job->object_type, time() );
			}
	
			// Mark them as done in queue
			$this->delete_jobs( $jobs );
		}
	}
	
	/**
	 * If called during a request, any queued indexing will be instead sent to
	 * the async queue
	 */
	public function offload_indexing_to_queue() {
		if ( ! has_filter( 'pre_ep_index_sync_queue', [ $this, 'intercept_ep_sync_manager_indexing' ] ) ) {
			add_filter( 'pre_ep_index_sync_queue', [ $this, 'intercept_ep_sync_manager_indexing' ], 10, 3 );
		}
	}

	public function intercept_ep_sync_manager_indexing( $bail, $sync_manager, $indexable_slug ) {
		// Only posts supported right now
		if ( 'post' !== $indexable_slug ) {
			return $bail;
		}

		if ( empty( $sync_manager->sync_queue ) ) {
			return $bail;
		}
	
		// TODO add function to bulk insert

		foreach ( array_keys( $sync_manager->sync_queue ) as $object_id ) {
			$this->queue_object( $object_id, $indexable_slug );
		}

		// Queue up a cron event to process these immediately
		$this->cron->schedule_batch_job();

		// Empty out the queue now that we've queued those items up
		$sync_manager->sync_queue = [];

		return true;
	}
}
