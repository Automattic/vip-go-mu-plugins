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
	 * Validate DB and ES index counts for all objects
	 *
	 * ## OPTIONS
	 *
	 *
	 * ## EXAMPLES
	 *     wp vip-search queue stress-test
	 *
	 * @subcommand stress-test
	 */
	public function stress_test( $args, $assoc_args ) {
		WP_CLI::confirm( 'This command queues up thousands of posts and is not recommended to be run in production. Continue?' );

		$batch_size = 500;

		// Get a bunch of posts
		$q = new WP_Query( array(
			'posts_per_page' => $batch_size,
			'post_type' => 'post',
			'post_status' => 'publish',
		) );

		// Start a timer

		// Start a counter for how many index operations happened

		// Queue up batch of posts

		// Mark some as running to simulate ongoing processing

		// Queue up same posts again

		// Report on queue size (vs total found posts)

		// Report on how many re-index jobs "happened"
	}
}
