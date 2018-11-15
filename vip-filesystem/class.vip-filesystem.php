<?php
/**
 * Created by PhpStorm.
 * User: hanifn
 * Date: 31/10/18
 * Time: 11:40 PM
 */

namespace Automattic\VIP\Filesystem;

use function Automattic\VIP\Files\new_api_client;

class Vip_Filesystem {

	/**
	 * The protocol defined for the  VIP Filesystem
	 */
	const PROTOCOL = 'vip';

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Vip_Filesystem_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

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
	 * @var     Vip_Filesystem_Stream
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
		$this->define_filters();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since   1.0.0
	 * @access  private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once __DIR__ . '/class.vip-filesystem-loader.php';

		/**
		 * The class representing the VIP Files stream
		 */
		require_once __DIR__ . '/class.vip-filesystem-stream.php';

		$this->loader = new Vip_Filesystem_Loader();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		// Loads all hooks
		$this->loader->run();

		// Create and register stream
		$this->stream_wrapper = new Vip_Filesystem_Stream( new_api_client(),
			self::PROTOCOL );
		$this->stream_wrapper->register();
	}

	/**
	 * Register all of the filters related to the functionality of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_filters() {
		$this->loader->add_filter( 'upload_dir',
			$this, 'filter_upload_dir' );
//		$this->loader->add_filter( 'wp_delete_file',
//			$this, 'filter_delete_file' );
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
		$params['path']    = str_replace(
			WP_CONTENT_DIR, self::PROTOCOL . '://wp-content', $params['path'] );
		$params['basedir'] = str_replace(
			WP_CONTENT_DIR, self::PROTOCOL . '://wp-content', $params['basedir'] );
		print_r( $params );

		return $params;
	}
}