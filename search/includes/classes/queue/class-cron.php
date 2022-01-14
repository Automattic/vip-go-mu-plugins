<?php

namespace Automattic\VIP\Search\Queue;

use Automattic\VIP\Search\Queue as Queue;
use Automattic\WP\Cron_Control\Events_Store;
use \ElasticPress\Indexables as Indexables;
use WP_Error;

class Cron {
	/**
	 * The name of the cron event to run the index processing
	 */
	const PROCESSOR_CRON_EVENT_NAME = 'vip_search_queue_processor';

	/**
	 * How many objects to re-index at a time in a single cron job
	 */
	const PROCESSOR_MAX_OBJECTS_PER_CRON_EVENT = 1000;

	/**
	 * The name of the recurring cron event that checks for any unscheduled or deadlocked jobs
	 */
	const SWEEPER_CRON_EVENT_NAME = 'vip_search_queue_sweeper';

	/**
	 * Custom cron interval name for the "sweeper"
	 */
	const SWEEPER_CRON_INTERVAL_NAME = 'vip_search_queue_sweeper_interval';

	/**
	 * Custom cron interval value
	 */
	const SWEEPER_CRON_INTERVAL = 1 * \MINUTE_IN_SECONDS;

	/**
	 * The name of the cron event for processing term updates
	 */
	const TERM_UPDATE_CRON_EVENT_NAME = 'vip_search_term_update';

	/**
	 * The number of objects queued per batch for term updates
	 */
	const TERM_UPDATE_BATCH_SIZE = 25000;

	/**
	 * The maximum number if processor jobs allowed at one time
	 */
	const MAX_PROCESSOR_JOB_COUNT = 3;

	/**
	 * Instance of Automattic\VIP\Search\Queue that created this Cron instance
	 * @var Queue
	 */
	public $queue;

