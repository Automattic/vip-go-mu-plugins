<?php

namespace Automattic\VIP\Files;

class Path_Utils {
	public static function is_subdirectory_multisite_path( $file_path, $uploads_path ) {
		$pattern = '#^/[_0-9a-zA-Z-]+/' . $uploads_path . '/#';

		return preg_match( $pattern, $file_path );
	}

	public static function is_sub_subdirectory_multisite_path( $file_path, $uploads_path ) {
		$pattern = '#^/[_0-9a-zA-Z-]+/[_0-9a-zA-Z-]+/' . $uploads_path . '/#';

		return preg_match( $pattern, $file_path );
	}

	/**
	 * Strips off sub- and sub-subdirectory from a valid, multisite file path.
	 *
	 * For example, given a URL like `/subsite-1/subsite_2/wp-content/uploads/sites/1/file.jpg`.
	 * We will get back `/wp-content/uploads/sites/1/file.jpg`.
	 *
	 * Note: only supports 2 levels of subdirectories.
	 *
	 * @param string $file_path The file path to sanitize
	 * @param string $uploads_path The relative path from ABSPATH to `uploads`, minus leading and trailing slashes (e.g. `wp-content/uploads`)
	 * @return string|bool The sanitized path if it's a valid path, otherwise `false`.
	 */
	public static function trim_leading_multisite_directory( $file_path, $uploads_path ) {
		if ( self::is_sub_subdirectory_multisite_path( $file_path, $uploads_path ) ) {
			return preg_replace( '#^/[_0-9a-zA-Z-]+/[_0-9a-zA-Z-]+#', '', $file_path );
		} elseif ( self::is_subdirectory_multisite_path( $file_path, $uploads_path ) ) {
			return preg_replace( '#^/[_0-9a-zA-Z-]+#', '', $file_path );
		}

		return false;
	}
}
