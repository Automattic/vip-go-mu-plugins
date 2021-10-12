<?php

namespace Automattic\VIP\CLI;

use \WP_CLI;
use Automattic\VIP\Files\Meta_Updater;

/**
 * Helper command to manage VIP Go Filesystem
 *
 * @package Automattic\VIP\Files
 */
class VIP_Files_CLI_Command extends \WPCOM_VIP_CLI_Command {

	private $dry_run = true;

	/**
	 * @var Meta_Updater
	 */
	private $meta_updater;

	/**
	 * Update file attachment metadata
	 *
	 * ## OPTIONS
	 *
	 * [--start-index=<start-index>]
	 * : Which ID to start from
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--dry-run=<dry-run>]
	 * : Whether or not to update to database, or simply inspect it.
	 * ---
	 * default: false
	 * options:
	 *   - true
	 *   - false
	 * ---
	 *
	 * [--batch=<batch>]
	 * : Batch size to process attachments in.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * [--yes]
	 * : Skip confirmation step
	 * ---
	 *
	 * ## EXAMPLES
	 *     wp vip files update-filesizes
	 *
	 * @subcommand update-filesizes
	 * @sypnosis [--dry-run=<dry-run>] [--batch=<batch>]
	 */
	public function update_filesizes( $args, $assoc_args ) {
		if ( ! defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) || true !== VIP_FILESYSTEM_USE_STREAM_WRAPPER ) {
			WP_CLI::error( 'This script only works when the VIP Stream Wrapper is enabled. Please add `define( \'VIP_FILESYSTEM_USE_STREAM_WRAPPER\', true );` to vip-config.php and try again.' );
			return;
		}

		WP_CLI::line( 'Updating attachment filesize metadata...' );

		// Parse arguments
		$_dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', true );
		if ( 'false' === $_dry_run ) {
			$this->dry_run = false;
		}

		$start_index = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'start-index', 0 );
		if ( 0 > $start_index ) {
			WP_CLI::error( 'Invalid start index: ' . $start_index );
			WP_CLI::halt( 1 );
		}

		$batch_size = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'batch', 1000 );
		if ( 0 >= $batch_size ) {
			WP_CLI::error( 'Invalid batch size: ' . $batch_size );
			WP_CLI::halt( 1 );
		}

		$log_file_name = sprintf( '%svip-files-update-filesizes-%s%s.csv', get_temp_dir(),
		$this->dry_run ? 'dry-run-' : '', gmdate( 'YmdHi' ) );

		WP_CLI::line( '' );
		WP_CLI::line( 'ARGUMENTS' );
		WP_CLI::line( '* dry run: ' . ( $this->dry_run ? 'yes' : 'no' ) );
		WP_CLI::line( '* start index: ' . $start_index );
		WP_CLI::line( '* batch size: ' . $batch_size );
		WP_CLI::line( '* log file: ' . $log_file_name );
		WP_CLI::line( '' );

		$this->meta_updater = new Meta_Updater( $batch_size, $log_file_name );

		$attachment_count = $this->meta_updater->get_count();

		WP_CLI::confirm( sprintf( 'Should we start processing %s attachments?', number_format( $attachment_count ) ), $assoc_args );

		$max_id = $this->meta_updater->get_max_id();

		$end_index = $start_index + $batch_size;

		do {
			WP_CLI::line( sprintf( 'Processing IDs %s => %s (MAX ID: %s)', number_format( $start_index ), number_format( $end_index ), number_format( $max_id ) ) );

			$attachments = $this->meta_updater->get_attachments( $start_index, $end_index );

			WP_CLI::line( sprintf( '-- found %s attachments without a filesize', number_format( count( $attachments ) ) ) );

			if ( $attachments ) {
				$counts = $this->meta_updater->update_attachments( $attachments, $this->dry_run );
				WP_CLI::line( sprintf( '-- results: %s', wp_json_encode( $counts ) ) );
			}

			// Pause.
			$this->stop_the_insanity();
			sleep( 1 );

			$start_index = $end_index + 1;
			$end_index   = $start_index + $batch_size;

		} while ( $start_index <= $max_id );

		$this->meta_updater->finish_update();

		WP_CLI::success( 'Attachments metadata update complete!' );
		WP_CLI::success( 'Log file can be found at: ' . $log_file_name );
	}
}

WP_CLI::add_command( 'vip files', __NAMESPACE__ . '\VIP_Files_CLI_Command' );
