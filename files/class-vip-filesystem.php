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
//		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ], 20, 1 );
//		add_filter( 'wp_handle_upload_prefilter', [ $this, 'prefilter_move_tmp_file' ], 10, 1 );
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

	public function prefilter_move_tmp_file( $file ) {
		$upload_dir = wp_upload_dir();
		$new_path   = $upload_dir['basedir'] . '/tmp/' . basename( $file['tmp_name'] );

		copy( $file['tmp_name'], $new_path );
		unlink( $file['tmp_name'] );
		$file['tmp_name'] = $new_path;

		return $file;
	}
}
