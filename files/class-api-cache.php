<?php

namespace Automattic\VIP\Files;

use WP_Error;


class API_Cache {

	/**
	 * @var API_Client  Holds the class instance
	 */
	private static $instance = null;

	/**
	 * @var array   Array of created local cache files
	 */
	private $files = [];

	/**
	 * @var string  Temp directory to cache file in
	 */
	private $tmp_dir = '/tmp';

	/**
	 * API_Cache constructor.
	 */
	protected function __construct() {
		$this->tmp_dir = get_temp_dir();

		add_action( 'shutdown', [ $this, 'clear_tmp_files' ] );
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function clear_tmp_files() {
		if ( empty( $this->files ) ) {
			return;
		}

		foreach( $this->files as $name => $path ) {
			unlink( $path );
			unset( $this->files[ $name ] );
		}
	}

	public function get_file( $filepath ) {
		$file_name = basename( $filepath );

		if ( isset( $this->files[ $file_name ] ) ) {
			return file_get_contents( $this->files[ $file_name ] );
		}

		return false;
	}

	public function cache_file( $filepath, $data ) {
		$file_name = basename( $filepath );

		if ( ! isset( $this->files[ $file_name ])) {
			// create file with unique filename
			$tmp_file = tempnam( $this->tmp_dir, 'vip' );

			$this->files[ $file_name ] = $tmp_file;
		}

		// This will overwrite existing file if any
		file_put_contents( $this->files[ $file_name ], $data );
	}

	public function remove_file( $filepath ) {
		$file_name = basename( $filepath );

		if ( isset( $this->files[ $file_name ] ) ) {
			unlink( $this->files[ $file_name ] );
			unset( $this->files[ $file_name ] );
		}
	}
}
