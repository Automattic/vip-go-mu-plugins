<?php

namespace Automattic\VIP\Files;

if ( ! class_exists( '\WP_Filesystem_Base' ) ) {
	require( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
}

if ( ! class_exists( '\WP_Filesystem_Direct' ) ) {
	require( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
}

require_once( __DIR__ . '/class-wp-filesystem-uploads.php' );
require_once( __DIR__ . '/class-api-client.php' );

use WP_Error;
use WP_Filesystem_Base;
use WP_Filesystem_Direct;

class WP_Filesystem_VIP extends WP_Filesystem_Base {

	private $api;
	private $direct;

	public function __construct() {
		$this->method = 'vip';
		$this->errors = new WP_Error();

		$this->api = new WP_Filesystem_Uploads();
		$this->direct = new WP_Filesystem_Direct( null );
	}

	private function get_transport_for_path( $filename ) {
		if ( $this->is_uploads_path( $filename ) ) {
			return $this->api;
		} elseif ( $this->is_tmp_path( $filename ) ) {
			return $this->direct;
		}

		// This is the usual way to do errors, we'll use it but also trigger a PHP E_USER_ERROR to ensure users see this.
		$this->errors->add( 'filepath_not_supported', 'No appropriate transport found for filename: ' . $filename );

		// TODO: Do we want to just trigger_error in some circumstances? maybe only when environement != production?
		trigger_error( 'Files can only be modified either in the temporary folder or in the uploads folder. Please see our documentation here:', E_USER_ERROR );
	}

	private function is_tmp_path( $filename ) {
		// TODO: Should we check against sys_get_temp_dir()?
		if ( strpos( $filename, '/tmp' ) ) {
			return true;
		}
		return false;
	}

	private function is_uploads_path( $filename ) {
		$upload_path = trim( get_option( 'upload_path' ) );
		if ( empty( $upload_path ) ) {
			$upload_path = 'wp-content/uploads';
		}

		// TODO: Do we want to ensure the folder exists? This could flag false positives.
		if ( false === strpos( $filename, $upload_path ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Reads entire file into a string
	 *
	 * @param string $file Name of the file to read.
	 * @return string|bool The function returns the read data or false on failure.
	 */
	public function get_contents( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->get_contents( $file );
	}

	/**
	 * Reads entire file into an array
	 *
	 * @param string $file Path to the file.
	 * @return array|bool the file contents in an array or false on failure.
	 */
	public function get_contents_array( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->get_contents_array( $file );
	}

	/**
	 * Write a string to a file
	 *
	 * @param string $file     Remote path to the file where to write the data.
	 * @param string $contents The data to write.
	 * @param int    $mode     Optional. The file permissions as octal number, usually 0644.
	 *                         Default false. - Unimplemented
	 * @return bool False upon failure, true otherwise.
	 */
	public function put_contents( $file, $contents, $mode = false ) {
		$transport = $this->get_transport_for_path( $file );
		$transport->put_contents( $file, $contents, $mode );
		return true;
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param bool   $overwrite
	 * @param int    $mode - Unimplemented
	 * @return bool
	 */
	public function copy( $source, $destination, $overwrite = false, $mode = false ) {
		$source_transport = $this->get_transport_for_path( $source );
		$destination_transport = $this->get_transport_for_path( $destination );

		if ( ! $overwrite && $destination_transport->exists( $destination ) ) {
			return false;
		}

		$file_content = $source_transport->get_contents( $source );
		$destination_transport->put_contents( $destination, $file_content );
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param bool $overwrite
	 * @return bool
	 */
	public function move( $source, $destination, $overwrite = false ) {
		$copy_results = $this->copy( $source, $destination, $overwrite );
		if ( false === $copy_results ) {
			return false;
		}

		$this->delete( $source );

		return true; // TODO: What if delete fails?
	}

	/**
	 * @param string $file
	 * @param bool $recursive - Unimplemented
	 * @param string $type - Unimplemented
	 * @return bool
	 */
	public function delete( $file, $recursive = false, $type = false ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->delete( $file );
	}

	/**
	 * @param string $file
	 * @return int
	 */
	public function size( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->size( $file );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function exists( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->exists( $file );
	}
	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_file( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->is_file( $file );
	}
	/**
	 * @param string $path
	 * @return bool
	 */
	public function is_dir( $path ) {
		$transport = $this->get_transport_for_path( $path );
		return $transport->is_dir( $path );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_readable( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->is_readable( $file );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_writable( $file ) {
		$transport = $this->get_transport_for_path( $file );
		return $transport->is_writable( $file );
	}

	/**
	 * Unimplemented
	 *
	 * @param string $file
	 * @return int
	 */
	public function atime( $file ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented
	 *
	 * @param string $file
	 * @return int
	 */
	public function mtime( $file ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}



	/**
	 * Unimplemented
	 *
	 * @param string $file
	 * @param int $time
	 * @param int $atime
	 * @return bool
	 */
	public function touch( $file, $time = 0, $atime = 0 ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented
	 *
	 * @param string $path
	 * @param mixed  $chmod
	 * @param mixed  $chown
	 * @param mixed  $chgrp
	 * @return bool
	 */
	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented
	 *
	 * @param string $path
	 * @param bool $recursive
	 * @return bool
	 */
	public function rmdir( $path, $recursive = false ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented
	 *
	 * @param string $path
	 * @param bool $include_hidden
	 * @param bool $recursive
	 * @return bool|array
	 */
	public function dirlist( $path, $include_hidden = true, $recursive = false ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}


	/**
	 * Unimplemented - Gets the current working directory
	 *
	 * @return string|bool the current working directory on success, or false on failure.
	 */
	public function cwd() {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented - Change directory
	 *
	 * @param string $dir The new current directory.
	 * @return bool Returns true on success or false on failure.
	 */
	public function chdir( $dir ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented - Changes file group
	 *
	 * @param string $file      Path to the file.
	 * @param mixed  $group     A group name or number.
	 * @param bool   $recursive Optional. If set True changes file group recursively. Default false.
	 * @return bool Returns true on success or false on failure.
	 */
	public function chgrp( $file, $group, $recursive = false ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented - Changes filesystem permissions
	 *
	 * @param string $file      Path to the file.
	 * @param int    $mode      Optional. The permissions as octal number, usually 0644 for files,
	 *                          0755 for dirs. Default false.
	 * @param bool   $recursive Optional. If set True changes file group recursively. Default false.
	 * @return bool Returns true on success or false on failure.
	 */
	public function chmod( $file, $mode = false, $recursive = false ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented - Changes file owner
	 *
	 * @param string $file      Path to the file.
	 * @param mixed  $owner     A user name or number.
	 * @param bool   $recursive Optional. If set True changes file owner recursively.
	 *                          Default false.
	 * @return bool Returns true on success or false on failure.
	 */
	public function chown( $file, $owner, $recursive = false ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented - Gets file owner
	 *
	 * @param string $file Path to the file.
	 * @return string|bool Username of the user or false on error.
	 */
	public function owner( $file ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented - Gets file permissions
	 *
	 * FIXME does not handle errors in fileperms()
	 *
	 * @param string $file Path to the file.
	 * @return string Mode of the file (last 3 digits).
	 */
	public function getchmod( $file ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * Unimplemented
	 *
	 * @param string $file
	 * @return string|false
	 */
	public function group( $file ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}
}
