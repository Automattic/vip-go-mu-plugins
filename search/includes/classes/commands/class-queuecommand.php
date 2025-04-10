<?php

namespace Automattic\VIP\Search\Commands;

use WP_CLI;

use Automattic\VIP\Search\Queue\Schema;

require_once __DIR__ . '/../../../../vip-helpers/vip-wp-cli.php';
require_once __DIR__ . '/../class-health.php';

/**
 * Commands to view and manage the index queue
 *
 * @package Automattic\VIP\Search
 */
class QueueCommand extends \WPCOM_VIP_CLI_Command {
	/**
	 * Get info on the queue
	 *
	 * ## OPTIONS
	 *
	 *[--format=<format>]
	 * : Accepts 'table', 'json', 'csv', or 'yaml'. Default: table
	 *
	 * ## EXAMPLES
	 *     wp vip-search queue info
	 *     wp vip-search queue info --format=json
	 *
	 * @subcommand info
	 */
	public function info( $args, $assoc_args ) {
		$format = $assoc_args['format'] ?? 'table';
		if ( ! in_array( $format, [ 'table', 'json', 'csv', 'yaml' ], true ) ) {
			WP_CLI::error( __( '--format only accepts the following values: table, json, csv, yaml' ) );
		}

		$search = \Automattic\VIP\Search\Search::instance();
		$stats  = $search->queue->get_queue_stats();
		$info   = [
			[
				'queue_count'       => number_format_i18n( $stats->queue_count ),
				'average_wait_time' => $stats->average_wait_time > 0 ? human_readable_duration( gmdate( 'H:i:s', $stats->average_wait_time ) ) : $stats->average_wait_time,
				'longest_wait_time' => $stats->longest_wait_time > 0 ? human_readable_duration( gmdate( 'H:i:s', $stats->longest_wait_time ) ) : $stats->longest_wait_time,
			],
		];

		WP_CLI\Utils\format_items( $format, $info, [ 'queue_count', 'average_wait_time', 'longest_wait_time' ] );
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
