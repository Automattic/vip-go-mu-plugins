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
	 * Purge the queue
	 *
	 * ## OPTIONS
	 *
	 *[--skip-confirm]
	 * : Skip confirmation and purge the queue
	 *
	 * ## EXAMPLES
	 *     wp vip-search queue purge
	 *     wp vip-search queue purge --skip-confirm
	 *
	 * @subcommand purge
	 */

	public function purge( $args, $assoc_args ) {
		if ( ! isset( $assoc_args['skip-confirm'] ) ) {
			WP_CLI::confirm( 'Are you sure you want to truncate the existing indexing queue? Any items currently queued will be dropped' );
		}

		$search = \Automattic\VIP\Search\Search::instance();
		$queue  = $search->queue;

		$result = $queue->empty_queue();
		WP_CLI::success( sprintf( 'Total items removed from queue: %d', is_int( $result ) ? $result : 'error' ) );
	}
}
