<?php

namespace Automattic\VIP\Files;

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';

require_once __DIR__ . '/class-api-client.php';

class WP_Filesystem_VIP_Uploads extends \WP_Filesystem_Base {

	/** @var API_Client */
	private $api;

	public function __construct( Api_Client $api_client ) {
		$this->method = 'vip-uploads';
		$this->errors = new \WP_Error();
		$this->api    = $api_client;
	}

	/**
	 * Gets the relative path for a file starting from `/wp-content/`.
	 *
	 * Which is the path that the Files Api_Client expects.
	 */
	protected function sanitize_uploads_path( $path ) {
		$sanitized_path = $path;

		$wp_content_dir = WP_CONTENT_DIR;
		$upload_dir     = wp_get_upload_dir();
		$upload_basedir = $upload_dir['basedir'];

		// WP_CONTENT_DIR and wp_get_upload_dir() may not be the same.
		// So we handle them separately.
		if ( 0 === stripos( $sanitized_path, $upload_basedir ) ) {
			$sanitized_path = str_ireplace( $upload_basedir, '', $sanitized_path );
			$sanitized_path = '/wp-content/uploads' . $sanitized_path;
		} elseif ( 0 === stripos( $sanitized_path, $wp_content_dir ) ) {
			$sanitized_path = str_ireplace( $wp_content_dir, '', $sanitized_path );
			$sanitized_path = '/wp-content' . $sanitized_path;
		}

		// TODO: Should we fail for other paths?

		$file_name = basename( $sanitized_path );
		$file_path = dirname( $sanitized_path );

		return sprintf( '%s/%s', $file_path, $file_name );
	}

	/**
	 * Reads entire file into a string
	 *
	 * @param string $file Name of the file to read.
	 *
	 * @return string|bool The function returns the read data or false on failure.
	 */
	public function get_contents( $file ) {
		$uploads_path = $this->sanitize_uploads_path( $file );

		$content = $this->api->get_file_content( $uploads_path );
		if ( is_wp_error( $content ) ) {
			$this->errors = $content;

			return false;
		}

		return $content;
	}

	/**
	 * Reads entire file into an array
	 *
	 * @param string $file Path to the file.
	 *
	 * @return array|bool the file contents in an array or false on failure.
	 */
	public function get_contents_array( $file ) {
		$contents = $this->get_contents( $file );
		if ( false === $contents ) {
			return false;
		}

		if ( empty( $contents ) ) {
			return [];
		}

		// Replicate the behaviour of `WP_Filesystem_Direct::get_contents_array` which uses `file`.
		// This adds the PHP_EOL character to the end of each array item.
		$lines = explode( PHP_EOL, $contents );

		return array_map( function ( $line ) {
			return $line . PHP_EOL;
		}, $lines );
	}

	/**
	 * Write a string to a file
	 *
	 * Since the API expects a file we'll copy the content to a local temporary file first.
	 *
	 * @param string $file_path Remote path to the file where to write the data.
	 * @param string $contents The data to write.
	 *
	 * @return bool False upon failure, true otherwise.
	 */
	public function put_contents( $file_path, $contents, $mode = false ) {
		$uploads_path = $this->sanitize_uploads_path( $file_path );

		$file_name     = basename( $file_path );
		$tmp_file_path = tempnam( get_temp_dir(), 'uploads-' . $file_name );    // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam
		file_put_contents( $tmp_file_path, $contents );                         // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		$response = $this->api->upload_file( $tmp_file_path, $uploads_path );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		unlink( $tmp_file_path );

		if ( is_wp_error( $response ) ) {
			$this->errors = $response;

			return false;
		}

		return true;
	}

	/**
	 * @param string $file
	 *
	 * @return bool
	 */
	public function delete( $file, $recursive = false, $type = false ) {
		$uploads_path = $this->sanitize_uploads_path( $file );

		$response = $this->api->delete_file( $uploads_path );
		if ( is_wp_error( $response ) ) {
			$this->errors = $response;

			return false;
		}

		return true;
	}

	/**
	 * Gets the file size (in bytes).
	 *
	 * @param string $file Path to file.
	 *
	 * @return int|bool Size of the file in bytes.
	 */
	public function size( $file ) {
		$uploads_path = $this->sanitize_uploads_path( $file );

		$contents = $this->get_contents( $uploads_path );
		if ( false === $contents ) {
			return false; // We don't need to set the errors as that's already done by `get_contents`
		}

		// TODO: switch to HEAD request and check the Content-Length header
		return mb_strlen( $contents );
	}

	/**
	 * Check if a file exists.
	 *
	 * @param string $file Path to file.
	 *
	 * @return bool Whether $file exists or not.
	 */
	public function exists( $file ) {
		$uploads_path = $this->sanitize_uploads_path( $file );

		// We don't have an API for managing directories.
		// Let's just assume we can create files on all paths.
		if ( $this->is_dir( $uploads_path ) ) {
			return true;
		}

		return $this->api->is_file( $uploads_path );
	}

	/**
	 * Check if resource is a file.
	 *
	 * @param string $file File path.
	 *
	 * @return bool Whether $file is a file.
	 */
	public function is_file( $file ) {
		// The API only deals with files, so we can just check for existence.
		return $this->exists( $file );
	}

	/**
	 * Check if resource is a directory.
	 *
	 * We just naively check to see if the path has an extension.
	 *
	 * @param string $path Directory path.
	 *
	 * @return bool Whether $path is a directory.
	 */
	public function is_dir( $path ) {
		$uploads_path = $this->sanitize_uploads_path( $path );

		$pathinfo = pathinfo( $uploads_path );

		return false === isset( $pathinfo['extension'] );
	}

