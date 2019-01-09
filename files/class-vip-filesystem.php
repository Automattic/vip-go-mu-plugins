<?php

namespace Automattic\VIP\Files;

use WP_Error;

class VIP_Filesystem {

	/**
	 * The protocol defined for the  VIP Filesystem
	 */
	const PROTOCOL = 'vip';

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
	}

	/**
	 * Register all of the filters related to the functionality of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function add_filters() {

		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ], 10, 1 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'filter_filetype_check' ), 10, 4 );
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
	 * @access  private
	 *
	 * @param   string      $filename
	 *
	 * @return  bool        True if filetype is supported. Else false
	 */
	private function check_filetype_with_backend( $filename ) {
		$upload_path = $this->get_upload_path();

		$file_path = $upload_path . $filename;

		$result = $this->stream_wrapper->client->get_unique_filename( $file_path );

		if ( is_wp_error( $result ) ) {
			if ( 'invalid-file-type' !== $result->get_error_code() ) {
				trigger_error( $result->get_error_message(), E_USER_WARNING );
			}
			return false;
		}

		return true;
	}

	private function get_upload_path() {
		$upload_dir_path = wp_get_upload_dir()['path'];
		return ltrim( $upload_dir_path. self::PROTOCOL . '://' );
	}
}
