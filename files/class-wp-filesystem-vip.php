<?php

namespace Automattic\VIP\Files;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

require_once __DIR__ . '/class-wp-filesystem-vip-uploads.php';
require_once __DIR__ . '/class-api-client.php';

use WP_Error;
use WP_Filesystem_Direct;

class WP_Filesystem_VIP extends \WP_Filesystem_Base {

	/** @var WP_Filesystem_Direct */
	private $direct;

	/**
	 * @param array $dependencies Array that contains an instance of `WP_Filesystem_VIP_Uploads` and `WP_Filesystem_Direct`.
	 */
	public function __construct( $dependencies ) {
		$this->method = 'vip';
		$this->errors = new WP_Error();

		list( $filesystem_uploads, $filesystem_direct ) = $dependencies;

		$this->uploads = $filesystem_uploads;
		$this->direct  = $filesystem_direct;
	}

	/**
	 * Try to find the right class to handle the file.
	 * If this is a 'read' context we'll default to passing it to WP_Filesystem_Direct
	 *
	 * @param $filename
	 * @param $context string "read" or "write"
	 *
	 * @return WP_Filesystem_VIP_Uploads|bool|mixed|WP_Filesystem_Direct
	 */
	private function get_transport_for_path( $filename, $context = 'read' ) {
		// If we're not in a VIP environment, allow some WP_CLI functionality to work.
		if ( true !== VIP_GO_ENV ) {
			// Allow access to the maintenance file used by WP-CLI.
			if ( $this->is_maintenance_file( $filename ) ) {
				return $this->direct;
			}

			// Allow core upgrades.
			// Note: WP_CLI doesn't set WP_INSTALLING so we fallback to checking for the upgrade lock instead.
			if ( wp_installing() || get_option( 'core_updater.lock' ) ) {
				return $this->direct;
			}

			// Allow plugin, theme, and language installs.
			if ( $this->is_upgrade_path( $filename )
				|| $this->is_plugins_path( $filename )
				|| $this->is_themes_path( $filename )
				|| $this->is_languages_path( $filename ) ) {
				return $this->direct;
			}
		}

		// Uploads paths can just use PHP functions when stream wrapper is enabled.
		// This is because wp_upload_dir will return a vip:// path.
		if ( $this->is_uploads_path( $filename ) ) {
			if ( defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) && true === VIP_FILESYSTEM_USE_STREAM_WRAPPER ) {
				return $this->direct;
			}

			return $this->uploads;
		} elseif ( $this->is_tmp_path( $filename ) ) {
			return $this->direct;
		} elseif ( 'read' === $context ) {
			return $this->direct;
		}

		$upload_dir = wp_get_upload_dir()['basedir'];
		$temp_dir   = get_temp_dir();

		/* Translators: 1) file name 2) class name 3) tmp dir path 4) uploads dir path */
		$error_msg = sprintf( __( 'The `%1$s` file cannot be managed by the `%2$s` class. Writes are only allowed for the `%3$s` and `%4$s` directories and reads can be performed everywhere.' ), $filename, __CLASS__, $temp_dir, $upload_dir );

		$this->errors->add( 'unsupported-filepath', $error_msg );

		// TODO: Do we want to trigger_error in all environments? (Or just a small batch to start).
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error( esc_html( $error_msg ), E_USER_WARNING );

