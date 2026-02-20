<?php
/**
 * Integration utils.
 * 
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
	* Get the available versions of the integration in descending order.
	*
	* @return array<string, string> An associative array of available versions, where the key is the
	*                               directory name and the value is the version number. The versions
	*                               are sorted in descending order.
	*/
function get_available_versions( string $dir, string $folder_prefix, string $entry_file ): array {
	$versions = [];
	if ( ! is_dir( $dir ) ) {
		return $versions;
	}

	$scan_entries = scandir( $dir );
	foreach ( $scan_entries as $entry ) {
		if (
			str_contains( $entry, $folder_prefix . '-' ) &&
			is_dir( $dir . $entry ) &&
			file_exists( $dir . $entry . '/' . $entry_file )
		) {
			// Extract the version number from the directory name
			$versions[ $entry ] = str_replace( $folder_prefix . '-', '', $entry );
		}
	}

	// Sort the versions in descending order.
	uksort( $versions, function ( $a, $b ) use ( $versions ) {
		return version_compare( $versions[ $b ], $versions[ $a ] );
	} );

	return $versions;
}

/**
 * Get the latest version of the integration.
 *
 * @param string $dir The directory to scan for versions.
 * @param string $folder_prefix The prefix of the folder name.
 * @param string $entry_file The entry file of the integration.
 * @return string|null The latest version of the integration or null if no versions are found.
 */
function get_latest_version( string $dir, string $folder_prefix, string $entry_file ): string|null {
	// Get all the available versions in the directory, sorted in descending order.
	$versions = get_available_versions( $dir, $folder_prefix, $entry_file );

	// If no versions are found, return null.
	if ( empty( $versions ) ) {
		return null;
	}

	// Return the latest version.
	return array_key_first( $versions );
}
