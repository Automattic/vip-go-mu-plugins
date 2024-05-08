<?php

namespace Automattic\VIP\Search;

use ElasticPress\Indexables;
use WP_Error;

class Queue {
	const CACHE_GROUP                     = 'vip-search-index-queue';
	const OBJECT_LAST_INDEX_TIMESTAMP_TTL = 120; // Must be at least longer than the rate limit intervals

	const MAX_BATCH_SIZE = 500;
	const DEADLOCK_TIME  = 5 * MINUTE_IN_SECONDS;

	/** @var Queue\Schema */
	public $schema;
	/** @var Indexables */
	public $indexables;
	/** @var \Automattic\VIP\Logstash\Logger */
	public $logger;
	/** @var Queue\Cron */
	public $cron;

	public const INDEX_COUNT_CACHE_GROUP            = 'vip_search';
	public const INDEX_COUNT_CACHE_KEY              = 'index_op_count';
	public const INDEX_RATE_LIMITED_START_CACHE_KEY = 'index_rate_limited_start';
	public const INDEX_QUEUEING_ENABLED_KEY         = 'index_queueing_enabled';
	public const INDEX_DEFAULT_PRIORITY             = 5;
	public static $stat_sampling_drop_value         = 5; // Value to compare >= against rand( 1, 10 ). 5 should result in roughly half being true.

	public static $max_indexing_op_count;
	private const DEFAULT_MAX_INDEXING_OP_COUNT           = 6000 + 1;
	private const LOWER_BOUND_MAX_INDEXING_OPS_PER_SECOND = 10;
	private const UPPER_BOUND_MAX_INDEXING_OPS_PER_SECOND = 250;

	private const INDEX_RATE_LIMITED_ALERT_LIMIT       = 7200; // 2 hours in seconds
	private const INDEX_RATE_LIMITING_ALERT_SLACK_CHAT = '#vip-go-es-alerts';
	private const INDEX_RATE_LIMITING_ALERT_LEVEL      = 2; // Level 2 = 'alert'

	private static $index_count_ttl;
	private const DEFAULT_INDEX_COUNT_TTL     = 5 * \MINUTE_IN_SECONDS;
	private const LOWER_BOUND_INDEX_COUNT_TTL = 1 * \MINUTE_IN_SECONDS;
	private const UPPER_BOUND_INDEX_COUNT_TTL = 2 * \HOUR_IN_SECONDS;

	private static $index_queueing_ttl;
	private const DEFAULT_INDEX_QUEUEING_TTL     = 5 * \MINUTE_IN_SECONDS;
	private const LOWER_BOUND_INDEX_QUEUEING_TTL = 1 * \MINUTE_IN_SECONDS;
	private const UPPER_BOUND_INDEX_QUEUEING_TTL = 20 * \MINUTE_IN_SECONDS;

	private static $max_sync_indexing_count;
	private const DEFAULT_MAX_SYNC_INDEXING_COUNT     = 10000;
	private const LOWER_BOUND_MAX_SYNC_INDEXING_COUNT = 2500;
	private const UPPER_BOUND_MAX_SYNC_INDEXING_COUNT = 25000;

	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->apply_settings();

		require_once __DIR__ . '/queue/class-schema.php';
		require_once __DIR__ . '/queue/class-cron.php';

		$this->schema = new Queue\Schema();
		$this->schema->init();

		$this->cron = new Queue\Cron();
		$this->cron->init();
		$this->cron->queue = $this;

		$this->indexables = Indexables::factory();

		// Logger - can be set explicitly for mocking purposes
		if ( ! $this->logger ) {
			$this->logger = new \Automattic\VIP\Logstash\Logger();
		}

