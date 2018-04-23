<?php

namespace Automattic\VIP\Files;

class WP_Filesystem_Uploads extends \WP_Filesystem_Base {

	private $api;

	public function __construct() {
		$this->method = 'vip-uploads';
		$this->errors = new \WP_Error();
		$this->api = new_api_instance();
	}

	/**
	 * Reads entire file into a string
	 *
	 * @param string $file Name of the file to read.
	 * @return string|bool The function returns the read data or false on failure.
	 */
	public function get_contents( $file ) {
		//TODO: Caching for remote gets? Static single request cache vs memcache?
		$file = $this->api->get_file( $file );
		if ( is_wp_error( $file ) ) {
			$this->errors = $file;
			return false;
		}
		return $file;
	}

	/**
	 * Reads entire file into an array
	 *
	 * @param string $file Path to the file.
	 * @return array|bool the file contents in an array or false on failure.
	 */
	public function get_contents_array( $file ) {
		$file = $this->get_contents( $file );
		if ( false === $file ) {
			return false;
		}
		//We're going to explode the array based on the EOL character and then re-add the EOL character to the end of the Array item to replicate the behaviour of file() which this function uses when it's "direct" http://php.net/manual/en/function.file.php
		$array = explode( PHP_EOL, $file );
		array_map( function( $array_item ) {
			return $array_item . PHP_EOL;
		}, $array );

	}

	/**
	 * Write a string to a file
	 *
	 * @param string $file     Remote path to the file where to write the data.
	 * @param string $contents The data to write.
	 * @return bool False upon failure, true otherwise.
	 */
	public function put_contents( $file, $contents ) {
		return $this->api->put_contents( $file );
	}



	/**
	 * @param string $file
	 * @return bool
	 */
	public function delete( $file ) {
		$response = $this->api->delete_file( $file );
		if ( is_wp_error( $response ) ) {
			$this->errors = $response;
			return false;
		}
		return true;
	}

	/**
	 *
	 * This is currently really not efficient as we're fetching the whole file remotely to determine it's size. We might want to optimize this in the future.
	 *
	 * @param string $file
	 * @return int
	 */
	public function size( $file ) {
		$file = $this->get_contents( $this );
		if ( false === $file ) {
			return false; // We don't need to set the errors as that's already done by `get_contents`
		}
		return sizeof( $file );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function exists( $file ) {
		return $this->api->is_file( $file );
	}
	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_file( $file ) {
		return $this->api->is_file( $file );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_readable( $file ) {
		// Right now if we get that the file exists then we can read it. There's no circumstance under which we should have access to knowing a file exists but not being able to read it.
		return $this->api->is_file( $file );
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function is_writable( $file ) {
		return $this->api->is_writable( $file );
	}

	/**
	 *
	 * Unimplemented, this is handled at the WP_Filesystem_VIP_Compatability_Layer layer so that we can do from /tmp/ to uploads and vice versa
	 *
	 * @param string $source
	 * @param string $destination
	 * @param bool   $overwrite
	 * @param int    $mode -
	 * @return bool
	 */
	public function copy( $source, $destination, $overwrite = false, $mode = false ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 *
	 * Unimplemented, this is handled at the WP_Filesystem_VIP_Compatability_Layer layer so that we can do from /tmp/ to uploads and vice versa
	 *
	 * @param string $source
	 * @param string $destination
	 * @param bool $overwrite
	 * @return bool
	 */
	public function move( $source, $destination, $overwrite = false ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function is_dir( $path ) {
		trigger_error( 'This function is currently unimplemented', E_USER_ERROR );
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
