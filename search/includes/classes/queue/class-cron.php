<?php

namespace Automattic\VIP\Search\Queue;

use Automattic\VIP\Search\Queue as Queue;

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
	const SWEEPER_CRON_INTERVAL = 5 * \MINUTE_IN_SECONDS;

	/**
	 * Instance of Automattic\VIP\Search\Queue that created this Cron instance
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

		if ( ! $this->is_enabled() ) {
			return;
		}

		// Add the custom cron schedule
		add_filter( 'cron_schedules', [ $this, 'filter_cron_schedules' ], 10, 1 );

		$this->schedule_sweeper_job();
	}

	/**
	 * Schedule health check job
	 *
	 * Add the event name to WP cron schedule and then add the action
	 */
	public function schedule_sweeper_job() {
		if ( ! wp_next_scheduled( self::SWEEPER_CRON_EVENT_NAME ) ) {
			wp_schedule_event( time(), self::SWEEPER_CRON_INTERVAL, self::SWEEPER_CRON_EVENT_NAME );
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
			'display' => __( 'VIP Search index queue job creator time interval' ),
		];

		return $schedule;
	}

	/**
	 * Process a batch of jobs via cron
	 * 
	 * This is the cron hook for indexing a batch of objects
	 * 
	 * @param {array} $job_ids Array of job ids to process
	 */
	public function process_jobs( $job_ids ) {
		$jobs = $this->queue->get_jobs( $job_ids );

		if ( empty( $jobs ) ) {
			return;
		}

		$this->queue->process_jobs( $jobs );
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

			return;
		}

		// TODO add a "max batches" setting and keep creating batch jobs until none are found or we hit the max

		// Find jobs to process
		$jobs = $this->queue->checkout_jobs( self::PROCESSOR_MAX_OBJECTS_PER_CRON_EVENT );

		if ( empty( $jobs ) ) {
			return;
		}

		$job_ids = wp_list_pluck( $jobs, 'job_id' );

		wp_schedule_single_event( time(), self::PROCESSOR_CRON_EVENT_NAME, $job_ids );
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
