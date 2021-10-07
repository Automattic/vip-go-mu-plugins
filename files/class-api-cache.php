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

		foreach ( $this->files as $name => $path ) {
			unlink( $path );                // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
			unset( $this->files[ $name ] );
		}

		// empty file stats cache
		$this->file_stats = [];
	}

	public function get_file( $filepath ) {
		if ( isset( $this->files[ $filepath ] ) ) {
			return $this->files[ $filepath ];
		}

		return false;
	}

	public function get_file_stats( $filepath ) {
		if ( isset( $this->file_stats[ $filepath ] ) ) {
			return $this->file_stats[ $filepath ];
		}

		return false;
	}

	public function cache_file( $filepath, $local_file ) {
		$this->files[ $filepath ] = $local_file;
	}

	public function cache_file_stats( $filepath, $info ) {
		// This will overwrite existing stats if any
		$this->file_stats[ $filepath ] = $info;
	}

	public function copy_to_cache( $dst, $src ) {
		if ( ! isset( $this->files[ $dst ] ) ) {
			// create file with unique filename
			$tmp_file = $this->create_tmp_file();

			$this->files[ $dst ] = $tmp_file;
		}

		// This will overwrite existing file if any
		copy( $src, $this->files[ $dst ] );
	}

	public function remove_file( $filepath ) {
		if ( isset( $this->files[ $filepath ] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
			unlink( $this->files[ $filepath ] );
			unset( $this->files[ $filepath ] );
		}

		// Remove cached stats too if any
		unset( $this->file_stats[ $filepath ] );
	}

	public function remove_stats( $filepath ) {
		// Remove cached stats if any
		unset( $this->file_stats[ $filepath ] );
	}

	public function create_tmp_file() {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam
		return tempnam( $this->tmp_dir, 'vip' );
	}
}
