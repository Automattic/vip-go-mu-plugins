<?php

namespace Automattic\VIP\Files;

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
	 * Check filetype support against Mogile
	 *
	 * Leverages Mogile backend, which will return a 406 or other non-200 code if the filetype is unsupported
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

		$check = $this->check_uniqueness_with_backend( $filename );

		// Setting `ext` and `type` to empty will fail the upload because Go doesn't allow unfiltered uploads
		// See `_wp_handle_upload()`
		if ( 200 != $check['http_code'] ) {
			$filetype_data['ext']             = '';
			$filetype_data['type']            = '';
			// Never set this true, which leaves filename changing to dedicated methods in this class
			$filetype_data['proper_filename'] = false;
		}

		return $filetype_data;
	}

	private function check_uniqueness_with_backend( $filename ) {
		if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) ) {
			$file['error'] = $uploads['error'];
			return $file;
		}

		$url_parts = wp_parse_url( $uploads['url'] . '/' . $filename );
		$file_path = $url_parts['path'];
		if ( is_multisite() ) {
			$sanitized_file_path = Path_Utils::trim_leading_multisite_directory( $file_path, $this->get_upload_path() );
			if ( false !== $sanitized_file_path ) {
				$file_path = $sanitized_file_path;
				unset( $sanitized_file_path );
			}
		}

		$result = $this->stream_wrapper->client->get_file( $file_path );
	}
}