		$this->setup_hooks();
	}

	public function is_enabled() {
		return true;
	}

	public function apply_settings() {
		/**
		 * The period with which the Elasticsearch indexing rate limiting threshold is set.
		 *
		 * A set amount of indexing requests are allowed per period. After rate limiting is triggered, it occurs for a set amount of time and bulk indexing operations
		 * are queued for asynchronous processing over time.
		 *
		 * @hook vip_search_index_count_period
		 * @param int $period The period, in seconds, for Elasticsearch indexing rate limiting checks.
		 */
		self::$index_count_ttl = apply_filters( 'vip_search_index_count_period', self::DEFAULT_INDEX_COUNT_TTL );

		if ( ! is_numeric( self::$index_count_ttl ) ) {
			_doing_it_wrong(
				'add_filter',
				'vip_search_index_count_period should be an integer.',
				'5.5.3'
			);

			self::$index_count_ttl = self::DEFAULT_INDEX_COUNT_TTL;
		}

		self::$index_count_ttl = intval( self::$index_count_ttl );

		if ( self::$index_count_ttl < self::LOWER_BOUND_INDEX_COUNT_TTL ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_index_count_period should not be set below %d seconds.', self::LOWER_BOUND_INDEX_COUNT_TTL ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$index_count_ttl = self::LOWER_BOUND_INDEX_COUNT_TTL;
		}

		if ( self::$index_count_ttl > self::UPPER_BOUND_INDEX_COUNT_TTL ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_index_count_period should not be set above %d seconds.', self::UPPER_BOUND_INDEX_COUNT_TTL ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$index_count_ttl = self::UPPER_BOUND_INDEX_COUNT_TTL;
		}

		/**
		 * The number of indexing operations allowed per period before Elasticsearch rate limiting takes effect.
		 *
		 * Ratelimiting works by being triggered and then persisting for a set period. During this period, all bulk indexing operations are added to a queue and are
		 * processed asynchronously.
		 *
		 * @hook vip_search_max_indexing_op_count
		 * @param int $ratelimit_threshold The threshold to trigger rate limiting for the period.
		 */
		self::$max_indexing_op_count = apply_filters( 'vip_search_max_indexing_op_count', self::DEFAULT_MAX_INDEXING_OP_COUNT );

		if ( ! is_numeric( self::$max_indexing_op_count ) ) {
			_doing_it_wrong(
				'add_filter',
				'vip_search_max_indexing_op_count should be an integer.',
				'5.5.3'
			);

			self::$max_indexing_op_count = self::DEFAULT_MAX_INDEXING_OP_COUNT;
		}

		self::$max_indexing_op_count = intval( self::$max_indexing_op_count );

		$lower_bound_max_indexing_op_count = ( self::$index_count_ttl * self::LOWER_BOUND_MAX_INDEXING_OPS_PER_SECOND ) + 1;

		if ( self::$max_indexing_op_count < $lower_bound_max_indexing_op_count ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_max_indexing_op_count should not be below %d queries per second.', self::LOWER_BOUND_MAX_INDEXING_OPS_PER_SECOND ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$max_indexing_op_count = $lower_bound_max_indexing_op_count;
		}

		$upper_bound_max_indexing_op_count = ( self::$index_count_ttl * self::UPPER_BOUND_MAX_INDEXING_OPS_PER_SECOND ) + 1;

		if ( self::$max_indexing_op_count > $upper_bound_max_indexing_op_count ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_max_indexing_op_count should not exceed %d queries per second.', self::UPPER_BOUND_MAX_INDEXING_OPS_PER_SECOND ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$max_indexing_op_count = $upper_bound_max_indexing_op_count;
		}

		/**
		 * The length of time Elasticsearch indexing rate limiting will be active once triggered.
		 *
		 * During this period, all bulk indexing operations will be queued for asynchronous processing over time.
		 *
		 * @hook vip_search_index_ratelimiting_duration
		 * @param int $duration The duration that Elasticsearch indexing rate limiting will be in effect.
		 */
		self::$index_queueing_ttl = apply_filters( 'vip_search_index_ratelimiting_duration', self::DEFAULT_INDEX_QUEUEING_TTL );

		if ( ! is_numeric( self::$index_queueing_ttl ) ) {
			_doing_it_wrong(
				'add_filter',
				'vip_search_index_ratelimiting_duration should be an integer.',
				'5.5.3'
			);

			self::$index_queueing_ttl = self::DEFAULT_INDEX_QUEUEING_TTL;
		}

		self::$index_queueing_ttl = intval( self::$index_queueing_ttl );

		if ( self::$index_queueing_ttl < self::LOWER_BOUND_INDEX_QUEUEING_TTL ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_index_ratelimiting_duration should not be set below %d seconds.', self::LOWER_BOUND_INDEX_QUEUEING_TTL ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$index_queueing_ttl = self::LOWER_BOUND_INDEX_QUEUEING_TTL;
		}

		if ( self::$index_queueing_ttl > self::UPPER_BOUND_INDEX_QUEUEING_TTL ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_index_ratelimiting_duration should not be set above %d seconds.', self::UPPER_BOUND_INDEX_QUEUEING_TTL ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$index_queueing_ttl = self::UPPER_BOUND_INDEX_QUEUEING_TTL;
		}

		/**
		 * The maximum number of objects that can be synchronously indexing in Search.
		 *
		 * Any indexing operations with more objects than this will have the operation queued for asynchronous processing to avoid overloading the cluster and
		 * to prevent hanging / timeouts in the UI and related bad UX.
		 *
		 * @hook vip_search_max_indexing_count
		 * @param int $max_count The maximum number of objects that can be synchronously indexed.
		 */
		self::$max_sync_indexing_count = apply_filters( 'vip_search_max_indexing_count', self::DEFAULT_MAX_SYNC_INDEXING_COUNT );

		if ( ! is_numeric( self::$max_sync_indexing_count ) ) {
			_doing_it_wrong(
				'add_filter',
				'vip_search_max_indexing_count should be an integer.',
				'5.5.3'
			);

			self::$max_sync_indexing_count = self::DEFAULT_MAX_SYNC_INDEXING_COUNT;
		}

		self::$max_sync_indexing_count = intval( self::$max_sync_indexing_count );

		if ( self::$max_sync_indexing_count < self::LOWER_BOUND_MAX_SYNC_INDEXING_COUNT ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_max_sync_indexing_count should not be below %d.', self::LOWER_BOUND_MAX_SYNC_INDEXING_COUNT ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$max_sync_indexing_count = self::LOWER_BOUND_MAX_SYNC_INDEXING_COUNT;
		}

		if ( self::$max_sync_indexing_count > self::UPPER_BOUND_MAX_SYNC_INDEXING_COUNT ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_max_sync_indexing_count should not be above %d.', self::UPPER_BOUND_MAX_SYNC_INDEXING_COUNT ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$max_sync_indexing_count = self::UPPER_BOUND_MAX_SYNC_INDEXING_COUNT;
		}
	}

	public function setup_hooks() {
		// We should make sure to apply the settings again after the customer code have been loaded to ensure the consistency.
		add_action( 'after_setup_theme', array( $this, 'apply_settings' ), PHP_INT_MAX );

		add_action( 'saved_term', [ $this, 'offload_term_indexing_to_queue' ], 0, 3 ); // saved_term fires after SyncManager_Helper actions

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
	 *
	 * @param string $indexable_slug The Indexable slug
	 * @param array $options (optional) Array of options
	 *
	 * @return int|WP_Error The index version number to use, WP_Error on failure
	 */
	public function get_index_version_number_from_options( $indexable_slug, $options = array() ) {
		$index_version = isset( $options['index_version'] ) ? $options['index_version'] : null;

		if ( ! is_int( $index_version ) ) {
			$indexable = Indexables::factory()->get( $indexable_slug );

			if ( ! $indexable ) {
				return new WP_Error( 'invalid-indexable', sprintf( 'Indexable not found for type %s', $indexable_slug ) );
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
	 * @param string $indexable_slug The Indexable type, defaults to 'post'
	 * @param array $options (optional) Array of options
	 * @return int|WP_Error ID of the queued object, 0 or WP_Error on failure
	 */
	public function queue_object( $object_id, $indexable_slug = 'post', $options = array() ) {
		$indexable = Indexables::factory()->get( $indexable_slug );
		if ( ! $indexable ) {
			return new WP_Error( 'invalid-indexable', sprintf( 'Indexable not found for type %s', $indexable_slug ) );
		}

		if ( ! $indexable->index_exists() ) {
			return new WP_Error( 'index-not-exists', sprintf( 'Index not found for type %s', $indexable_slug ) );
		}

		global $wpdb;

		$next_index_time = $this->get_next_index_time( $object_id, $indexable_slug );

		if ( is_int( $next_index_time ) ) {
			$next_index_time = gmdate( 'Y-m-d H:i:s', $next_index_time );
		} else {
			$next_index_time = null;
		}

		$index_version      = $this->get_index_version_number_from_options( $indexable_slug, $options );
		$start_time_escaped = $next_index_time ? $wpdb->prepare( '%s', array( $next_index_time ) ) : 'NULL';
		$priority           = (int) ( $options['priority'] ?? self::INDEX_DEFAULT_PRIORITY );

		$table_name = $this->schema->get_table_name();

		$original_suppress = $wpdb->suppress_errors;

		// Suppress errors because duplicate key errors are expected, as we use the UNIQUE index
		// to de-duplicate queued jobs without first querying to see if the object is queued
		$wpdb->suppress_errors( true );

		// phpcs:disable WordPress.DB -- the code below breaks literally all DB rules :-)
		$wpdb->query(
			$wpdb->prepare( "INSERT INTO {$table_name} (object_id, object_type, status, index_version, start_time, priority)
				VALUES (%d, %s, 'queued', %d, {$start_time_escaped}, %d) ON DUPLICATE KEY UPDATE priority = LEAST(priority, %d)",
				[ $object_id, $indexable_slug, $index_version, $priority, $priority ]
			)
		);
		// phpcs:enable

		$insert_id = $wpdb->insert_id;

		/**
		 * Fires when an object is requested to be queued. Note that this fires regardless of if the object is actually queued
		 * as it may have already been in the queue (and a new db row was not actually created)
		 *
		 * @param int $object_id Object id
		 * @param string $indexable_slug The Indexable type
		 * @param array $options The options passed to queue_object()
		 * @param int $index_version The index version that was used when queuing the object
		 */
		do_action( 'vip_search_indexing_object_queued', $object_id, $indexable_slug, $options, $index_version );

		$wpdb->suppress_errors( $original_suppress );

		// TODO handle errors other than duplicate entry

		return $insert_id;
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
	 * @param string $indexable_slug The Indexable slug, defaults to 'post'
	 * @param array $options (optional) Array of options
	 */
	public function queue_objects( $object_ids, $indexable_slug = 'post', $options = array() ) {
		if ( ! is_array( $object_ids ) ) {
			return;
		}

		$indexable = Indexables::factory()->get( $indexable_slug );

		if ( ! $indexable || ! $indexable->index_exists() ) {
			return;
		}

		foreach ( $object_ids as $object_id ) {
			$this->queue_object( $object_id, $indexable_slug, $options );
		}
	}

	/**
	 * Retrieve the unix timestamp representing the soonest time that a given object can be indexed
	 *
	 * This provides rate limiting by checking the cached timestamp of the last successful indexing operation
	 * and applying the defined minimum interval between successive indexing jobs
	 *
	 * @param int $object_id The id of the object
	 * @param string $indexable_slug The Indexable slug
	 * @param array $options (optional) Array of options
	 * @return int|null $next_index_time The soonest unix timestamp when the object can be indexed again
	 */
	public function get_next_index_time( $object_id, $indexable_slug, $options = array() ) {
		$last_index_time = $this->get_last_index_time( $object_id, $indexable_slug, $options );

		$next_index_time = null;

		if ( is_int( $last_index_time ) && $last_index_time ) {
			// Next index time is last index time + interval
			$next_index_time = $last_index_time + $this->get_index_interval_time();
		}

		return $next_index_time;
	}

	/**
	 * Retrieve the unix timestamp representing the most recent time an object was indexed
	 *
	 * @param int $object_id The id of the object
	 * @param string $indexable_slug The Indexable slug
	 * @param array $options (optional) Array of options
	 * @return int $last_index_time The unix timestamp when the object was last indexed
	 */
	public function get_last_index_time( $object_id, $indexable_slug, $options = array() ) {
		$cache_key = $this->get_last_index_time_cache_key( $object_id, $indexable_slug, $options );

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
	 * @param string $indexable_slug The Indexable slug
	 * @param int $time Unix timestamp when the object was last indexed
	 * @param array $options (optional) Array of options
	 */
	public function set_last_index_time( $object_id, $indexable_slug, $time, $options = array() ) {
		$cache_key = $this->get_last_index_time_cache_key( $object_id, $indexable_slug, $options );

		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_set( $cache_key, $time, self::CACHE_GROUP, self::OBJECT_LAST_INDEX_TIMESTAMP_TTL );
	}

	/**
	 * Get the cache key used to track an object's last indexed timestamp
	 *
	 * @param int $object_id The id of the object
	 * @param string $indexable_slug The Indexable slug
	 * @param array $options (optional) Array of options
	 * @return string The cache key to use for the object's last indexed timestamp
	 */
	public function get_last_index_time_cache_key( $object_id, $indexable_slug, $options = array() ) {
		$index_version = $this->get_index_version_number_from_options( $indexable_slug, $options );

		return sprintf( '%s-%d-v%d', $indexable_slug, $object_id, $index_version );
	}

	/**
	 * Get the interval between successive index operations on a given object
	 *
	 * @return int Minimum number of seconds between re-indexes
	 */
	public function get_index_interval_time() {
		// Room for future improvement - on non-active index versions, increase the time between re-indexing a given object

		return 60;
	}

	/**
	 * Update a specific job with input data
	 *
	 * @param int $job_id Job id to update
	 * @param array $data Data to update $job_id with
	 * @return int|bool Number of rows updated, false on failure
	 */
	public function update_job( $job_id, $data ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->update( $table_name, $data, array( 'job_id' => $job_id ) );
	}

	/**
	 * Update a list of jobs
	 *
	 * @param array $job_ids Array of job ids to update
	 * @param array $data Data to update jobs with
	 * @return array Array of jobs updated
	 */
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

	/**
	 * Delete a list of jobs
	 *
	 * @param array $job_ids Array of job objects to delete
	 * @return array Array of jobs deleted
	 */
	public function delete_jobs( $jobs ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$ids = wp_list_pluck( $jobs, 'job_id' );

		$escaped_ids = implode( ', ', array_map( 'intval', $ids ) );

		$sql = "DELETE FROM {$table_name} WHERE `job_id` IN ( {$escaped_ids} )"; // Cannot prepare table name. @codingStandardsIgnoreLine

		return $wpdb->get_results( $sql ); // Already escaped. @codingStandardsIgnoreLine
	}

	/**
	 * Empty search index queue table
	 *
	 * @return int|bool Number of rows deleted, false on failure
	 */
	public function empty_queue() {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		return $wpdb->query( "DELETE FROM {$table_name}" ); // Cannot prepare table name. @codingStandardsIgnoreLine
	}

	/**
	 * Count the number of jobs present
	 *
	 * @param string $status Status of jobs to count
	 * @param string $indexable_slug The Indexable slug for jobs to count, default is 'post'
	 * @param array $options (optional) Array of options
	 * @return int $job_count Number of jobs counted
	 */
	public function count_jobs( $status, $indexable_slug = 'post', $options = array() ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$query = null;

		// TODO should we support $index_version here? Is there a better way to structure these conditionals?
		if ( 'all' === strtolower( $status ) ) {
			if ( 'all' === strtolower( $indexable_slug ) ) {
				$query = "SELECT COUNT(*) FROM {$table_name} WHERE 1"; // Cannot prepare table name. @codingStandardsIgnoreLine
			} else {
				$query = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE `object_type` = %s", // Cannot prepare table name. @codingStandardsIgnoreLine
					$indexable_slug
				);
			}
		}

		// If query has not already been set, it's a "normal" query. This is done after b/c the index version lookup will fail
		// when $indexable_slug is equal to 'all' since this is not a valid Indexable
		if ( ! $query ) {
			$index_version = $this->get_index_version_number_from_options( $indexable_slug, $options );

			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE `status` = %s AND `object_type` = %s AND `index_version` = %d", // Cannot prepare table name. @codingStandardsIgnoreLine
				$status,
				$indexable_slug,
				$index_version
			);
		}

		$job_count = $wpdb->get_var( $query ); // Query may change depending on status/object type @codingStandardsIgnoreLine

		return intval( $job_count );
	}

	/**
	 * Count the number of jobs due now
	 *
	 * @param string $indexable_slug The Indexable slug for jobs to count
	 * @return int|null Number of jobs due now, null on failure
	 */
	public function count_jobs_due_now( $indexable_slug = 'post' ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE `status` = 'queued' AND `object_type` = %s AND ( `start_time` <= NOW() OR `start_time` IS NULL )", // Cannot prepare table name. @codingStandardsIgnoreLine
				$indexable_slug
			)
		);
	}

	/**
	 * Get next job for an object
	 *
	 * @param int $object_id ID of object
	 * @param string $indexable_slug The Indexable slug
	 * @param array $options (optional) Array of options
	 * @return object $job Next job for $object_id
	 */
	public function get_next_job_for_object( $object_id, $indexable_slug, $options = array() ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$index_version = $this->get_index_version_number_from_options( $indexable_slug, $options );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE `object_id` = %d AND `object_type` = %s AND `index_version` = %d AND `status` = 'queued' LIMIT 1", // Cannot prepare table name. @codingStandardsIgnoreLine
				$object_id,
				$indexable_slug,
				$index_version
			)
		);

		return $job;
	}

	/**
	 * Get the jobs using the lowest and highest job_id range
	 *
	 * @param int $min_id Minimum job_id
	 * @param int $max_id Maximum job_id
	 * @return array $jobs Array of job objects
	 */
	public function get_jobs_by_range( $min_id, $max_id ) {
		global $wpdb;

		if ( ! $min_id || ! $max_id ) {
			return array();
		}

		$table_name = $this->schema->get_table_name();

		$min_job_id_value = intval( $min_id );
		$max_job_id_value = intval( $max_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE `job_id` >= %d AND `job_id` <= %d",  // Cannot prepare table name. @codingStandardsIgnoreLine
				$min_job_id_value,
				$max_job_id_value
			)
		);

		return $jobs;
	}

	/**
	 * Get the jobs using a specific list of ids
	 *
	 * @param array $ids IDs of the jobs to retrieve
	 * @return array Array of job objects
	 */
	public function get_jobs_by_ids( array $ids ) {
		global $wpdb;
		$result = [];

		if ( ! empty( $ids ) ) {
			$table_name    = $this->schema->get_table_name();
			$sanitized_ids = join( ', ', array_map( 'intval', $ids ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$result = $wpdb->get_results( "SELECT * FROM {$table_name} WHERE `job_id` IN ({$sanitized_ids})" );
		}

		return $result;
	}

	/**
	 * Grab $count jobs that are due now and mark them as running
	 *
	 * @param int $count How many jobs to check out
	 * @return array Array of jobs (db rows) that were checked out
	 */
	public function checkout_jobs( $count = 250 ) {
		// Enforce a reasonable limit on batch size
		$count = min( $count, self::MAX_BATCH_SIZE );
		$count = max( $count, 1 );

		global $wpdb;

		$table_name = $this->schema->get_table_name();

		// TODO transaction
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE ( `start_time` <= NOW() OR `start_time` IS NULL ) AND `status` = 'queued' ORDER BY `priority`, `job_id` LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			'status'         => 'scheduled',
			'scheduled_time' => $scheduled_time,
		) );

		// Set right status on the already queried jobs objects
		foreach ( $jobs as &$job ) {
			$job->status         = 'scheduled';
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
	 *
	 * @param int $count Number of deadlocked jobs to retrieve at once, defaults to 250
	 * @return array $jobs Array of deadlocked jobs
	 */
	public function get_deadlocked_jobs( $count = 250 ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		// If job was scheduled before this time, it is considered deadlocked
		$deadlocked_time = time() - self::DEADLOCK_TIME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$jobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE `status` IN ( 'scheduled', 'running' ) AND (`scheduled_time` <= %s OR `scheduled_time` IS NULL) LIMIT %d", // Cannot prepare table name. @codingStandardsIgnoreLine
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

			$filtered_deadlocked_jobs = $this->delete_jobs_on_the_same_object( $deadlocked_jobs );
			$filtered_deadlocked_jobs = $this->delete_jobs_on_the_already_queued_object( $filtered_deadlocked_jobs );

			$deadlocked_job_ids = wp_list_pluck( $filtered_deadlocked_jobs, 'job_id' );

			$this->update_jobs( $deadlocked_job_ids, array(
				'status'         => 'queued',
				'scheduled_time' => null,
			) );
		}
	}

	/**
	 * In some cases there could be multiple jobs for the same object stuck in a deadlock state.
	 * If we would try to update all of them at once we would break the DB constraint
	 * (state + object_id + object_type + index_version).
	 *
	 * We will delete the duplicate from the DB table as well as from the list of jobs to be re-queued.
	 *
	 * @param array $all_deadlocked_jobs Array of all deadlocked jobs
	 * @return array $filtered_deadlocked_jobs Unique deadlocked jobs
	 */
	private function delete_jobs_on_the_same_object( $all_deadlocked_jobs ) {
		$found_objects            = [];
		$filtered_deadlocked_jobs = [];
		$jobs_to_be_deleted       = [];

		foreach ( $all_deadlocked_jobs as $job ) {
			$unique_key = sprintf( '%s_%s_%s',
				$job->{'object_id'},
				$job->{'object_type'},
				$job->{'index_version'}
			);

			if ( array_key_exists( $unique_key, $found_objects ) ) {
				$jobs_to_be_deleted[] = $job;
			} else {
				$found_objects[ $unique_key ] = true;

				$filtered_deadlocked_jobs[] = $job;
			}
		}

		if ( ! empty( $jobs_to_be_deleted ) ) {
			$this->delete_jobs( $jobs_to_be_deleted );
		}

		return $filtered_deadlocked_jobs;
	}

	/**
	 * We can't re-queue jobs that are already waiting in queue. We should remove such jobs instead.
	 *
	 * @param array $deadlocked_jobs List of deadlocked jobs
	 * @return array $deadlocked_jobs List of deadlocked jobs deleted from queue
	 */
	public function delete_jobs_on_the_already_queued_object( $deadlocked_jobs ) {
		global $wpdb;

		$table_name = $this->schema->get_table_name();

		$job_ids = wp_list_pluck( $deadlocked_jobs, 'job_id' );

		$escaped_ids = implode( ', ', array_map( 'intval', $job_ids ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$jobs_to_be_deleted = $wpdb->get_results(
			 // Cannot prepare table name. @codingStandardsIgnoreStart
			"SELECT deadlocked.* FROM {$table_name} deadlocked WHERE `job_id` IN ( {$escaped_ids} ) AND EXISTS (
				 SELECT queued.* FROM {$table_name} queued
				 WHERE queued.status = 'queued'
				 AND queued.object_id = deadlocked.object_id
				 AND queued.object_type = deadlocked.object_type
				 AND queued.index_version = deadlocked.index_version
				 AND queued.job_id != deadlocked.job_id
			)",
			 // @codingStandardsIgnoreEnd
		);

		if ( ! empty( $jobs_to_be_deleted ) ) {
			$this->delete_jobs( $jobs_to_be_deleted );
		}

		return $deadlocked_jobs;
	}

	/**
	 * Process jobs
	 *
	 * @param array $jobs List of jobs
	 */
	public function process_jobs( $jobs ) {
		// Set them as running
		$job_ids = wp_list_pluck( $jobs, 'job_id' );

		$this->update_jobs( $job_ids, array( 'status' => 'running' ) );

		$indexables = Indexables::factory();

		// Organize by version and type, so we can process each unique batch in bulk
		$jobs_by_version_and_type = $this->organize_jobs_by_index_version_and_type( $jobs );

		// Batch process each type using the indexable
		foreach ( $jobs_by_version_and_type as $index_version => $jobs_by_type ) {
			foreach ( $jobs_by_type as $type => $jobs ) {
				$indexable = $indexables->get( $type );

				// If the index version no longer exists, just delete the jobs and don't bother with stats or anything
				// since the jobs weren't actually processed
				$index_versions = \Automattic\VIP\Search\Search::instance()->versioning->get_versions( $indexable );
				if ( ! array_key_exists( intval( $index_version ), $index_versions ) ) {
					$this->delete_jobs( $jobs );
					continue;
				}

				\Automattic\VIP\Search\Search::instance()->versioning->set_current_version_number( $indexable, $index_version );

				$ids = wp_list_pluck( $jobs, 'object_id' );

				// Increment first to prevent overrunning ratelimiting
				static::index_count_incr( count( $ids ) );

				\Automattic\VIP\Logstash\log2logstash(
					[
						'severity' => 'info',
						'feature'  => 'search_queue',
						'message'  => 'Indexing content',
						'blog_id'  => get_current_blog_id(),
						'extra'    => [
							'homeurl'    => home_url(),
							'index_name' => $indexable->get_index_name(),
							'count'      => count( $ids ),
						],
					]
				);

				$indexable->bulk_index( $ids );

				// TODO handle errors

				// Mark them as done in queue
				$this->delete_jobs( $jobs );

				\Automattic\VIP\Search\Search::instance()->versioning->reset_current_version_number( $indexable );
			}
		}
	}

	/**
	 * Given an array of jobs, sort them into sub arrays by type and index version
	 *
	 * This helps us minimize the cost of switching between versions and types (Indexables) when processing a list of jobs
	 *
	 * @param array $jobs Array of jobs to sort
	 * @return array $organized Multi-dimensional array of jobs, first keyed by version, then by type
	 */
	public function organize_jobs_by_index_version_and_type( $jobs ) {
		$organized = array();

		foreach ( $jobs as $job ) {
			if ( ! isset( $organized[ $job->index_version ] ) ) {
				$organized[ $job->index_version ] = array();
			}

			if ( ! isset( $organized[ $job->index_version ][ $job->object_type ] ) ) {
				$organized[ $job->index_version ][ $job->object_type ] = array();
			}

			$organized[ $job->index_version ][ $job->object_type ][] = $job;
		}

		return $organized;
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
	 *
	 * @param int $term_id Term id
	 * @param int $tt_id Term taxonomy id
	 * @param string $taxonomy Taxonomy
	 */
	public function offload_term_indexing_to_queue( $term_id, $tt_id, $taxonomy ) {
		$term = \get_term( $term_id, $taxonomy );

		if ( is_wp_error( $term ) || ! is_object( $term ) ) {
			return;
		}

		if ( true === apply_filters( 'ep_skip_action_edited_term', false, $term_id, $tt_id, $taxonomy ) ) {
			return; // Do not offload if SyncManager_Helper is skipping actions on immaterial term changes
		}

		// If the number of affected posts is low enough, process them now rather than send them to cron
		if ( $term->count <= self::$max_sync_indexing_count ) {
			$this->offload_indexing_to_queue();
			return;
		}

		add_filter( 'ep_skip_action_edited_term', '__return_true' ); // Disable ElasticPress execution on term edit
		$this->cron->schedule_queue_posts_for_term_taxonomy_id( $term->term_taxonomy_id );
	}

	/**
	 * Send objects to the queue processor cron job instead of directly syncing
	 *
	 * @param bool $bail Whether to skip the syncing process
	 * @param SyncManager $sync_manager SyncManager instance for Indexable
	 * @param string $indexable_slug The Indexable slug
	 * @return bool Whether to intercept the sync process
	 */
	public function intercept_ep_sync_manager_indexing( $bail, $sync_manager, $indexable_slug ) {
		// Only posts supported right now
		if ( 'post' !== $indexable_slug ) {
			return $bail;
		}

		if ( empty( $sync_manager->sync_queue ) ) {
			return $bail;
		}

		$this->queue_objects( array_keys( $sync_manager->sync_queue ), $indexable_slug );

		// If indexing operations are NOT currently ratelimited, queue up a cron event to process these immediately.
		if ( ! static::is_indexing_ratelimited() ) {
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
	 * @param array $document_ids IDs of the documents that were to be indexed
	 * @param string $slug Indexable slug
	 * @param array|boolean $return Elasticsearch response. False on error.
	 * @return bool Whether anything was done
	 */
	public function action__ep_after_bulk_index( $document_ids, $slug, $return ) {
		if ( false === $this->is_enabled() || ! is_array( $document_ids ) || 'post' !== $slug || false !== $return ) {
			return false;
		}

		// TODO shouldn't this have a type?

		$this->queue_objects( $document_ids );

		return true;
	}

	/**
	 * Hook pre_ep_index_sync_queue for indexing operation ratelimiting.
	 * If ratelimited, bail and offload indexing to queue.
	 *
	 * @param bool $bail Current value of bail
	 * @param object $sync_manager Instance of Sync_Manager
	 * @param string $indexable_slug Indexable slug
	 * @return bool Whether to bail on indexing or not
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
		$increment             = count( $sync_manager->sync_queue );
		$index_count_in_period = static::index_count_incr( $increment );

		// If indexing operation ratelimiting is hit, queue index operations
		if ( $index_count_in_period > static::$max_indexing_op_count ) {
			if ( class_exists( Prometheus_Collector::class ) ) {
				Prometheus_Collector::increment_ratelimited_index_counter( Search::instance()->get_current_host(), $increment );
			}

			$this->handle_index_limiting_start_timestamp();
			$this->maybe_alert_for_prolonged_index_limiting();

			// Offload indexing to async queue
			$this->intercept_ep_sync_manager_indexing( $bail, $sync_manager, $indexable_slug );

			if ( ! static::is_indexing_ratelimited() ) {
				static::turn_on_index_ratelimiting();
				$this->log_index_ratelimiting_start();
			}
		} else {
			static::turn_off_index_ratelimiting();
			$this->clear_index_limiting_start_timestamp();
		}

		// Honor filters that want to bail on indexing while also honoring ratelimiting
		return true === $bail || true === static::is_indexing_ratelimited();
	}

	/**
	 * Get the start time for indexing rate limiting
	 *
	 * @return int|false Timestamp of when indexing rate limiting started, or false if not set
	 */
	public static function get_indexing_rate_limit_start() {
		return wp_cache_get( self::INDEX_RATE_LIMITED_START_CACHE_KEY, self::INDEX_COUNT_CACHE_GROUP );
	}

	public function maybe_alert_for_prolonged_index_limiting() {
		$index_limiting_start = static::get_indexing_rate_limit_start();

		if ( false === $index_limiting_start ) {
			return;
		}

		$index_limiting_time = time() - $index_limiting_start;

		if ( $index_limiting_time < self::INDEX_RATE_LIMITED_ALERT_LIMIT ) {
			return;
		}

		$message = sprintf(
			'Application %d - %s has had its Elasticsearch indexing rate limited for %d seconds. Large batch indexing operations are being queued for indexing in batches over time.',
			FILES_CLIENT_SITE_ID,
			home_url(),
			$index_limiting_time
		);

		\Automattic\VIP\Utils\Alerts::instance()->send_to_chat( self::INDEX_RATE_LIMITING_ALERT_SLACK_CHAT, $message, self::INDEX_RATE_LIMITING_ALERT_LEVEL );

		trigger_error( $message, \E_USER_WARNING ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		\Automattic\VIP\Logstash\log2logstash(
			array(
				'severity' => 'warning',
				'feature'  => 'search_indexing_rate_limiting',
				'message'  => $message,
			)
		);
	}

	/**
	 * Check whether indexing is currently ratelimited
	 *
	 * @return bool Whether indexing is currently ratelimited
	 */
	public static function is_indexing_ratelimited() {
		return false !== wp_cache_get( self::INDEX_QUEUEING_ENABLED_KEY, self::INDEX_COUNT_CACHE_GROUP );
	}

	/**
	 *  Turn on ratelimit indexing
	 *
	 * @return bool True on success, false on failure
	 */
	public static function turn_on_index_ratelimiting() {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		return wp_cache_set( self::INDEX_QUEUEING_ENABLED_KEY, true, self::INDEX_COUNT_CACHE_GROUP, self::$index_queueing_ttl );
	}

	/**
	 *  Turn off ratelimit indexing
	 *
	 * @return bool void
	 */
	public static function turn_off_index_ratelimiting() {
		wp_cache_delete( self::INDEX_QUEUEING_ENABLED_KEY, self::INDEX_COUNT_CACHE_GROUP );
	}

	/**
	 * Get the current queue stats
	 *
	 * @return object An object containing queue stats - average_wait_time, longest_wait_time, queue_count
	 */
	public function get_queue_stats() {
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$queue_stats = $wpdb->get_row(
				// Query does not need preparation. @codingStandardsIgnoreStart
				"SELECT
					FLOOR( AVG( TIMESTAMPDIFF( SECOND, queued_time, NOW() ) ) ) AS average_wait_time,
					FLOOR( MAX( TIMESTAMPDIFF( SECOND, queued_time, NOW() ) ) ) AS longest_wait_time,
					COUNT( * ) AS queue_count
				FROM $table_name
				WHERE 1"
				// @codingStandardsIgnoreEnd
		);

		// Null value will usually mean empty table
		if ( is_null( $queue_stats ) || ! is_object( $queue_stats ) ) {
			return (object) [
				'average_wait_time' => 0,
				'longest_wait_time' => 0,
				'queue_count'       => 0,
			];
		}

		$queue_stats->average_wait_time = intval( $queue_stats->average_wait_time );
		$queue_stats->longest_wait_time = intval( $queue_stats->longest_wait_time );
		$queue_stats->queue_count       = intval( $queue_stats->queue_count );

		return $queue_stats;
	}

	/**
	 * Given an indexable slug and an index version, delete all matching jobs from the queue.
	 *
	 * Used to clean up after an index version deletion and prevent processing jobs that don't need to be processed.
	 *
	 * @param string $indexable_slug An indexable slug
	 * @param int|string $index_version An index version
	 * @return null|WP_Error|object Either null if the queue isn't enabled, a WP_Error if the parameters are incorrect, or the results of wpdb::get_results()
	 */
	public function delete_jobs_for_index_version( $indexable_slug, $index_version ) {
		global $wpdb;

		// If run without having the queue enabled, queue wait times are 0
		if ( ! $this->is_enabled() ) {
			return null;
		}

		if ( ! is_string( $indexable_slug ) ) {
			return new WP_Error( 'invalid-slug-for-queue-cleanup-on-delete', sprintf( 'Invalid indexable slug \'%s\'', $indexable_slug ) );
		}

		if ( ! is_numeric( $index_version ) ) {
			return new WP_Error( 'invalid-version-for-queue-cleanup-on-delete', sprintf( 'Invalid version \'%d\'', $index_version ) );
		}

		// If schema is null, init likely not run yet
		// Happens when cron is scheduled/run via wp commands
		if ( is_null( $this->schema ) ) {
			$this->init();
		}

		$table_name = $this->schema->get_table_name();

		return $wpdb->get_results( $wpdb->prepare( "DELETE FROM `{$table_name}` WHERE `object_type` = %s AND `index_version` = %d", $indexable_slug, $index_version ) ); // @codingStandardsIgnoreLine
	}

	/**
	 * Increment the number of indexing operations that have been passed through VIP Search
	 *
	 * @param int $increment Number to increment
	 * @return int|bool New value on success, false on failure
	 */
	private static function index_count_incr( $increment = 1 ) {
		if ( false === wp_cache_get( static::INDEX_COUNT_CACHE_KEY, static::INDEX_COUNT_CACHE_GROUP ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( static::INDEX_COUNT_CACHE_KEY, 0, static::INDEX_COUNT_CACHE_GROUP, static::$index_count_ttl );
			static::turn_off_index_ratelimiting();
		}

		return wp_cache_incr( self::INDEX_COUNT_CACHE_KEY, $increment, self::INDEX_COUNT_CACHE_GROUP );
	}

	/**
	 * Checks if the index limiting start timestamp is set, set it otherwise
	 */
	public function handle_index_limiting_start_timestamp() {
		if ( false === static::get_indexing_rate_limit_start() ) {
			$start_timestamp = time();
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( static::INDEX_RATE_LIMITED_START_CACHE_KEY, $start_timestamp, static::INDEX_COUNT_CACHE_GROUP );
		}
	}

	public function clear_index_limiting_start_timestamp() {
		wp_cache_delete( static::INDEX_RATE_LIMITED_START_CACHE_KEY, static::INDEX_COUNT_CACHE_GROUP );
	}

	/**
	 * When indexing rate limting first begins, log this information and surface as a PHP warning
	 */
	public function log_index_ratelimiting_start() {
		$message = sprintf(
			'Application %d - %s has triggered Elasticsearch indexing rate limiting, which will last for %d seconds. Large batch indexing operations are being queued for indexing in batches over time.',
			FILES_CLIENT_SITE_ID,
			\home_url(),
			self::$index_queueing_ttl
		);

		$this->logger->log(
			'warning',
			'search_indexing_rate_limiting',
			$message
		);
	}
}
