<?php

namespace Automattic\VIP\Search\Queue;

use Automattic\VIP\Search\Queue as Queue;

class Cron {
	/**
	 * The name of the cron event to run the index processing
	 */
	const CRON_PROCESSOR_EVENT_NAME = 'vip_search_queue_processor';

	/**
	 * How many objects to re-index at a time in a single cron job
	 */
	const CRON_PROCESSOR_MAX_OBJECTS_PER_JOB = 1000;

	/**
	 * The name of the recurring cron event that schedules individual cron events to process objects
	 */
	const CRON_JOB_CREATOR_EVENT_NAME = 'vip_search_queue_job_creator';

	/**
	 * Custom cron interval name
	 */
	const CRON_JOB_CREATOR_INTERVAL_NAME = 'vip_search_queue_job_creator_interval';

	/**
	 * Custom cron interval value
	 */
	const CRON_JOB_CREATOR_INTERVAL = \MINUTE_IN_SECONDS;

	/**
	 * Instance of Automattic\VIP\Search\Queue that created this Cron instance
	 */
	public $queue;

	/**
	 * Initialize the job class
	 *
	 * @access	public
	 */
	public function init() {
		// We always add this action so that the job can unregister itself if it no longer should be running
		add_action( self::CRON_PROCESSOR_EVENT_NAME, [ $this, 'process_jobs' ] );
		add_action( self::CRON_JOB_CREATOR_EVENT_NAME, [ $this, 'create_jobs' ] );

		if ( ! $this->is_enabled() ) {
			return;
		}

		// Add the custom cron schedule
		add_filter( 'cron_schedules', [ $this, 'filter_cron_schedules' ], 10, 1 );

		$this->schedule_job();
	}

	/**
	 * Schedule health check job
	 *
	 * Add the event name to WP cron schedule and then add the action
	 */
	public function schedule_job() {
		if ( ! wp_next_scheduled( self::CRON_JOB_CREATOR_EVENT_NAME )  ) {
			wp_schedule_event( time(), self::CRON_JOB_CREATOR_INTERVAL_NAME, self::CRON_JOB_CREATOR_EVENT_NAME );
		}
	}

	/**
	 * Disable health check job
	 *
	 * Remove the ES health check job from the events list
	 */
	public function disable_job() {
		if ( wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			wp_clear_scheduled_hook( self::CRON_EVENT_NAME );
		}
	}

	/**
	 * Filter `cron_schedules` output
	 *
	 * Add the custom interval to WP cron schedule
	 *
	 * @param		array	$schedule
	 *
	 * @return	mixed
	 */
	public function filter_cron_schedules( $schedule ) {
		if ( isset( $schedule[ self::CRON_JOB_CREATOR_INTERVAL_NAME ] ) ) {
			return $schedule;
		}

		$schedule[ self::CRON_JOB_CREATOR_INTERVAL_NAME ] = [
			'interval' => self::CRON_JOB_CREATOR_INTERVAL,
			'display' => __( 'VIP Search index queue job creator time interval' ),
		];

		return $schedule;
	}

	/**
	 * Process a batch of jobs
	 */
	public function process_jobs( $job_ids ) {
		$jobs = $this->queue->get_jobs( $job_ids );

		if ( ! count( $jobs ) ) {
			return;
		}

		$this->queue->process_batch_jobs( $jobs );
	}

	public function create_jobs() {
		// Check if job has been disabled
		if ( ! $this->is_enabled() ) {
			$this->disable_job();

			return;
		}

		// Find jobs to process
		$jobs = $this->queue->get_batch_jobs( self::CRON_PROCESSOR_MAX_OBJECTS_PER_JOB );

		if ( ! count( $jobs ) ) {
			return;
		}

		$job_ids = wp_list_pluck( $jobs, 'id' );

		wp_schedule_single_event( time(), self::CRON_PROCESSOR_EVENT_NAME, $job_ids );
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
