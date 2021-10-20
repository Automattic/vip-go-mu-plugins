<?php

namespace Automattic\VIP\Search\Commands;

use \WP_CLI;
use \WP_CLI\Utils;

require_once __DIR__ . '/../class-health.php';

/**
 * Commands to view and manage the index queue
 *
 * @package Automattic\VIP\Search
 */
class QueueCommand extends \WPCOM_VIP_CLI_Command {
	private const SUCCESS_ICON = "\u{2705}"; // unicode check mark
	private const FAILURE_ICON = "\u{274C}"; // unicode cross mark

	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		parent::__construct();
	}

	/**
	 * Run repeated re-indexing of many posts and record/report on 
	 * how many re-index operations occur
	 *
	 * ## OPTIONS
	 * 
	 *[--truncate]
	 * : Should we truncate the existing queue before starting?
	 * ---
	 *
	 * ## EXAMPLES
	 *     wp vip-search queue stress-test
	 *
	 * @subcommand stress-test
	 */
	public function stress_test( $args, $assoc_args ) {
		// TODO limit to only some sites

		$search = \Automattic\VIP\Search\Search::instance();
		$queue  = $search->queue;

		$object_type = 'post';

		// Is the async queue enabled?
		if ( ! $queue->is_enabled() ) {
			WP_CLI::error( 'Async indexing is not enabled, aborting' );

			exit();
		}

		WP_CLI::confirm( 'This command queues hundreds of posts for indexing as a stress test and is not recommended to be run in production. Continue?' );

		if ( $assoc_args['truncate'] ) {
			WP_CLI::confirm( 'Are you sure you want to truncate the existing indexing queue? Any items currently queued will be dropped' );

			$queue->empty_queue();
		}

		$queue->offload_indexing_to_queue();

		$starting_queued_count         = $queue->count_jobs( 'queued', $object_type );
		$starting_queued_count_due_now = $queue->count_jobs( 'queued', $object_type );

		WP_CLI::line( sprintf( 'Async queue currently contains %d queued jobs, with %d due now', $starting_queued_count, $starting_queued_count_due_now ) );

		$batch_size = 100;
	
		// Get a bunch of posts
		$q = new \WP_Query( array(
			'posts_per_page' => $batch_size,
			'post_type'      => 'post',
			'post_status'    => 'publish', // Keep it simple
		) );

		$indexable = \ElasticPress\Indexables::factory()->get( $object_type );

		WP_CLI::line( sprintf( 'Queuing %d objects via ElasticPress', count( $q->posts ) ) );

		// Queue up batch of posts
		foreach ( $q->posts as $post ) {
			$indexable->sync_manager->add_to_queue( $post->ID );
		}

		// Now process the items in the EP queue. If async indexing is enabled, this will
		// send the posts to the async queue and bail early
		WP_CLI::line( 'Triggering indexing of ElasticPress queue (normally happens on shutdown)' );

		$indexable->sync_manager->index_sync_queue();

		$current_queued_count = $queue->count_jobs( 'queued', $object_type );

		WP_CLI::line( sprintf( 'EP queue processed, now there are %d queued async jobs', $current_queued_count ) );

		WP_CLI::line( 'Processing a batch of queued async jobs' );

		$jobs = $queue->checkout_jobs( $batch_size );

		$queue->process_jobs( $jobs );

		WP_CLI::line( sprintf( 'Processed %d jobs from the index', $batch_size ) );

		// Queue up same posts again
		$requeue_times = 5;

		for ( $i = 0; $i < $requeue_times; $i++ ) {
			WP_CLI::line( sprintf( 'Requeuing same %d objects for re-indexing', count( $q->posts ) ) );

			foreach ( $q->posts as $post ) {
				$indexable->sync_manager->add_to_queue( $post->ID );
			}

			WP_CLI::line( 'Triggering indexing of ElasticPress queue (normally happens on shutdown)' );

			$indexable->sync_manager->index_sync_queue();
		}

		$after_requeue_queued_count         = $queue->count_jobs( 'scheduled', $object_type );
		$after_requeue_queued_due_now_count = $queue->count_jobs_due_now( $object_type );

		$total_times_queued = count( $q->posts ) * ( $requeue_times + 1 ); // +1 b/c of the initial batch we queued before retrying
		
		WP_CLI::line( '-------------' );

		WP_CLI::line( sprintf( 'After 1 initial queue, processing those jobs, then requeuing the same posts an additional %d times, there are %d async jobs queued, with %d due now', $requeue_times, $after_requeue_queued_count, $after_requeue_queued_due_now_count ) );
		WP_CLI::line( sprintf( 'Without index debouncing and rate limiting, would expect %d index operations', $total_times_queued ) );

		// Find next index timestamp for the first object, to show when it can be next re-indexed
		$next_job_for_first_post = $queue->get_next_job_for_object( $q->posts[0]->ID, 'post' );

		$next_job_start_time = strtotime( $next_job_for_first_post->start_time );

		$next_job_start_time_diff = $next_job_start_time - time();

		WP_CLI::line( sprintf( 'The first post in the batch is scheduled to be re-indexed at %s, %d seconds from now', $next_job_for_first_post->start_time, $next_job_start_time_diff ) );
	}
}
