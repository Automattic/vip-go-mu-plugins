<?php

namespace Automattic\VIP\Files;

class Meta_Updater {

	const DEFAULT_BATCH_SIZE = 1000;

	/**
	 * @var bool
	 */
	protected $dry_run;

	/**
	 * @var int
	 */
	protected $batch_size;

	/**
	 * @var int
	 */
	protected $count;

	/**
	 * @var int
	 */
	protected $max_id;

	/**
	 * @var resource
	 */
	protected $log_file;

	/**
	 * Meta_Updater constructor.
	 *
	 * @param int $batch_size
	 */
	public function __construct( int $batch_size = 0, string $log_file = null ) {
		if ( 0 >= $batch_size ) {
			$batch_size = self::DEFAULT_BATCH_SIZE;
		}
		$this->batch_size = $batch_size;

		if ( $log_file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
			$this->log_file = fopen( $log_file, 'w' );
		}

		$this->count = array_sum( (array) wp_count_posts( 'attachment' ) );
	}

	/**
	 * @return int
	 */
	public function get_batch_size(): int {
		return $this->batch_size;
	}

	/**
	 * @param int $batch_size
	 */
	public function set_batch_size( int $batch_size ): void {
		$this->batch_size = $batch_size;
	}

	/**
	 * @return int
	 */
	public function get_count(): int {
		return $this->count;
	}

	/**
	 * Get max possible post ID
	 *
	 * @return int
	 */
	public function get_max_id(): int {
		if ( $this->max_id ) {
			return $this->max_id;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->max_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} ORDER BY ID DESC LIMIT 1" );

		return $this->max_id;
	}

	/**
	 * Get all attachments post
	 *
	 * @param int $start_index
	 * @param int $end_index
	 *
	 * @return array
	 */
	public function get_attachments( int $start_index = 0, int $end_index = 0 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachments = $wpdb->get_col(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID BETWEEN %d AND %d", $start_index, $end_index )
		);

		// Only return attachments without filesize
		$filtered = [];
		foreach ( $attachments as $attachment_id ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( ( is_array( $meta ) && ! isset( $meta['filesize'] ) ) || '' === $meta ) {
				$filtered[] = $attachment_id;
			}
		}

		return $filtered;
	}

	/**
	 * Update attachments' metadata
	 *
	 * @param array $attachments
	 * @param bool $dry_run
	 */
	public function update_attachments( array $attachments, bool $dry_run = false ): array {
		$this->dry_run = $dry_run;

		$counts = [];

		foreach ( $attachments as $attachment_id ) {
			list( $result, $details ) = $this->update_attachment_filesize( $attachment_id );

			if ( ! isset( $counts[ $result ] ) ) {
				$counts[ $result ] = 0;
			}
			$counts[ $result ]++;

			if ( $this->log_file ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
				fputcsv( $this->log_file, [
					gmdate( 'c' ),
					$attachment_id,
					$result,
					$details,
				] );
			}
		}

		return $counts;
	}

	/**
	 * Update attachment's filesize metadata
	 *
	 * @param int $attachment_id
	 *
	 * @return array
	 */
	private function update_attachment_filesize( $attachment_id ): array {
		$meta = wp_get_attachment_metadata( $attachment_id );

		// If the meta doesn't exist at all, it's worth still storing the filesize
		if ( empty( $meta ) ) {
			$meta = [];
		}

		if ( ! is_array( $meta ) ) {
			return [ 'skip-invalid-metadata', 'does not have valid metadata' ];
		}

		if ( isset( $meta['filesize'] ) ) {
			return [ 'skip-has-filesize', 'already has filesize' ];
		}

		$filesize = $this->get_filesize_from_file( $attachment_id );

		if ( 0 >= $filesize ) {
			return [ 'fail-get-filesize', 'failed to get filesize' ];
		}

		$meta['filesize'] = $filesize;

		if ( $this->dry_run ) {
			return [ 'skip-dry-run', 'dry-run; would have updated filesize to ' . $filesize ];
		}

		wp_update_attachment_metadata( $attachment_id, $meta );
		return [ 'updated', 'updated filesize to ' . $filesize ];
	}

	/**
	 * Get file size from attachment ID
	 *
	 * @param int $attachment_id
	 *
	 * @return int
	 */
	private function get_filesize_from_file( $attachment_id ) {
		$attachment_url = wp_get_attachment_url( $attachment_id );

		$response = wp_remote_head( $attachment_url );
		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( '%s: failed to HEAD attachment %s because %s', __METHOD__, esc_html( $attachment_url ), esc_html( $response->get_error_message() ) ), E_USER_WARNING );
			return 0;
		}

		return (int) wp_remote_retrieve_header( $response, 'Content-Length' );
	}

	/**
	 * Clean up after updates
	 */
	public function finish_update() {
		if ( $this->log_file ) {
			fclose( $this->log_file );
		}
	}
}
