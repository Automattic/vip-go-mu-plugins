<?php

namespace Automattic\VIP\Cron;

use Automattic\WP\Cron_Control;
use ActionScheduler_Store;
use WP_Error;

/*
 * Dynamic queue adjustments for autoscaling Action Scheduler.
 *
 * Every few minutes, this checks to see if the AS queue has exceeded a certain threshold
 * of "due now" actions. And if so, will schedule additional queues to run concurrently
 * in cron until the queue is caught up. Scales directly off of cron control's JOB_CONCURRENCY_LIMIT.
 *
 * 1) Cron jobs are registered when needed.
 * 2) Cron-control picks up each job when it can, triggering an AS queue to start.
 * 3) This new AS queue processes actions until it comes up on the timeout limit.
 * 4) The queue/job end. The cycle repeats itself until no additional queues are needed.
 */
class Action_Scheduler_Dynamic_Queue {
	const QUEUE_PROCESSOR_CRON_EVENT = 'vip_action_scheduler_run_queue';

	// Safety cap.
	const MAX_ALLOWED_DYNAMIC_QUEUES = 15;

	public function init() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Disable the async (frontend ajax) queue. We only want to run AS in cron.
		add_filter( 'action_scheduler_allow_async_request_runner', '__return_false', 30 );

		// Allow each queue to run for 120 seconds by default.
		add_filter( 'action_scheduler_queue_runner_time_limit', function() {
			return 120;
		}, 30 );

		// Configure concurrency.
		add_filter( 'a8c_cron_control_concurrent_event_whitelist', [ $this, 'configure_cron_control_concurrency' ] );
		add_filter( 'action_scheduler_queue_runner_concurrent_batches', [ $this, 'configure_action_scheduler_concurrency' ], 30 );

		add_action( self::QUEUE_PROCESSOR_CRON_EVENT, [ $this, 'process_queue' ] );

