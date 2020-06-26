<?php

namespace Automattic\VIP\Search;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;

use \WP_Error as WP_Error;

class Versioning {
	const CURRENT_VERSION_OPTION = 'vip_search_index_versions';

	/**
	 * Retrieve the active index version for a given Indexable
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable type for which to get the active index version
	 * @return {int} The currently active index version
	 */
	public function get_active_version( \ElasticPress\Indexable $indexable ) {

	}

	/**
	 * Retrieve details about available index versions
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The optional indexable type for which to retrieve index versions
	 * @return {array} Array of index versions
	 */
	public function get_versions( \ElasticPress\Indexable $indexable = null ) {

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
	 * @param {int} $version The new index version to activate
	 * @return {bool|WP_Error} Boolean indicating success, or WP_Error on error 
	 */
	public function activate_version( \ElasticPress\Indexable $indexable, $version ) {

	}

	/**
	 * Get stats for a given index version, such as how many documents it contains
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable type for which to activate the new index
	 * @param {int} The index version to get stats for
	 * @return {array} Array of index stats
	 */
	public function get_version_stats( \ElasticPress\Indexable $indexable, $version ) {
		// Need helper function in \ElasticPress\Elasticsearch
	}

	/**
	 * Get the versioned index name for a given Indexable
	 * 
	 * Note - versions start at 1, and the first version will not contain a version identifier for backwards compatibility with
	 * unversioned indexes
	 * 
	 * @param {\ElasticPress\Indexable} $indexable The Indexable type for which to get the versioned index name
	 * @param {int} The index version to get the name for
	 * @return {string} The versioned index name
	 */
	public function get_versioned_index_name( \ElasticPress\Indexable $indexable, $version ) {

	}
}