	/**
	 * Initialize the job class
	 *
	 * @access public
	 */
	public function init() {
		// We always add this action so that the job can unregister itself if it no longer should be running
		add_action( self::PROCESSOR_CRON_EVENT_NAME, [ $this, 'process_jobs' ] );
		add_action( self::SWEEPER_CRON_EVENT_NAME, [ $this, 'sweep_jobs' ] );
		add_action( self::TERM_UPDATE_CRON_EVENT_NAME, [ $this, 'queue_posts_for_term_taxonomy_id' ] );

		add_filter( 'a8c_cron_control_concurrent_event_whitelist', [ $this, 'configure_concurrency' ] );

		if ( ! $this->is_enabled() ) {
			return;
		}

		// Add the custom cron schedule
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_filter( 'cron_schedules', [ $this, 'filter_cron_schedules' ] );

		// Hook into init actions(except for init) to ensure cron-control has already been loaded
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			add_action( 'wp_loaded', [ $this, 'schedule_sweeper_job' ], 0 );
		} else {
			add_action( 'admin_init', [ $this, 'schedule_sweeper_job' ], 0 );
		}
	}

	public function get_max_concurrent_processor_job_count() {
		$allowed_total_concurrency = (int) ceil( \Automattic\WP\Cron_Control\JOB_CONCURRENCY_LIMIT / 4 );
		return min( self::MAX_PROCESSOR_JOB_COUNT, $allowed_total_concurrency );
	}

	/**
	 * Make job processing = indexing of documents run concurrently. This should help with handling spikes
	 * of bulk reindexing as well as keep cron's option used for queued jobs small, by processing them faster.
	 *
	 * Also, we want to make sure to only take up to 25% of avaiable cron concurrency capacity, so that other
	 * cron jobs can still be processed without a big impact.
	 */
	public function configure_concurrency( $whitelist ) {
		$whitelist[ self::PROCESSOR_CRON_EVENT_NAME ] = $this->get_max_concurrent_processor_job_count();

		return $whitelist;
	}

	/**
	 * Schedule sweeper job
	 *
	 * This event checks for any queued items that haven't yet been scheduled, and any that are deadlocked
	 */
	public function schedule_sweeper_job() {
		if ( ! wp_next_scheduled( self::SWEEPER_CRON_EVENT_NAME ) ) {
			wp_schedule_event( time(), self::SWEEPER_CRON_INTERVAL_NAME, self::SWEEPER_CRON_EVENT_NAME );
		}
	}

	/**
	 * Disable recurring sweeper job
	 */
	public function disable_sweeper_job() {
		if ( wp_next_scheduled( self::SWEEPER_CRON_EVENT_NAME ) ) {
			wp_clear_scheduled_hook( self::SWEEPER_CRON_EVENT_NAME );
		}
	}

	/**
	 * Filter `cron_schedules` output
	 *
	 * Add the custom interval to WP cron schedule
	 *
	 * @param array $schedule
	 *
	 * @return mixed
	 */
	public function filter_cron_schedules( $schedule ) {
		if ( isset( $schedule[ self::SWEEPER_CRON_INTERVAL_NAME ] ) ) {
			return $schedule;
		}

		$schedule[ self::SWEEPER_CRON_INTERVAL_NAME ] = [
			'interval' => self::SWEEPER_CRON_INTERVAL,
			'display'  => __( 'VIP Search index queue job creator time interval' ),
		];

		return $schedule;
	}

	/**
	 * Process a batch of jobs via cron
	 *
	 * This is the cron hook for indexing a batch of objects
	 *
	 * @param array $options Containing either max_id and min_id or option keys
	 */
	public function process_jobs( $options ) {
		if ( ! empty( $options['option'] ) ) {
			$job_ids = get_option( $options['option'] );
			delete_option( $options['option'] );

			// Should not normally happen
			if ( ! is_array( $job_ids ) ) {
				return;
			}

			$jobs = $this->queue->get_jobs_by_ids( $job_ids );
		} else {
			$jobs = $this->queue->get_jobs_by_range( $options['min_id'], $options['max_id'] );
		}

		if ( empty( $jobs ) ) {
			return;
		}

		$this->queue->process_jobs( $jobs );
	}

	/**
	 * Given a term taxonomy id, queue all posts for reindexing that match it
	 *
	 * @param int $term_taxonomy_id The term taxonomy id you want to index
	 */
	public function queue_posts_for_term_taxonomy_id( $term_taxonomy_id ) {
		$indexable_post_types    = Indexables::factory()->get( 'post' )->get_indexable_post_types();
		$indexable_post_statuses = Indexables::factory()->get( 'post' )->get_indexable_post_status();

		// Only proceed if indexable post types are defined correctly
		if ( ! is_array( $indexable_post_types ) || empty( $indexable_post_types ) ) {
			return;
		}

		// Only proceed if indexable post statuses are defined correctly
		if ( ! is_array( $indexable_post_statuses ) || empty( $indexable_post_statuses ) ) {
			return;
		}

		// WP_Query args for looking up posts that match the term taxonomy id and indexable
		// post types/statuses
		$args = array(
			// phpcs:ignored WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page'         => self::TERM_UPDATE_BATCH_SIZE,
			'post_type'              => $indexable_post_types,
			'post_status'            => $indexable_post_statuses,
			'paged'                  => 1,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => true,
			'tax_query'              => array(  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'field' => 'term_taxonomy_id',
					'terms' => $term_taxonomy_id,
				),
			),
		);

		$posts = new \WP_Query( $args );

		// If no posts, just return early
		if ( ! $posts->have_posts() ) {
			return;
		}

		// Iterate pagination
		while ( $args['paged'] <= $posts->max_num_pages ) {
			// Queue all posts for page
			$this->queue->queue_objects( $posts->posts, 'post' );

			// Go to the next page and reset $posts
			$args['paged'] = intval( $args['paged'] ) + 1;
			$posts         = new \WP_Query( $args );

			// If page is empty, just return early
			if ( ! $posts->have_posts() ) {
				return;
			}
		}
	}

	/**
	 * Find objects that need to be processed (in a batch) and schedule an event to process them
	 *
	 * This is intended to be a "sweep" of any objects that may have been missed - as stuff gets queued,
	 * we schedule vip_search_queue_processor events immediately, but this helps find anything that fell through
	 * the cracks (fatal error or something) as well as identify deadlocks
	 */
	public function sweep_jobs() {
		// Check if job has been disabled
		if ( ! $this->is_enabled() ) {
			$this->disable_sweeper_job();
		}

		$this->queue->free_deadlocked_jobs();

		$job_count     = $this->get_processor_job_count();
		$max_job_count = $this->get_max_concurrent_processor_job_count();

		while ( ! is_wp_error( $job_count ) && $job_count < $max_job_count ) {
			$schedule_success = $this->schedule_batch_job();

			// A WP_Error means the event couldn't be scheduled due to concurrency limit.
			// FALSE means an empty queue, so there's nothing to do here anymore.
			if ( is_wp_error( $schedule_success ) || false === $schedule_success ) {
				break;
			}

			$job_count = $this->get_processor_job_count();
		}
	}

	/**
	 * Get the number of processor jobs already scheduled.
	 *
	 * @return int|WP_Error - count of jobs or error if we failed to fetch the current number.
	 */
	public function get_processor_job_count() {
		// If cron control isn't available, only schedule one job
		if ( ! class_exists( Events_Store::class ) ) {
			return new \WP_Error( 'vip-search-cron-no-events-store', 'Automattic\\WP\\Cron_Control\\Events_Store is not defined' );
		}

		global $wpdb;

		/** @var Events_Store */
		$event_store = Events_Store::instance();
		$table_name  = $event_store->get_table_name();

		$current_processor_job_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE action = 'vip_search_queue_processor' AND status != 'complete'" ); // Cannot prepare table name. @codingStandardsIgnoreLine

		// If null, only schedule one job
		if ( is_null( $current_processor_job_count ) ) {
			return new \WP_Error( 'vip-search-cron-processor-count', 'Could not find the current search queue processor count' );
		}

		return intval( $current_processor_job_count );
	}

	/**
	 * Checkout items from queue and schedule a job to process them.
	 *
	 * @return boolean|WP_Error true if scheduling went well, false if there are no items to schedule, error otherwise
	 */
	public function schedule_batch_job() {
		// Find jobs to process
		$jobs = $this->queue->checkout_jobs( self::PROCESSOR_MAX_OBJECTS_PER_CRON_EVENT );

		if ( empty( $jobs ) ) {
			return false;
		}

		$job_ids = wp_list_pluck( $jobs, 'job_id' );

		$base = 'vip:esqp_';
		do {
			$random = wp_generate_uuid4();
			$option = $base . $random;
		} while ( false === add_option( $option, $job_ids, '', false ) );

		$options = [
			'option' => $option,
		];

		return wp_schedule_single_event( time(), self::PROCESSOR_CRON_EVENT_NAME, [ $options ], true );
	}

	public function schedule_queue_posts_for_term_taxonomy_id( $term_taxonomy_id ) {
		wp_schedule_single_event( time(), self::TERM_UPDATE_CRON_EVENT_NAME, array( $term_taxonomy_id ) );
	}

	/**
	 * Are the cron jobs enabled
	 *
	 * @return bool True if job is enabled. Else, false
	 */
	public function is_enabled() {
		return true;
	}
}
