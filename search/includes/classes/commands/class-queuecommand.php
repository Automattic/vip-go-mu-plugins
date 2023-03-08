<?php

namespace Automattic\VIP\Search\Commands;

use \WP_CLI;
use \WP_CLI\Utils;

use \Automattic\VIP\Search\Queue\Schema;

require_once __DIR__ . '/../class-health.php';

/**
 * Commands to view and manage the index queue
 *
 * @package Automattic\VIP\Search
 */
class QueueCommand extends \WPCOM_VIP_CLI_Command {
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
		$schema = new Schema();
		$result = $queue->empty_queue();

		// int means the query is successful and indicates the number of rows affected
		if ( is_int( $result ) ) {
			WP_CLI::success( sprintf( 'Total items removed from queue: %d', $result ) );
		} else {
			WP_CLI::error( 'Purge has failed, please inspect the ' . $schema->get_table_name() . ' table manually.' );
		}
	}
}
