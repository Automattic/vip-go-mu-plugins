<?php

namespace Automattic\VIP\CLI;

use \WP_CLI;

/**
 * Helper command to manage VIP Go Filesystem
 *
 * @package Automattic\VIP\Files
 */
class VIP_Files_CLI_Command extends \WPCOM_VIP_CLI_Command {

	private $dry_run = true;

	private $log_file;

	/**
	 * Update file attachment metadata
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run=<dry-run>]
	 * : Wether or not to update to database, or simply inspect it.
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
		global $wpdb;

		if ( ! defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) || true !== VIP_FILESYSTEM_USE_STREAM_WRAPPER ) {
			WP_CLI::error( 'This script only works when the VIP Stream Wrapper is enabled. Please add `define( \'VIP_FILESYSTEM_USE_STREAM_WRAPPER\', true );` to vip-config.php and try again.' );
			return;
		}

		WP_CLI::line( 'Updating attachment filesize metadata...' );

		$log_file_name = sprintf( '%svip-files-update-filesizes-%s%s.csv', get_temp_dir(), $this->dry_run ? 'dry-run-' : '', date( 'YmdHi' ) );
		$this->log_file = fopen( $log_file_name, 'w' );

		// Parse arguments
		$_dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', true );
		if ( 'false' === $_dry_run ) {
			$this->dry_run = false;
		}

		$batch_size = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'batch', 1000 );
		if ( 0 >= $batch_size ) {
			WP_CLI::error( 'Invalid batch size: ' . $batch_size );
			WP_CLI::halt( 1 );
		}

		WP_CLI::line( '' );
		WP_CLI::line( 'ARGUMENTS' );
		WP_CLI::line( '* dry run: ' . ( $this->dry_run ? 'yes' : 'no' ) );
		WP_CLI::line( '* batch size: ' . $batch_size );
		WP_CLI::line( '* log file: ' . $log_file_name );
		WP_CLI::line( '' );

		$attachment_count = array_sum( (array) wp_count_posts( 'attachment' ) );
		if ( 0 >= $attachment_count ) {
			WP_CLI::error( 'No attachments found' );
			WP_CLI::halt( 1 );
		}

		WP_CLI::confirm( sprintf( 'Should we start processing %s attachments?', number_format( $attachment_count ) ), $assoc_args );

		$max_id = $wpdb->get_var( 'SELECT ID FROM ' . $wpdb->posts . ' ORDER BY ID DESC LIMIT 1' );

		$start_index = 0;
		$end_index = $start_index + $batch_size;

		do {
			WP_CLI::line( sprintf( 'Processing IDs %s => %s (MAX ID: %s)', number_format( $start_index ), number_format( $end_index ), number_format( $max_id ) ) );

			$sql = $wpdb->prepare( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = "attachment" AND ID BETWEEN %d AND %d',
				$start_index, $end_index );
			$attachments = $wpdb->get_results( $sql );

			WP_CLI::line( sprintf( '-- found %s attachments', number_format( count( $attachments ) ) ) );

			if ( $attachments ) {
				$this->update_attachments( $attachments );
			}

			// Pause.
			$this->stop_the_insanity();
			sleep( 1 );

			$start_index = $end_index + 1;
			$end_index = $start_index + $batch_size;

		} while ( $start_index <= $max_id );

		fclose( $this->log_file );

		WP_CLI::success( 'Attachments metadata update complete!' );
		WP_CLI::success( 'Log file can be found at: ' . $log_file_name );
	}

	/**
	 * Update attachments' metadata
	 *
	 * @param array $attachments
	 */
	private function update_attachments( array $attachments ): void {
		foreach ( $attachments as $attachment ) {
			list( $did_update, $result ) = $this->update_attachment_filesize( $attachment->ID );

			fputcsv( $this->log_file, [
				$attachment->ID,
				$did_update ? 'updated' : 'skipped',
				$result,
			] );
		}
	}

	/**
	 * Update attachment's filesize metadata
	 *
	 * @param int $attachment_id
	 */
	private function update_attachment_filesize( $attachment_id ): array {
		$meta = wp_get_attachment_metadata( $attachment_id );

		// If the meta doesn't exist at all, it's worth still storing the filesize
		if ( empty( $meta ) ) {
			$meta = [];
		}

		if ( ! is_array( $meta ) ) {
			return [ false, 'does not have valid metadata' ];
		}

		if ( isset( $meta['filesize'] ) ) {
			return [ false, 'already has filesize' ];
		}

		$filesize = $this->get_filesize_from_file( $attachment_id );

		if ( 0 >= $filesize ) {
			return [ false, 'failed to get filesize' ];
		}

		$meta['filesize'] = $filesize;

		if ( $this->dry_run ) {
			return [ false, 'dry-run; would have updated filesize to ' . $filesize ];
		}

		wp_update_attachment_metadata( $attachment_id, $meta );
		return [ true, 'updated filesize to ' . $filesize ];
	}

	/**
	 * Get file size from attachment ID
	 *
	 * @param int $attachment_id
	 *
	 * @return int
	 */
	private function get_filesize_from_file( $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		if ( ! file_exists( $file ) ) {
			return 0;
		}

		return filesize( $file );
	}
}

WP_CLI::add_command( 'vip files', __NAMESPACE__ . '\VIP_Files_CLI_Command' );