		return false;
	}

	private function is_maintenance_file( $file_path ) {
		$maintenance_file = ABSPATH . '.maintenance';

		return $file_path === $maintenance_file;
	}

	private function is_tmp_path( $file_path ) {
		$tmp_dir = get_temp_dir();

		return 0 === strpos( $file_path, $tmp_dir );
	}

	private function is_uploads_path( $file_path ) {
		$upload_dir  = wp_get_upload_dir();
		$upload_base = $upload_dir['basedir'];

		return 0 === strpos( $file_path, $upload_base );
	}

	private function is_wp_content_subfolder_path( $file_path, $subfolder ) {
		$upgrade_base = sprintf( '%s/%s', WP_CONTENT_DIR, $subfolder );
		return 0 === strpos( $file_path, $upgrade_base . '/' ) || $file_path === $upgrade_base;
	}

	private function is_upgrade_path( $file_path ) {
		return $this->is_wp_content_subfolder_path( $file_path, 'upgrade' );
	}

	private function is_plugins_path( $file_path ) {
		return $this->is_wp_content_subfolder_path( $file_path, 'plugins' );
	}

	private function is_themes_path( $file_path ) {
		return $this->is_wp_content_subfolder_path( $file_path, 'themes' );
	}

	private function is_languages_path( $file_path ) {
		return $this->is_wp_content_subfolder_path( $file_path, 'languages' );
	}

	/**
	 * Reads entire file into a string
	 *
	 * @param string $file Name of the file to read.
	 *
	 * @return string|bool The function returns the read data or false on failure.
	 */
	public function get_contents( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->get_contents( $file );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Reads entire file into an array
	 *
	 * @param string $file Path to the file.
	 *
	 * @return array|bool the file contents in an array or false on failure.
	 */
	public function get_contents_array( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->get_contents_array( $file );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Write a string to a file
	 *
	 * @param string $file Remote path to the file where to write the data.
	 * @param string $contents The data to write.
	 * @param int $mode Optional. The file permissions as octal number, usually 0644.
	 *
	 * @return bool False upon failure, true otherwise.
	 */
	public function put_contents( $file, $contents, $mode = false ) {
		$transport = $this->get_transport_for_path( $file, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->put_contents( $file, $contents, $mode );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param bool $overwrite
	 * @param int $mode
	 *
	 * @return bool
	 */
	public function copy( $source, $destination, $overwrite = false, $mode = false ) {
		$source_transport = $this->get_transport_for_path( $source );
		if ( ! $source_transport ) {
			return false;
		}

		$destination_transport = $this->get_transport_for_path( $destination, 'write' );
		if ( ! $destination_transport ) {
			return false;
		}

		$destination_exists = $destination_transport->exists( $destination );
		if ( ! $overwrite && $destination_exists ) {
			/* translators: 1: destination file path 2: overwrite param 3: `true` boolean value */
			$this->errors->add( 'destination-exists', sprintf( __( 'The destination path (`%1$s`) already exists and `%2$s` was not not set to `%3$s`.' ), $destination, '$overwrite', 'true' ) );
			return false;
		}

		$file_content = $source_transport->get_contents( $source );
		if ( false === $file_content ) {
			$this->errors = $source_transport->errors;
			return false;
		}

		$put_results = $destination_transport->put_contents( $destination, $file_content, $mode );
		if ( false === $put_results ) {
			$this->errors = $destination_transport->errors;
			return false;
		}

		return $put_results;

	}

	/**
	 * @param string $source
	 * @param string $destination
	 * @param bool $overwrite
	 *
	 * @return bool
	 */
	public function move( $source, $destination, $overwrite = false ) {
		$copy_results = $this->copy( $source, $destination, $overwrite );
		if ( false === $copy_results ) {
			return false;
		}

		// We don't need to set the errors here since delete() will take care of it
		return $this->delete( $source );
	}

	/**
	 * @param string $file
	 * @param bool $recursive
	 * @param string $type
	 *
	 * @return bool
	 */
	public function delete( $file, $recursive = false, $type = false ) {
		$transport = $this->get_transport_for_path( $file, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->delete( $file, $recursive, $type );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * @param string $file
	 *
	 * @return int
	 */
	public function size( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->size( $file );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * @param string $file
	 *
	 * @return bool
	 */
	public function exists( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->exists( $file );
		if ( false === $return
			&& $transport->errors->get_error_code() ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * @param string $file
	 *
	 * @return bool
	 */
	public function is_file( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->is_file( $file );
		if ( false === $return
			&& $transport->errors->get_error_code() ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * @param string $path
	 *
	 * @return bool
	 */
	public function is_dir( $path ) {
		$transport = $this->get_transport_for_path( $path );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->is_dir( $path );
		if ( false === $return
			&& $transport->errors->get_error_code() ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * @param string $file
	 *
	 * @return bool
	 */
	public function is_readable( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->is_readable( $file );
		if ( false === $return
			&& $transport->errors->get_error_code() ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * @param string $file
	 *
	 * @return bool
	 */
	public function is_writable( $file ) {
		$transport = $this->get_transport_for_path( $file, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->is_writable( $file );
		if ( false === $return
			&& $transport->errors->get_error_code() ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Gets the file's last access time.
	 *
	 * @param string $file Path to file.
	 *
	 * @return int|bool Unix timestamp representing last access time.
	 */
	public function atime( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->atime( $file );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Gets the file modification time.
	 *
	 * @param string $file Path to file.
	 *
	 * @return int|bool Unix timestamp representing modification time.
	 */
	public function mtime( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->mtime( $file );
		if ( false === $return
			&& $transport->errors->get_error_code() ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Set the access and modification times of a file.
	 *
	 * Note: If $file doesn't exist, it will be created.
	 *
	 * @param string $file Path to file.
	 * @param int $time Optional. Modified time to set for file.
	 *                      Default 0.
	 * @param int $atime Optional. Access time to set for file.
	 *                      Default 0.
	 *
	 * @return bool Whether operation was successful or not.
	 */
	public function touch( $file, $time = 0, $atime = 0 ) {
		$transport = $this->get_transport_for_path( $file, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->touch( $file, $time, $atime );
		if ( false === $return
			&& $transport->errors->get_error_code() ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Create a directory.
	 *
	 * @param string $path Path for new directory.
	 * @param mixed $chmod Optional. The permissions as octal number, (or False to skip chmod)
	 *                      Default false.
	 * @param mixed $chown Optional. A user name or number (or False to skip chown)
	 *                      Default false.
	 * @param mixed $chgrp Optional. A group name or number (or False to skip chgrp).
	 *                      Default false.
	 *
	 * @return bool False if directory cannot be created, true otherwise.
	 */
	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ) {
		$transport = $this->get_transport_for_path( $path, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->mkdir( $path, $chmod, $chown, $chgrp );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Delete a directory.
	 *
	 * @param string $path Path to directory.
	 * @param bool $recursive Optional. Whether to recursively remove files/directories.
	 *                          Default false.
	 *
	 * @return bool Whether directory is deleted successfully or not.
	 */
	public function rmdir( $path, $recursive = false ) {
		$transport = $this->get_transport_for_path( $path, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->rmdir( $path, $recursive );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Get details for files in a directory or a specific file.
	 *
	 * @param string $path Path to directory or file.
	 * @param bool $include_hidden Optional. Whether to include details of hidden ("." prefixed) files.
	 *                               Default true.
	 * @param bool $recursive Optional. Whether to recursively include file details in nested directories.
	 *                               Default false.
	 *
	 * @return array|bool {
	 *     Array of files. False if unable to list directory contents.
	 *
	 * @type string $name Name of the file/directory.
	 * @type string $perms *nix representation of permissions.
	 * @type int $permsn Octal representation of permissions.
	 * @type string $owner Owner name or ID.
	 * @type int $size Size of file in bytes.
	 * @type int $lastmodunix Last modified unix timestamp.
	 * @type mixed $lastmod Last modified month (3 letter) and day (without leading 0).
	 * @type int $time Last modified time.
	 * @type string $type Type of resource. 'f' for file, 'd' for directory.
	 * @type mixed $files If a directory and $recursive is true, contains another array of files.
	 * }
	 */
	public function dirlist( $path, $include_hidden = true, $recursive = false ) {
		$transport = $this->get_transport_for_path( $path );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->dirlist( $path, $include_hidden, $recursive );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}


	/**
	 * Gets the current working directory
	 *
	 * @return string|bool the current working directory on success, or false on failure.
	 */
	public function cwd() {
		$transport = $this->get_transport_for_path( '' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->cwd();
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Change directory
	 *
	 * @param string $dir The new current directory.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function chdir( $dir ) {
		$transport = $this->get_transport_for_path( $dir );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->chdir( $dir );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Changes file group
	 *
	 * @param string $file Path to the file.
	 * @param mixed $group A group name or number.
	 * @param bool $recursive Optional. If set True changes file group recursively. Default false.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function chgrp( $file, $group, $recursive = false ) {
		$transport = $this->get_transport_for_path( $file, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->chgrp( $file, $group, $recursive );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Changes filesystem permissions
	 *
	 * @param string $file Path to the file.
	 * @param int $mode Optional. The permissions as octal number, usually 0644 for files,
	 *                          0755 for dirs. Default false.
	 * @param bool $recursive Optional. If set True changes file group recursively. Default false.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function chmod( $file, $mode = false, $recursive = false ) {
		$transport = $this->get_transport_for_path( $file, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->chmod( $file, $mode, $recursive );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Changes file owner
	 *
	 * @param string $file Path to the file.
	 * @param mixed $owner A user name or number.
	 * @param bool $recursive Optional. If set True changes file owner recursively.
	 *                          Default false.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function chown( $file, $owner, $recursive = false ) {
		$transport = $this->get_transport_for_path( $file, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->chown( $file, $owner, $recursive );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Gets file owner
	 *
	 * @param string $file Path to the file.
	 *
	 * @return string|bool Username of the user or false on error.
	 */
	public function owner( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->owner( $file );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Gets file permissions
	 *
	 * @param string $file Path to the file.
	 *
	 * @return string Mode of the file (last 3 digits).
	 */
	public function getchmod( $file ) {
		$transport = $this->get_transport_for_path( $file, 'write' );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->gethchmod( $file );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}

	/**
	 * Get the file's group.
	 *
	 * @param string $file Path to the file.
	 *
	 * @return string|bool The group or false on error.
	 */
	public function group( $file ) {
		$transport = $this->get_transport_for_path( $file );
		if ( ! $transport ) {
			return false;
		}

		$return = $transport->group( $file );
		if ( false === $return ) {
			$this->errors = $transport->errors;
			return false;
		}

		return $return;
	}
}