		if ( wp_doing_cron() || ( is_admin() && ! wp_doing_ajax() ) ) {
			add_action( 'shutdown', [ $this, 'maybe_dispatch_new_queues' ] );
		}
	}

	private function is_enabled() {
		$dependencies_exist = class_exists( 'ActionScheduler_Store' ) && class_exists( 'Automattic\WP\Cron_Control\Events_Store' );
		return apply_filters( 'vip_action_schedule_dynamic_queue_enabled', $dependencies_exist );
	}

	/**
	 * Increase allowed event concurrency in cron control.
	 * Note: This needs to happen before the after_setup_theme hook, priority 10.
	 */
	public function configure_cron_control_concurrency( $whitelist ) {
		$whitelist[ self::QUEUE_PROCESSOR_CRON_EVENT ] = $this->get_max_allowed_queue_jobs();
		return $whitelist;
	}

	/**
	 * Increase allowed batch concurrency in action scheduler.
	 */
	public function configure_action_scheduler_concurrency() {
		// Increment by 1 to account for the default `action_scheduler_run_queue` recurring action.
		return $this->get_max_allowed_queue_jobs() + 1;
	}

	private function get_max_allowed_queue_jobs() {
		$total_cron_control_concurrency_limit = defined( 'Automattic\WP\Cron_Control\JOB_CONCURRENCY_LIMIT' ) ? Cron_Control\JOB_CONCURRENCY_LIMIT : 10;

		// Allow up to 33% of the cron-control queue to be used for action scheduler processing.
		return (int) min( ceil( $total_cron_control_concurrency_limit / 3 ), self::MAX_ALLOWED_DYNAMIC_QUEUES );
	}

	private function get_queue_timeout_limit() {
		// Note: We set this to 120 seconds in a filter above.
		return absint( apply_filters( 'action_scheduler_queue_runner_time_limit', 30 ) );
	}

	/**
	 * Every few minutes, we determine if we should dispatch additional AS queues.
	 */
	public function maybe_dispatch_new_queues() {
		$dispatch_interval = max( $this->get_queue_timeout_limit() - 10, 60 );
		// @codingStandardsIgnoreLine - cache time expiration is variable, but safeguarded already.
		if ( ! wp_cache_add( 'dynamic-queue-scheduler-lock', 'locked', 'vip', $dispatch_interval ) ) {
			// Only dispatch new queues around the time it takes to finish the previous round, or once a minute minimum.
			return;
		}

		$pending_cron_jobs_count = $this->get_pending_queue_job_count();
		if ( is_wp_error( $pending_cron_jobs_count ) || $pending_cron_jobs_count >= $this->get_max_allowed_queue_jobs() ) {
			// If unsure of how many are currently scheduled, or if already at max, then avoid adding more jobs for this round.
			return;
		}

		$queues_to_dispatch = (int) $this->number_of_queues_to_dispatch( $pending_cron_jobs_count );
		if ( $queues_to_dispatch <= 0 ) {
			return;
		}

		$current_time = time();
		foreach ( range( 1, $queues_to_dispatch ) as $queue_id ) {
			$current_time += 5; // Stagger the queue starts, helps Action Scheduler avoid issues with the claims logic.

			// The differing timestamped arg allows us to register multiple of the same event.
			wp_schedule_single_event( $current_time, self::QUEUE_PROCESSOR_CRON_EVENT, [ $current_time ] );
		}
	}

	/**
	 * Get the number of queue jobs already scheduled.
	 * This is necessary, as the `cron` option is not guaranteed to contain all scheduled events.
	 */
	private function get_pending_queue_job_count() {
		global $wpdb;
		$table_name = Cron_Control\Events_Store::instance()->get_table_name();

		// Note: A job is marked as 'completed' as it begins to run, so we unfortunately are unable to tell directly if they are still running or completed.
		// @codingStandardsIgnoreLine - cannot prepare table name.
		$current_processor_job_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE action = %s and status = 'pending'", self::QUEUE_PROCESSOR_CRON_EVENT ) );

		if ( is_null( $current_processor_job_count ) ) {
			return new WP_Error( 'vip-action-scheduler-dynamic-queue', 'Could not find the current queue count.' );
		}

		return intval( $current_processor_job_count );
	}

	/**
	 * Determine how many queues to dispatch by calculating how many actions need processing.
	 */
	private function number_of_queues_to_dispatch( $pending_cron_jobs_count ) {
		// Extra future-proofing safety here since we can't control what version of AS is running on a site.
		$unclaimed_pending_actions_due = 0;
		if ( method_exists( 'ActionScheduler_Store', 'instance' ) ) {
			$store = ActionScheduler_Store::instance();

			if ( method_exists( $store, 'query_actions' ) && function_exists( 'as_get_datetime_object' ) ) {
				$unclaimed_pending_actions_due = (int) $store->query_actions( [
					'date'    => as_get_datetime_object(),
					'status'  => ActionScheduler_Store::STATUS_PENDING,
					'claimed' => false,
				], 'count' );
			}
		}

		// This is tough to determine. Depends on how many actions can be processed within the time limit, as a queue will keep grabbing new batches.
		// For now, we'll assume that the queue can do at least 2 full batches.
		// TODO: Look into using the action_scheduler_maximum_execution_time_likely_to_be_exceeded filter to track averages better.
		$average_actions_processed_per_queue = min( absint( apply_filters( 'action_scheduler_queue_runner_batch_size', 25 ) ) * 2, 100 );

		// Rely on the default recurring cron queue to handle some of the volume.
		$actions_needing_a_queue = $unclaimed_pending_actions_due - $average_actions_processed_per_queue;

		if ( $actions_needing_a_queue < 1 ) {
			return 0;
		}

		$number_of_extra_queues_needed  = max( ceil( $actions_needing_a_queue / $average_actions_processed_per_queue ) - $pending_cron_jobs_count, 0 );
		$number_of_extra_queues_allowed = max( $this->get_max_allowed_queue_jobs() - $pending_cron_jobs_count, 0 );
		return min( $number_of_extra_queues_needed, $number_of_extra_queues_allowed );
	}

	/**
	 * Cron callback, starts up an AS queue.
	 * Uses the same hook AS does for the core recurring event.
	 */
	public function process_queue() {
		// Pass it off to Action Scheduler's main recurring cron hook.
		$context = 'Dynamic Queue';
		do_action( 'action_scheduler_run_queue', $context );
	}
}
