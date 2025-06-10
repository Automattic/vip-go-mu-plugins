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