	/**
	 * Check if a file is readable.
	 *
	 * @param string $file Path to file.
	 *
	 * @return bool Whether $file is readable.
	 */
	public function is_readable( $file ) {
		// If the file exists, we can read it.
		return $this->exists( $file );
	}

	/**
	 * Check if a file or directory is writable.
	 *
	 * @param string $file Path to file.
	 *
	 * @return bool Whether $file is writable.
	 */
	public function is_writable( $file ) {
		// This method is technically not implemented but we're returning true since we think most use cases would be to check if a file is writeable and then write to it. Given that most of the times the write will be successful there's not much to gain by implementing logic here.
		return true;
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
		// We don't have an API for managing directories.
		// Let's just assume we can create files on all paths.
		// And pretend that this dir was created.
		return true;
	}

	/**
	 * Unimplemented - Copy a file.
	 *
	 * This method should not be called directly and instead should be triggered via `WP_Filesystem_VIP`.
	 *
	 * @param string $source Path to the source file.
	 * @param string $destination Path to the destination file.
	 * @param bool $overwrite Optional. Whether to overwrite the destination file if it exists.
	 *                            Default false.
	 * @param int $mode Optional. The permissions as octal number, usually 0644 for files, 0755 for dirs.
	 *                            Default false.
	 *
	 * @return bool True if file copied successfully, False otherwise.
	 */
	public function copy( $source, $destination, $overwrite = false, $mode = false ) {
		// translators: 1: method name
		$error_msg = sprintf( __( 'The `%s` method cannot be called directly. Please use `WP_Filesystem_VIP::copy` instead' ), __METHOD__ );

		$this->errors->add( 'incorrect-usage', $error_msg );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( $error_msg, E_USER_WARNING );

		return false;
	}

	/**
	 * Move a file.
	 *
	 * This method should not be called directly and instead should be triggered via `WP_Filesystem_VIP`.
	 *
	 * @param string $source Path to the source file.
	 * @param string $destination Path to the destination file.
	 * @param bool $overwrite Optional. Whether to overwrite the destination file if it exists.
	 *                            Default false.
	 *
	 * @return bool True if file copied successfully, False otherwise.
	 */
	public function move( $source, $destination, $overwrite = false ) {
		// translators: 1 - method name
		$error_msg = sprintf( __( 'The `%s` method cannot be called directly. Please use `WP_Filesystem_VIP::move` instead' ), __METHOD__ );

		$this->errors->add( 'incorrect-usage', $error_msg );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( $error_msg, E_USER_WARNING );

		return false;
	}

	/**
	 * Unimplemented - Gets the file's last access time.
	 *
	 * @param string $file Path to file.
	 *
	 * @return int|bool Unix timestamp representing last access time.
	 */
	public function atime( $file ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Gets the file modification time.
	 *
	 * @param string $file Path to file.
	 *
	 * @return int|bool Unix timestamp representing modification time.
	 */
	public function mtime( $file ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Set the access and modification times of a file.
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
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Delete a directory.
	 *
	 * @param string $path Path to directory.
	 * @param bool $recursive Optional. Whether to recursively remove files/directories.
	 *                          Default false.
	 *
	 * @return bool Whether directory is deleted successfully or not.
	 */
	public function rmdir( $path, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Get details for files in a directory or a specific file.
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
		return $this->handle_unimplemented_method( __METHOD__ );
	}


	/**
	 * Unimplemented - Gets the current working directory
	 *
	 * @return string|bool the current working directory on success, or false on failure.
	 */
	public function cwd() {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Change directory
	 *
	 * @param string $dir The new current directory.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function chdir( $dir ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Changes file group
	 *
	 * @param string $file Path to the file.
	 * @param mixed $group A group name or number.
	 * @param bool $recursive Optional. If set True changes file group recursively. Default false.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function chgrp( $file, $group, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Changes filesystem permissions
	 *
	 * @param string $file Path to the file.
	 * @param int $mode Optional. The permissions as octal number, usually 0644 for files,
	 *                          0755 for dirs. Default false.
	 * @param bool $recursive Optional. If set True changes file group recursively. Default false.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function chmod( $file, $mode = false, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Changes file owner
	 *
	 * @param string $file Path to the file.
	 * @param mixed $owner A user name or number.
	 * @param bool $recursive Optional. If set True changes file owner recursively.
	 *                          Default false.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function chown( $file, $owner, $recursive = false ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Gets file owner
	 *
	 * @param string $file Path to the file.
	 *
	 * @return string|bool Username of the user or false on error.
	 */
	public function owner( $file ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	/**
	 * Unimplemented - Gets file permissions
	 *
	 * @param string $file Path to the file.
	 *
	 * @return string Mode of the file (last 3 digits).
	 */
	public function getchmod( $file ) {
		return $this->handle_unimplemented_method( __METHOD__, '' );
	}

	/**
	 * Unimplemented - Get the file's group.
	 *
	 * @param string $file Path to the file.
	 *
	 * @return string|bool The group or false on error.
	 */
	public function group( $file ) {
		return $this->handle_unimplemented_method( __METHOD__ );
	}

	protected function handle_unimplemented_method( $method, $return_value = false ) {
		/* Translators: unsupported method name */
		$error_msg = sprintf( __( 'The `%s` method is not implemented and/or not supported.' ), $method );

		$this->errors->add( 'unimplemented-method', $error_msg );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( $error_msg, E_USER_WARNING );

		return $return_value;
	}
}
