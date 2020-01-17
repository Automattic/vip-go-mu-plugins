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
	 * @var array   Array of cached file stats
	 */
	private $file_stats = [];

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
		if ( empty( $this->files ) && empty( $this->file_stats ) ) {
			return;
		}

		foreach( $this->files as $name => $path ) {
			unlink( $path );
			unset( $this->files[ $name ] );
		}

		// empty file stats cache
		$this->file_stats = [];
	}

	public function get_file( $filepath ) {
		$file_name = basename( $filepath );

		if ( isset( $this->files[ $file_name ] ) ) {
			return $this->files[ $file_name ];
		}

		return false;
	}

	public function get_file_stats( $filepath ) {
		$file_name = basename( $filepath );

		if ( isset( $this->file_stats[ $file_name ] ) ) {
			return $this->file_stats[ $file_name ];
		}

		return false;
	}

	public function cache_file( $filepath, $local_file ) {
		$file_name = basename( $filepath );

		$this->files[ $file_name ] = $local_file;
	}

	public function cache_file_stats( $filepath, $info ) {
		$file_name = basename( $filepath );

		// This will overwrite existing stats if any
		$this->file_stats[ $file_name ] = $info;
	}

	public function copy_to_cache( $dst, $src ) {
		$file_name = basename( $dst );

		if ( ! isset( $this->files[ $file_name ] ) ) {
			// create file with unique filename
			$tmp_file = $this->create_tmp_file();

			$this->files[ $file_name ] = $tmp_file;
		}

		// This will overwrite existing file if any
		copy( $src, $this->files[ $file_name ] );
	}

	public function remove_file( $filepath ) {
		$file_name = basename( $filepath );

		if ( isset( $this->files[ $file_name ] ) ) {
			unlink( $this->files[ $file_name ] );
			unset( $this->files[ $file_name ] );
		}

		// Remove cached stats too if any
		unset( $this->file_stats[ $file_name ] );
	}

	public function remove_stats( $filepath ) {
		$file_name = basename( $filepath );

		// Remove cached stats if any
		unset( $this->file_stats[ $file_name ] );
	}

	public function create_tmp_file() {
		return tempnam( $this->tmp_dir, 'vip' );
	}
}
