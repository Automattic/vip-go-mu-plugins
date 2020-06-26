<?php

namespace Automattic\VIP\Search;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;

use \WP_Error as WP_Error;

class Versioning {
	const INDEX_VERSIONS_OPTION = 'vip_search_index_versions';

	/**
	 * Retrieve the active index version for a given Indexable
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable type for which to get the active index version
	 * @return {int} The currently active index version
	 */
	public function get_active_version( Indexable $indexable ) {
		$versions = $this->get_versions( $indexable );

		$active_statuses = wp_list_pluck( $versions, 'active' );

		$array_index = array_search( true, $active_statuses, true );

		if ( false === $array_index ) {
			return null;
		}

		return $versions[ $array_index ];
	}

	/**
	 * Grab just the version number for the active version
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable to get the active version number for
	 * @return {int} The currently active version number
	 */
	public function get_active_version_number( Indexable $indexable ) {
		$active_version = $this->get_active_version( $indexable );

		if ( ! $active_version ) {
			return 1;
		}

		return $active_version['number'];
	}

	/**
	 * Retrieve details about available index versions
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable for which to retrieve index versions
	 * @return {array} Array of index versions
	 */
	public function get_versions( Indexable $indexable ) {
		if ( Search::is_network_mode() ) {
			$versions = get_site_option( self::INDEX_VERSIONS_OPTION, array() );
		} else {
			$versions = get_option( self::INDEX_VERSIONS_OPTION, array() );
		}

		$slug = $indexable->slug;

		if ( ! isset( $versions[ $slug ] ) || ! is_array( $versions[ $slug ] ) ) {
			return array();
		}

		return $versions[ $slug ];
	}

	/**
	 * Retrieve details about available index versions
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable for which to create a new version
	 * @return {bool} Boolean indicating if the new version was successfully added or not
	 */
	public function add_version( Indexable $indexable ) {
		$slug = $indexable->slug;
	
		$versions = $this->get_versions( $indexable );

		$new_version = $this->get_next_version_number( $versions );

		if ( ! $new_version ) {
			return new WP_Error( 'unable-to-get-next-version', 'Unable to determine next index version' );
		}

		$versions[] = array(
			'number' => $new_version,
			'active' => false,
			'created_time' => time(),
		);

		return $this->update_versions( $indexable, $versions );
	}

	/**
	 * Save details about available index versions
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable type for which to update versions
	 * @param {array} Array of version information for the given Indexable
	 * @return {bool} Boolean indicating if the version information was saved successfully or not
	 */
	public function update_versions( Indexable $indexable, $versions ) {
		if ( Search::is_network_mode() ) {
			$current_versions = get_site_option( self::INDEX_VERSIONS_OPTION, array() );
		} else {
			$current_versions = get_option( self::INDEX_VERSIONS_OPTION, array() );
		}

		$current_versions[ $indexable->slug ] = $versions;
	
		if ( Search::is_network_mode() ) {
			return update_site_option( self::INDEX_VERSIONS_OPTION, $current_versions, 'no' );
		}

		return update_option( self::INDEX_VERSIONS_OPTION, $current_versions, 'no' );
	}

	/**
	 * Determine what the next index version number is, based on an array of existing index versions
	 * 
	 * Versions start at 1
	 * 
	 * @param {array} $versions Array of existing versions from which to calculate the next version
	 * @return {int} Next version number
	 */
	public function get_next_version_number( $versions ) {
		$new_version = null;

		// If site has no versions yet (1 version), the next version is 2
		if ( empty( $versions ) || ! is_array( $versions ) ) {
			$new_version = 2;
		} else {
			$new_version = max( wp_list_pluck( $versions, 'number' ) );
		}

		if ( ! is_int( $new_version ) || $new_version <= 2 ) {
			$new_version = 2;
		} else {
			$new_version++;
		}

		return $new_version;
	}

	/**
	 * Activate a new version of an index
	 * 
	 * Verifies that the new target index does in-fact exist, then marks it as active
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable type for which to activate the new index
	 * @param {int} $version_number The new index version to activate
	 * @return {bool|WP_Error} Boolean indicating success, or WP_Error on error 
	 */
	public function activate_version( Indexable $indexable, $version_number ) {
		$versions = $this->get_versions( $indexable );

		$version_found = false;

		// Mark all others as inactive, activate the new one
		foreach ( $versions as &$version ) {
			if ( $version_number === $version['number'] ) {
				$version_found = true;

				$version['active'] = true;
				$version['activated_time'] = time();
			} else {
				$version['active'] = false;
			}
		}

		// If this wasn't a valid version, abort with error
		if ( ! $version_found ) {
			return new WP_Error( 'invalid-index-version', sprintf( 'The index version %d was not found', $version_number ) );
		}

		if ( ! $this->update_versions( $indexable, $versions ) ) {
			return new WP_Error( 'failed-activating-version', sprintf( 'The index version %d failed to activate', $version_number ) );
		}

		return true;
	}

	/**
	 * Get stats for a given index version, such as how many documents it contains
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable type for which to activate the new index
	 * @param {int} The index version to get stats for
	 * @return {array} Array of index stats
	 */
	public function get_version_stats( Indexable $indexable, $version ) {
		// Need helper function in \ElasticPress\Elasticsearch
	}
}
