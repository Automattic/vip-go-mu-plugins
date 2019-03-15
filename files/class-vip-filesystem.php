<?php

namespace Automattic\VIP\Files;

use WP_Error;

class VIP_Filesystem {

	/**
	 * The protocol defined for the  VIP Filesystem
	 */
	const PROTOCOL = 'vip';

	/**
	 * The name of the scheduled cron event to update attachment metadata
	 */
	const CRON_EVENT_NAME = 'vip_update_attachment_filesizes';

	/**
	 * Option name to mark all attachment filesize update completed
	 */
	const OPT_ALL_FILESIZE_PROCESSED = 'vip_all_attachment_filesize_processed';

	/**
	 * Option name to mark next index for starting the next batch of filesize updates
	 */
	const OPT_NEXT_FILESIZE_INDEX = 'vip_next_attachment_filesize_index';

	/**
	 * Option name for storing Max ID.
	 *
	 * We do not need to keep this updated as new attachments will already have file sizes
	 * included in their meta.
	 */
	const OPT_MAX_POST_ID = 'vip_attachment_max_post_id';

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The VIP Stream wrapper
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @var     VIP_Filesystem_Stream_Wrapper
	 */
	protected $stream_wrapper;

	/**
	 * Vip_Filesystem constructor.
	 */
	public function __construct() {
		if ( defined( 'VIP_FILESYSTEM_VERSION' ) ) {
			$this->version = VIP_FILESYSTEM_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'vip-filesystem';

		$this->load_dependencies();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since   1.0.0
	 * @access  private
	 */
	private function load_dependencies() {

		/**
		 * The class representing the VIP Files stream
		 */
		require_once __DIR__ . '/class-vip-filesystem-stream-wrapper.php';

		/**
		 * The class use to update attachment meta data
		 */
		require_once __DIR__ . '/class-meta-updater.php';
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->add_filters();

		// Create and register stream
		$this->stream_wrapper = new VIP_Filesystem_Stream_Wrapper( new_api_client(),
			self::PROTOCOL );
		$this->stream_wrapper->register();

		// Schedule meta update job
		$this->schedule_update_job();
	}

	/**
	 * Register all of the filters related to the functionality of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function add_filters() {

		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ], 10, 1 );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'filter_filetype_check' ], 10, 4 );
		add_filter( 'wp_delete_file', [ $this, 'filter_delete_file' ], 20, 1 );
		add_filter( 'get_attached_file', [ $this, 'filter_get_attached_file' ], 20, 2 );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'filter_wp_generate_attachment_metadata' ], 10, 2 );
		add_filter( 'cron_schedules', [ $this, 'filter_cron_schedules' ], 10, 1 );
	}

	/**
	 * Remove the registered filters.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function remove_filters() {

		remove_filter( 'upload_dir', [ $this, 'filter_upload_dir' ], 10 );
		remove_filter( 'wp_check_filetype_and_ext', [ $this, 'filter_filetype_check' ], 10 );
		remove_filter( 'wp_delete_file', [ $this, 'filter_delete_file' ], 20 );
		remove_filter( 'get_attached_file', [ $this, 'filter_get_attached_file' ], 20 );
		remove_filter( 'wp_generate_attachment_metadata', [ $this, 'filter_wp_generate_attachment_metadata' ] );
		remove_filter( 'cron_schedules', [ $this, 'filter_cron_schedules' ], 10 );
	}

	/**
	 * Filter the result of `wp_upload_dir` function
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Array of upload directory paths and URLs
	 *
	 * @return array Modified output of `wp_upload_dir`
	 */
	public function filter_upload_dir( $params ) {
		/**
		 * This is to account for the a8c-files plugin and should be temporary.
		 * Eventually, this plugin will replace a8c-files so this check can
		 * then be removed.
		 * - Hanif
		 */
		$pos = stripos( $params['path'], LOCAL_UPLOADS );
		if ( false !== $pos ) {
			$params['path']    = substr_replace( $params['path'],
				self::PROTOCOL . '://wp-content/uploads',
				$pos,
				strlen( LOCAL_UPLOADS ) );
			$params['basedir']    = substr_replace( $params['basedir'],
				self::PROTOCOL . '://wp-content/uploads',
				$pos,
				strlen( LOCAL_UPLOADS ) );
		} else {
			$pos = stripos( $params['path'], WP_CONTENT_DIR );
			$params['path']    = substr_replace( $params['path'],
				self::PROTOCOL . '://wp-content',
				$pos,
				strlen( WP_CONTENT_DIR ) );
			$params['basedir']    = substr_replace( $params['basedir'],
				self::PROTOCOL . '://wp-content',
				$pos,
				strlen( WP_CONTENT_DIR ) );
		}

		return $params;
	}

	/**
	 * Check filetype support against VIP Filesystem API
	 *
	 * Leverages VIP Filesystem API, which will return a 406 or other non-200 code if the filetype is unsupported
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   array   $filetype_data
	 * @param   string  $file
	 * @param   string  $filename
	 * @param   array   $mimes
	 *
	 * @return  array
	 */
	public function filter_filetype_check( $filetype_data, $file, $filename, $mimes ) {
		$filename = sanitize_file_name( $filename );

		// Setting `ext` and `type` to empty will fail the upload because Go doesn't allow unfiltered uploads
		// See `_wp_handle_upload()`
		if ( ! $this->check_filetype_with_backend( $filename ) ) {
			$filetype_data['ext']             = '';
			$filetype_data['type']            = '';
			// Never set this true, which leaves filename changing to dedicated methods in this class
			$filetype_data['proper_filename'] = false;
		}

		return $filetype_data;
	}

	/**
	 * Use the VIP Filesystem API to check for filename uniqueness
	 *
	 * The `unique_filename` API will return an error if file type is not supported
	 * by the VIP Filesystem.
	 *
	 * @since   1.0.0
	 * @access  protected
	 *
	 * @param   string      $filename
	 *
	 * @return  bool        True if filetype is supported. Else false
	 */
	protected function check_filetype_with_backend( $filename ) {
		$upload_path = $this->get_upload_path();

		$file_path = $upload_path . $filename;

		$result = $this->stream_wrapper->client->get_unique_filename( $file_path );

		if ( is_wp_error( $result ) ) {
			if ( 'invalid-file-type' !== $result->get_error_code() ) {
				trigger_error(
					sprintf( '%s #vip-go-streams', $result->get_error_message() ),
					E_USER_WARNING
				);
			}
			return false;
		}

		return true;
	}

	/**
	 * Return uploads path
	 *
	 * Different from wp_uploads_dir() as that will return the `vip://` protocol too
	 *
	 * @since   1.0.0
	 * @access  private
	 *
	 * @return  string The uploads path
	 */
	private function get_upload_path() {
		$upload_dir_path = wp_get_upload_dir()['path'];
		return substr( $upload_dir_path, strlen( self::PROTOCOL . '://' ) );
	}

	/**
	 * Deletes the file and purge the cache
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   string $file_path
	 *
	 * @return  string
	 */
	public function filter_delete_file( $file_path ) {
		// To ensure we don't needlessly fire off deletes for all sizes of the same image, of
		// which all except the first result in 404's, keep accounting of what we've deleted.
		static $deleted_uris = array();

		$file_path = $this->clean_file_path( $file_path );

		$file_uri  = $this->get_file_uri_path( $file_path );

		if ( in_array( $file_uri, $deleted_uris, true ) ) {
			// This file has already been successfully deleted from the file service in this request
			return '';
		}

		if ( ! unlink( $file_path ) ) {
			return '';
		}

		// Set our static so we can later recall that this file has already been deleted and purged
		$deleted_uris[] = $file_uri;

		// We successfully deleted the file, purge the file from the caches
		$this->purge_file_cache( $file_uri );

		// Return empty value so that `wp_delete_file()` won't try to `unlink` again
		return '';
	}

	/**
	 * Remove duplicate uploads base directory from file path
	 *
	 * Some of the intermediate file paths have the uploads `basedir` occur twice so we will need to
	 * check for that.
	 *
	 * @since   1.0.0
	 * @access  private
	 *
	 * @param   string  $file_path
	 *
	 * @return  string
	 */
	private function clean_file_path( $file_path ) {
		$upload_path = wp_get_upload_dir();

		// Find 2nd occurrence of `basedir`
		$pos = strpos( $file_path, $upload_path['basedir'], strlen( $upload_path['basedir'] ) );
		if ( false !== $pos ) {
			// +1 to account far trailing slash
			$file_path = substr( $file_path, strlen( $upload_path['basedir'] ) + 1 );
		}

		return $file_path;
	}

	/**
	 * Filters the generated attachment metadata
	 *
	 * @return array
	 */
	public function filter_wp_generate_attachment_metadata( $metadata, $attachment_id ) {
		// Append the filesize if not already set to avoid continued dynamic API calls.
		// The filesize doesn't change so it's okay to store it in meta.
		if ( ! isset( $metadata['filesize'] ) ) {
			$filesize = $this->get_filesize_from_file( $attachment_id );
			if ( false !== $filesize ) {
				$metadata['filesize'] = $filesize;
			}
		}

		return $metadata;
	}

	private function get_filesize_from_file( $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		if ( ! file_exists( $file ) ) {
			return false;
		}

		return filesize( $file );
	}

	/**
	 * Get the file path URI
	 *
	 * Strip query string and `vip` protocol to avoid attempting to delete the aforementioned image sizes
	 *
	 * @since   1.0.0
	 * @access  private
	 *
	 * @param   string      $file_path
	 *
	 * @return  string
	 */
	private function get_file_uri_path( $file_path ) {
		$url = wp_parse_url( $file_path );

		// Adding the leading slash because `wp_parse_url` reads the `wp-content` part
		// of the path as `host` without any slashes
		$file_uri = sprintf( '/%s%s', $url['host'], $url['path'] );

		return $file_uri;
	}

	/**
	 * Purge file from cache
	 *
	 * @since   1.0.0
	 * @access  private
	 *
	 * @param   string  $file_uri
	 */
	private function purge_file_cache( $file_uri ) {
		$invalidation_url = get_site_url() . $file_uri;

		if ( ! \WPCOM_VIP_Cache_Manager::instance()->queue_purge_url( $invalidation_url ) ) {
			trigger_error(
				sprintf( __( 'Error purging %s from the cache service #vip-go-streams' ), $invalidation_url ),
				E_USER_WARNING
			);
		}
	}

	/**
	 * Filter `get_attached_file` output
	 *
	 * Fixes incorrect attachment post meta data where `_wp_attached_file` is a
	 * URL instead of a file path relative to the uploads directory
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   string  $file           Path to file
	 * @param   int     $attachment_id  Attachment post ID
	 *
	 * @return  string  Path to file
	 */
	public function filter_get_attached_file( $file, $attachment_id ) {
		$uploads = wp_get_upload_dir();

		if ( $file && false !== strpos( $file, $uploads[ 'baseurl' ] ) ) {
			$file = str_replace( $uploads[ 'baseurl' ] . '/', '', $file );
		}

		return $file;
	}

	/**
	 * Filter `cron_schedules` output
	 *
	 * Add a custom schedule for a 5 minute interval
	 *
	 * @param   array   $schedule
	 *
	 * @return  mixed
	 */
	public function filter_cron_schedules( $schedule ) {
		if ( isset( $schedule[ 'vip_five_minutes' ] ) ) {
			return $schedule;
		}

		$schedule['vip_five_minutes'] = [
			'interval' => 300,
			'display' => __( 'Once every 5 minutes' ),
		];

		return $schedule;
	}

	public function schedule_update_job() {
		if ( get_option( self::OPT_ALL_FILESIZE_PROCESSED ) ) {
			if ( wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
				wp_clear_scheduled_hook( self::CRON_EVENT_NAME );
			}

			return;
		}

		if (! wp_next_scheduled ( self::CRON_EVENT_NAME )) {
			wp_schedule_event(time(), 'vip_five_minutes', self::CRON_EVENT_NAME );
		}

		add_action( self::CRON_EVENT_NAME, [ $this, 'update_attachment_meta' ] );
	}

	/**
	 * Cron job to update attachment metadata with file size
	 */
	public function update_attachment_meta() {
		trigger_error(
			sprintf( 'Starting %s... $vip-go-streams-debug', self::CRON_EVENT_NAME ),
			E_USER_NOTICE );
		if ( get_option( self::OPT_ALL_FILESIZE_PROCESSED ) ) {
			// already done. Nothing to update
			trigger_error(
				sprintf( 'Already completed. Exiting %s... $vip-go-streams-debug', self::CRON_EVENT_NAME ),
				E_USER_NOTICE );
			return;
		}

		$updater = new Meta_Updater( 1000 );

		$max_id = (int) get_option( self::OPT_MAX_POST_ID );
		if ( ! $max_id ) {
			$max_id = $updater->get_max_id();
			update_option( self::OPT_MAX_POST_ID, $max_id, false );
		}

		$start_index = get_option( self::OPT_NEXT_FILESIZE_INDEX, 0 );
		$end_index = $start_index + $updater->get_batch_size();

		do {
			if ( $start_index > $max_id ) {
				// This means all attachments have been processed so marking as done
				update_option( self::OPT_ALL_FILESIZE_PROCESSED, 1 );

				return;
			}

			$attachments = $updater->get_attachments( $start_index, $end_index );

			$start_index = $end_index + 1;
			$end_index = $start_index + $updater->get_batch_size();
		} while ( empty( $attachments ) );

		if ( $attachments ) {
			$updater->update_attachments( $attachments );
		}

		// All done, update next index option
		trigger_error(
			sprintf( 'Batch %d to %d completed. Updating options... $vip-go-streams-debug',
				$start_index, $end_index ),
			E_USER_NOTICE );
		update_option( self::OPT_NEXT_FILESIZE_INDEX, $end_index + 1, false );
	}
}
