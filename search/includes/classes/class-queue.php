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
	const DEADLOCK_TIME = 5 * MINUTE_IN_SECONDS;

	public $schema;

	public const INDEX_COUNT_CACHE_GROUP = 'vip_search';
	public const INDEX_COUNT_CACHE_KEY = 'index_op_count';
	public const INDEX_QUEUEING_ENABLED_KEY = 'index_queueing_enabled';
	public static $max_indexing_op_count = 3000 + 1; // 10 requests per second plus one for clealiness of comparing with Search::index_count_incr
	private const INDEX_COUNT_TTL = 5 * MINUTE_IN_SECONDS; // Period for indexing operations
	private const INDEX_QUEUEING_TTL = 15 * MINUTE_IN_SECONDS; // Keep indexing op queueing for 15 minutes once ratelimiting is triggered

	private const MAX_SYNC_INDEXING_COUNT = 10000;

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
		return true;
	}

	public function setup_hooks() {
		add_action( 'edited_terms', [ $this, 'offload_term_indexing_to_queue' ], 0, 2 );
		add_action( 'pre_delete_term', [ $this, 'offload_indexing_to_queue' ] );

		// For handling indexing failures
		add_action( 'ep_after_bulk_index', [ $this, 'action__ep_after_bulk_index' ], 10, 3 );

		add_filter( 'pre_ep_index_sync_queue', [ $this, 'ratelimit_indexing' ], PHP_INT_MAX, 3 );
	}

	/**
	 * Given an array of queue operation options, determine the correct index version number
	 * 
	 * This returns $options['index_version'] if set, or defaults to the current index version. Extracted
	 * here b/c it is reused all over
	 */
	public function get_index_version_number_from_options( $object_type, $options = array() ) {
		$index_version = isset( $options['index_version'] ) ? $options['index_version'] : null;

		if ( ! is_int( $index_version ) ) {
			$indexable = \ElasticPress\Indexables::factory()->get( $object_type );

			if ( ! $indexable ) {
				return new WP_Error( sprintf( 'Indexable not found for type %s', 'invalid-indexable', $object_type ) );
			}

			$index_version = \Automattic\VIP\Search\Search::instance()->versioning->get_current_version_number( $indexable );
		}

		return $index_version;
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
	public function queue_object( $object_id, $object_type = 'post', $options = array() ) {
		global $wpdb;

		$next_index_time = $this->get_next_index_time( $object_id, $object_type );

		if ( is_int( $next_index_time ) ) {
			$next_index_time = gmdate( 'Y-m-d H:i:s', $next_index_time );
		} else {
			$next_index_time = null;
		}

		$index_version = $this->get_index_version_number_from_options( $object_type, $options );

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

		if ( false !== $result ) {
			$this->record_added_to_queue_stat( 1, $object_type );
		}

		$wpdb->suppress_errors( $original_suppress );

		// TODO handle errors other than duplicate entry
	}

	/**
	 * Queue objects for re-indexing
	 *
	 * If the object is already queued, it will not be queued again
	 *
	 * If the object is being re-indexed too frequently, it will be queued but with a start_time
	 * in the future representing the earliest time the queue processor can index the object
	 *
	 * @param array $object_ids The ids of the objects
	 * @param string $object_type The type of objects
	 */
	public function queue_objects( $object_ids, $object_type = 'post', $options = array() ) {
		if ( ! is_array( $object_ids ) ) {
			return;
		}

		foreach ( $object_ids as $object_id ) {
			$this->queue_object( $object_id, $object_type, $options );
		}
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
	public function get_next_index_time( $object_id, $object_type, $options = array() ) {
		$last_index_time = $this->get_last_index_time( $object_id, $object_type, $options );

		$next_index_time = null;

		if ( is_int( $last_index_time ) && $last_index_time ) {
			// Next index time is last index time + interval
			$next_index_time = $last_index_time + $this->get_index_interval_time( $object_id, $object_type, $options );
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
	public function get_last_index_time( $object_id, $object_type, $options = array() ) {
		$cache_key = $this->get_last_index_time_cache_key( $object_id, $object_type, $options );

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
	public function set_last_index_time( $object_id, $object_type, $time, $options = array() ) {
		$cache_key = $this->get_last_index_time_cache_key( $object_id, $object_type, $options );

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
	public function get_last_index_time_cache_key( $object_id, $object_type, $options = array() ) {
		$index_version = $this->get_index_version_number_from_options( $object_type, $options );
	
		return sprintf( '%s-%d-v%d', $object_type, $object_id, $index_version );
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

	public function count_jobs( $status, $object_type = 'post', $options = array() ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$query = null;

		// TODO should we support $index_version here? Is there a better way to structure these conditionals?
		if ( 'all' === strtolower( $status ) ) {
			if ( 'all' === strtolower( $object_type ) ) {
				$query = "SELECT COUNT(*) FROM {$table_name} WHERE 1"; // Cannot prepare table name. @codingStandardsIgnoreLine
			} else {
				$query = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE `object_type` = %s", // Cannot prepare table name. @codingStandardsIgnoreLine
					$object_type
				);
			}
		}

		// If query has not already been set, it's a "normal" query. This is done after b/c the index version lookup will fail 
		// when $object_type is equal to 'all' since this is not a valid Indexable
		if ( ! $query ) {
			$index_version = $this->get_index_version_number_from_options( $object_type, $options );

			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE `status` = %s AND `object_type` = %s AND `index_version` = %d", // Cannot prepare table name. @codingStandardsIgnoreLine
				$status,
				$object_type,
				$index_version
			);
		}

		$job_count = $wpdb->get_var( $query ); // Query may change depending on status/object type @codingStandardsIgnoreLine

		return intval( $job_count );
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

	public function get_next_job_for_object( $object_id, $object_type, $options = array() ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$index_version = $this->get_index_version_number_from_options( $object_type, $options );

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE `object_id` = %d AND `object_type` = %s AND `index_version` = %d AND `status` = 'queued' LIMIT 1", // Cannot prepare table name. @codingStandardsIgnoreLine
				$object_id,
				$object_type,
				$index_version
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

		// Set them as scheduled
		$job_ids = wp_list_pluck( $jobs, 'job_id' );

		$scheduled_time = gmdate( 'Y-m-d H:i:s' );

		$this->update_jobs( $job_ids, array(
			'status' => 'scheduled',
			'scheduled_time' => $scheduled_time,
		) );

		// Set right status on the already queried jobs objects
		foreach ( $jobs as &$job ) {
			$job->status = 'scheduled';
			$job->scheduled_time = $scheduled_time;

			// Set the last index time for rate limiting. Technically the object isn't yet re-indexed, but 
			// this is close enough for our purpose and prevents repeat jobs from being queued for immediate processing
			// between the time we check out the job and the cron processor actually runs
			$this->set_last_index_time( $job->object_id, $job->object_type, time() );
		}

		return $jobs;
	}

	/**
	 * Find any jobs that are considered "deadlocked"
	 * 
	 * A deadlocked job is one that has been scheduled for processing, but has
	 * not completed within the defined time period
	 */
	public function get_deadlocked_jobs( $count = 250 ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		// If job was scheduled before this time, it is considered deadlocked
		$deadlocked_time = time() - self::DEADLOCK_TIME;

		$jobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE `status` = 'scheduled' AND `scheduled_time` <= %s LIMIT %d", // Cannot prepare table name. @codingStandardsIgnoreLine
			gmdate( 'Y-m-d H:i:s', $deadlocked_time ),
			$count
		) );
		
		return $jobs;
	}

	/**
	 * Find and release deadlocked jobs
	 */
	public function free_deadlocked_jobs() {
		// Run this several times, to release potentially many jobs in reasonable batches
		$batches = 5;

		for ( $i = 0; $i < $batches; $i++ ) {
			$deadlocked_jobs = $this->get_deadlocked_jobs( 500 );

			// If none found, we can stop the loop
			if ( empty( $deadlocked_jobs ) ) {
				break;
			}

			$deadlocked_job_ids = wp_list_pluck( $deadlocked_jobs, 'job_id' );

			$this->update_jobs( $deadlocked_job_ids, array(
				'status' => 'queued',
				'scheduled_time' => null,
			) );
		}
	}

	public function process_jobs( $jobs ) {
		// Set them as running
		$job_ids = wp_list_pluck( $jobs, 'job_id' );

		$this->update_jobs( $job_ids, array( 'status' => 'running' ) );

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

			// Increment first to prevent overrunning ratelimiting
			self::index_count_incr( count( $ids ) );

			$indexable->bulk_index( $ids );

			// TODO handle errors
	
			// Mark them as done in queue
			$this->delete_jobs( $jobs );

			$this->record_processed_from_queue_stat( count( $ids ), $indexable );

			$this->record_queue_count_stat( $indexable );
		}
	}

	public function record_processed_from_queue_stat( $count, $indexable ) {
		if ( ! $indexable ) {
			return;
		}

		if ( ! is_int( $count ) ) {
			$count = intval( $count );
		}

		$statsd_mode = 'processed_from_queue';

		// Pull index name using the indexable slug from the EP indexable singleton
		$statsd_index_name = $indexable->get_index_name();

		// For url parsing operations
		$es = \Automattic\VIP\Search\Search::instance();

		$url = $es->get_current_host();
		$per_site_stat = $es->get_statsd_prefix( $url, $statsd_mode, FILES_CLIENT_SITE_ID, $statsd_index_name );

		$statsd = new \Automattic\VIP\StatsD();
		$statsd->update_stats( $per_site_stat, $count, 1, 'c' );
	}

	public function record_queue_count_stat( $indexable ) {
		if ( ! $indexable ) {
			return;
		}

		$statsd_mode = 'queue_size';

		// Pull index name using the indexable slug from the EP indexable singleton
		$statsd_index_name = $indexable->get_index_name();

		// For url parsing operations
		$es = \Automattic\VIP\Search\Search::instance();

		$url = $es->get_current_host();
		$per_site_stat = $es->get_statsd_prefix( $url, $statsd_mode, FILES_CLIENT_SITE_ID, $statsd_index_name );

		$count = $this->count_jobs( 'all', $indexable->slug );

		$statsd = new \Automattic\VIP\StatsD();
		$statsd->gauge( $per_site_stat, $count );
	}

	public function record_added_to_queue_stat( $count, $object_type ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $object_type );

		if ( ! $indexable ) {
			return;
		}

		if ( ! is_int( $count ) ) {
			$count = intval( $count );
		}

		$statsd_mode = 'added_to_queue';

		// Pull index name using the indexable slug from the EP indexable singleton
		$statsd_index_name = $indexable->get_index_name();

		// For url parsing operations
		$es = \Automattic\VIP\Search\Search::instance();

		$url = $es->get_current_host();
		$per_site_stat = $es->get_statsd_prefix( $url, $statsd_mode, FILES_CLIENT_SITE_ID, $statsd_index_name );

		$statsd = new \Automattic\VIP\StatsD();
		$statsd->increment( $per_site_stat, $count );
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

	/**
	 * Offload term indexing to the queue
	 */
	public function offload_term_indexing_to_queue( $term_id, $taxonomy ) {
		$term = \get_term( $term_id, $taxonomy );

		if ( is_wp_error( $term ) || ! is_object( $term ) ) {
			return;
		}

		// If the number of affected posts is low enough, process them now rather than send them to cron
		if ( $term->count <= self::MAX_SYNC_INDEXING_COUNT ) {
			$this->offload_indexing_to_queue();
			return;
		}

		add_filter( 'ep_skip_action_edited_term', '__return_true' ); // Disable ElasticPress execution on term edit
		$this->cron->schedule_queue_posts_for_term_taxonomy_id( $term->term_taxonomy_id );
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

		// If indexing operations are NOT currently ratelimited, queue up a cron event to process these immediately.
		if ( ! self::is_indexing_ratelimited() ) {
			$this->cron->schedule_batch_job();
		}
		
		// Empty out the queue now that we've queued those items up
		$sync_manager->sync_queue = [];

		return true;
	}

	/**
	 * Hook after bulk indexing looking for errors. If there's an error with indexing some of the posts and the queue is enabled, 
	 * queue all of the posts for indexing.
	 *
	 * @param {array} $document_ids IDs of the documents that were to be indexed
	 * @param {string} $slug Indexable slug
	 * @param {array|boolean} $return Elasticsearch response. False on error.
	 * @return {bool} Whether anything was done
	 */
	public function action__ep_after_bulk_index( $document_ids, $slug, $return ) {
		if ( false === $this->is_enabled() || ! is_array( $document_ids ) || 'post' !== $slug || false !== $return ) {
			return false;
		}

		$this->queue_objects( $document_ids );

		return true;
	}

	/**
	 * Hook pre_ep_index_sync_queue for indexing operation ratelimiting.
	 * If ratelimited, bail and offload indexing to queue.
	 *
	 * @param {bool} $bail Current value of bail
	 * @param {object} $sync_manager Instance of Sync_Manager
	 * @param {string} $indexable_slug Indexable slug
	 * @return {bool} Whether to bail on indexing or not
	 */
	public function ratelimit_indexing( $bail, $sync_manager, $indexable_slug ) {
		// Only posts supported right now
		if ( 'post' !== $indexable_slug ) {
			return $bail;
		}

		if ( empty( $sync_manager->sync_queue ) ) {
			return $bail;
		}

		// Increment first to prevent overrunning ratelimiting
		$increment = count( $sync_manager->sync_queue );
		$index_count_in_period = self::index_count_incr( $increment );

		// If indexing operation ratelimiting is hit, queue index operations
		if ( $index_count_in_period > self::$max_indexing_op_count || self::is_indexing_ratelimited() ) {
			$this->record_ratelimited_stat( $increment, $indexable_slug );

			// Offload indexing to async queue
			$this->intercept_ep_sync_manager_indexing( $bail, $sync_manager, $indexable_slug );

			if ( ! self::is_indexing_ratelimited() ) {
				self::turn_on_index_ratelimiting();
			}
		}

		// Honor filters that want to bail on indexing while also honoring ratelimiting
		if ( true === $bail || true === self::is_indexing_ratelimited() ) {
			return true;
		} else {
			return false;
		}
	}

	public function record_ratelimited_stat( $count, $indexable_slug ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		if ( ! $indexable ) {
			return;
		}

		// Since we're ratelimting indexing, it seems safe to define this
		$statsd_mode = 'index_ratelimited';

		// Pull index name using the indexable slug from the EP indexable singleton
		$statsd_index_name = $indexable->get_index_name();

		// For url parsing operations
		$es = \Automattic\VIP\Search\Search::instance();

		$url = $es->get_current_host();
		$stat = $es->get_statsd_prefix( $url, $statsd_mode );
		$per_site_stat = $es->get_statsd_prefix( $url, $statsd_mode, FILES_CLIENT_SITE_ID, $statsd_index_name );

		$statsd = new \Automattic\VIP\StatsD();

		$statsd->increment( $stat, $count );
		$statsd->increment( $per_site_stat, $count );
	}

	/**
	 * Check whether indexing is currently ratelimited
	 *
	 * @return {bool} Whether indexing is curretly ratelimited
	 */
	public static function is_indexing_ratelimited() {
		return false !== wp_cache_get( self::INDEX_QUEUEING_ENABLED_KEY, self::INDEX_COUNT_CACHE_GROUP );
	}

	/**
	 *  Turn on ratelimit indexing
	 *
	 * @return {bool} True on success, false on failure
	 */
	public static function turn_on_index_ratelimiting() {
		return wp_cache_set( self::INDEX_QUEUEING_ENABLED_KEY, true, self::INDEX_COUNT_CACHE_GROUP, self::INDEX_QUEUEING_TTL );
	}

	/**
	 * Get the current average queue wait time
	 *
	 * @return {int} The current average wait time in seconds.
	 */
	public function get_average_queue_wait_time() {
		global $wpdb;

		// If run without having the queue enabled, queue wait times are 0
		if ( ! $this->is_enabled() ) {
			return 0;
		}

		// If schema is null, init likely not run yet
		// Happens when cron is scheduled/run via wp commands
		if ( is_null( $this->schema ) ) {
			$this->init();
		}

		$table_name = $this->schema->get_table_name();

		$average_wait_time = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT FLOOR( AVG( TIMESTAMPDIFF( SECOND, queued_time, NOW() ) ) ) AS average_wait_time FROM $table_name WHERE 1" // Cannot prepare table name. @codingStandardsIgnoreLine
			)
		);

		// Null value will usually mean empty table 
		if ( is_null( $average_wait_time ) || ! is_numeric( $average_wait_time ) ) {
			return 0;
		}

		return intval( $average_wait_time );
	}

	/*
	 * Increment the number of indexing operations that have been passed through VIP Search
	 */
	private static function index_count_incr( $increment = 1 ) {
		if ( false === wp_cache_get( self::INDEX_COUNT_CACHE_KEY, self::INDEX_COUNT_CACHE_GROUP ) ) {
			wp_cache_set( self::INDEX_COUNT_CACHE_KEY, 0, self::INDEX_COUNT_CACHE_GROUP, self::INDEX_COUNT_TTL );
		}

		return wp_cache_incr( self::INDEX_COUNT_CACHE_KEY, $increment, self::INDEX_COUNT_CACHE_GROUP );
	}
}
