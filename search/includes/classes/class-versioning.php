<?php

namespace Automattic\VIP\Search;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;

use \WP_Error as WP_Error;

class Versioning {
	const INDEX_VERSIONS_OPTION = 'vip_search_index_versions';
	/**
	 * The maximum number of index versions that can exist for any indexable.
	 */
	const MAX_NUMBER_OF_VERSIONS = 2;

	/**
	 * Injectable instance of \ElasticPress\Elasticsearch
	 */
	public $elastic_search_instance;

	/**
	 * The currently used index version, by type. This lets us override the active version for indexing while another index is active
	 */
	private $current_index_version_by_type = array();

	/**
	 * An internal record of every object that has been queued up (via Queue::queue_object()) so that we can replicate those jobs
	 * to the other index versions at the end of the request
	 */
	private $queued_objects_by_type_and_version = array();

	/**
	 * Internal flag to prevent infinite loops when handling object deletions
	 */
	private $is_doing_object_delete;


	public function __construct() {
		// When objects are added to the queue, we want to replicate that out to all index versions, to keep them in sync
		add_action( 'vip_search_indexing_object_queued', [ $this, 'action__vip_search_indexing_object_queued' ], 10, 4 );
		add_action( 'shutdown', [ $this, 'action__shutdown' ], 100 ); // Must always run _after_ EP's own shutdown hooks, so that pre_ep_index_sync_queue has fired

		// When objects are indexed normally, they go into the EP "sync_queue", which we need to replicate out to the non-active index versions
		// NOTE - the priority is very important, and must come _after_ the Queue class's pre_ep_index_sync_queue hook, so we don't duplicate effort (to allow
		// the Queue to take over EP's queue, at which point we don't need to insert them here, as they are handled during Queue::queue_object())
		add_filter( 'pre_ep_index_sync_queue', [ $this, 'filter__pre_ep_index_sync_queue' ], 100, 3 );

		add_action( 'plugins_loaded', [ $this, 'action__plugins_loaded' ] );

		$this->elastic_search_instance = \ElasticPress\Elasticsearch::factory();
	}

	public function action__plugins_loaded() {
		// Hook into the delete action of all known indexables, to replicate those deletes out to all inactive index versions
		// NOTE - runs on plugins_loaded so Indexables are properly registered beforehand
		$all_indexables = \ElasticPress\Indexables::factory()->get_all();

		foreach ( $all_indexables as $indexable ) {
			add_action( 'ep_delete_' . $indexable->slug, [ $this, 'action__ep_delete_indexable' ], 10, 2 );
		}

		$this->maybe_self_heal();
	}

	/**
	 * Set the current (not active) version for a given Indexable. This allows us to work on other index versions without making
	 * that index active
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to temporarily set the current index version
	 * @return bool|WP_Error True on success, or WP_Error on failure
	 */
	public function set_current_version_number( Indexable $indexable, $version_number ) {
		$actual_version_number = $this->normalize_version_number( $indexable, $version_number );

		if ( is_wp_error( $actual_version_number ) ) {
			return $actual_version_number;
		}

		// Validate that the requested version is known
		$versions = $this->get_versions( $indexable );

		if ( ! isset( $versions[ $actual_version_number ] ) ) {
			return new WP_Error( 'invalid-index-version', sprintf( 'The requested index version %d does not exist', $version_number ) );
		}

		// Store as $version_number (not $actual_version_number) so that we can resolve aliases like next/previous at runtime in get_current_version_number()
		$this->current_index_version_by_type[ $indexable->slug ] = $version_number;

		return true;
	}

	/**
	 * Reset the current version for a given Indexable. This will default back to the active index, with no override
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to reset the current index version
	 * @return bool|WP_Error True on success
	 */
	public function reset_current_version_number( Indexable $indexable ) {
		unset( $this->current_index_version_by_type[ $indexable->slug ] );

		return true;
	}

	/**
	 * Get the current index version number
	 *
	 * The current index number is the index that should be used for requests. It is different than the active index, which is the index
	 * that has been designated as the default for all requests. The current index can be overridden to make requests to other indexs, such as
	 * for indexing content on them while they are still inactive
	 *
	 * This defaults to the active index, but can be overridden by calling Versioning::set_current_version_number()
	 *
	 * NOTE - purposefully not adding a typehint due to a warning emitted by our very old version of PHPUnit on PHP 7.4
	 * (Function ReflectionType::__toString() is deprecated), because we mock this function, which causes __toString() to be called for params
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to get the current version number
	 *
	 * @return int The current version number
	 */
	public function get_current_version_number( $indexable ) {
		$override = isset( $this->current_index_version_by_type[ $indexable->slug ] ) ? $this->current_index_version_by_type[ $indexable->slug ] : null;

		// If an override is specified, normalize it (in case it's an alias) and return
		if ( $override ) {
			return $this->normalize_version_number( $indexable, $override );
		}

		return $this->get_active_version_number( $indexable );
	}

	/**
	 * Retrieve the active index version for a given Indexable
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to get the active index version
	 * @return int The currently active index version
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
	 * @param \ElasticPress\Indexable $indexable The Indexable to get the active version number for
	 * @return int The currently active version number
	 */
	public function get_active_version_number( Indexable $indexable ) {
		$active_version = $this->get_active_version( $indexable );

		if ( ! $active_version ) {
			return 1;
		}

		return $active_version['number'];
	}

	public function get_inactive_versions( Indexable $indexable ) {
		$versions = $this->get_versions( $indexable );
		$active_version_number = $this->get_active_version_number( $indexable );

		unset( $versions[ $active_version_number ] );

		return $versions;
	}

	/**
	 * Retrieve details about available index versions
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable for which to retrieve index versions
	 * @return array Array of index versions
	 */
	public function get_versions( Indexable $indexable ) {
		$versions = get_option( self::INDEX_VERSIONS_OPTION, array() );

		$slug = $indexable->slug;

		if ( ! isset( $versions[ $slug ] ) || ! is_array( $versions[ $slug ] ) || empty( $versions[ $slug ] ) ) {
			return array(
				1 => array(
					'number' => 1,
					'active' => true,
					'created_time' => null, // We don't know when it was actually created
					'activated_time' => null,
				),
			);
		}

		// Normalize the versions to ensure consistency (have all fields, etc)
		return array_map( array( $this, 'normalize_version' ), $versions[ $slug ] );
	}

	/**
	 * Normalize the fields of a version, to handle old or incomplete data
	 *
	 * This is important to keep the data stored in the option consistent and current when changes to the structure are needed
	 *
	 * @param array The index version to normalize
	 * @return array The index version, with all data normalized
	 */
	public function normalize_version( $version ) {
		$version_fields = array(
			'number',
			'active',
			'created_time',
			'activated_time',
		);

		if ( ! is_array( $version ) ) {
			$version = array();
		}

		$keys = array_keys( $version );

		$missing_keys = array_diff( $version_fields, $keys );

		foreach ( $missing_keys as $key ) {
			$version[ $key ] = null;
		}

		return $version;
	}

	/**
	 * Given a version number, normalize it by translating any aliases into actual version numbers
	 *
	 * @param int|string $version_number The version number to normalize, can be an id or alias like "next" or "previous"
	 */
	public function normalize_version_number( Indexable $indexable, $version_number ) {
		if ( is_int( $version_number ) ) {
			return $version_number;
		}

		$version_number = trim( $version_number );

		switch ( $version_number ) {
			case 'active':
				return $this->get_active_version_number( $indexable );

			case 'next':
				return $this->get_next_existing_version_number( $indexable );

			case 'previous':
				return $this->get_previous_existing_version_number( $indexable );

			default:
				// Was it a number, but passed through as a string? return it as an int
				if ( ctype_digit( strval( $version_number ) ) ) {
					return intval( $version_number );
				}

				return new WP_Error( 'invalid-version-number-alias', 'Unknown version number alias. Please use "active", "next" or "previous"' );
		}
	}

	public function get_next_existing_version_number( Indexable $indexable ) {
		$active_version_number = $this->get_active_version_number( $indexable );

		// If there is no active version, we can't determine what next is
		if ( ! $active_version_number ) {
			return new WP_Error( 'no-active-index-found', 'There is no active index version so the "next" version cannot be determined' );
		}

		$versions = $this->get_versions( $indexable );

		if ( empty( $versions ) ) {
			return new WP_Error( 'no-index-versions-found', 'No index versions found' );
		}

		// The next existing is the lowest index number after $active_version_number that exists, or null
		$version_numbers = array_keys( $versions );

		sort( $version_numbers );

		$active_version_array_index = array_search( $active_version_number, $version_numbers, true );

		if ( false === $active_version_array_index ) {
			return new WP_Error( 'active-index-not-found-in-versions-list', 'Active index not found in list of index versions' );
		}

		$target_array_index = $active_version_array_index + 1;

		// Is there another?
		if ( ! isset( $version_numbers[ $target_array_index ] ) ) {
			return new WP_Error( 'no-next-version', 'There is no "next" index version defined' );
		}

		return $version_numbers[ $target_array_index ];
	}

	public function get_previous_existing_version_number( Indexable $indexable ) {
		$active_version_number = $this->get_active_version_number( $indexable );

		// If there is no active version, we can't determine what previous is
		if ( ! $active_version_number ) {
			return new WP_Error( 'no-active-index-found', 'There is no active index version so the "next" version cannot be determined' );
		}

		$versions = $this->get_versions( $indexable );

		if ( empty( $versions ) ) {
			return new WP_Error( 'no-index-versions-found', 'No index versions found' );
		}

		// The previous existing is the highest index number before $active_version_number that exists, or null
		$version_numbers = array_keys( $versions );

		sort( $version_numbers );

		$active_version_array_index = array_search( $active_version_number, $version_numbers, true );

		if ( false === $active_version_array_index ) {
			return new WP_Error( 'active-index-not-found-in-versions-list', 'Active index not found in list of index versions' );
		}

		$target_array_index = $active_version_array_index - 1;

		// Is there another?
		if ( 0 > $target_array_index || ! isset( $version_numbers[ $target_array_index ] ) ) {
			return new WP_Error( 'no-previous-version', 'There is no "previous" index version defined' );
		}

		return $version_numbers[ $target_array_index ];
	}

	/**
	 * Retrieve details about a given index version
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable for which to retrieve the index version
	 * @return array Array of index versions
	 */
	public function get_version( Indexable $indexable, $version_number ) {
		$slug = $indexable->slug;

		$version_number = $this->normalize_version_number( $indexable, $version_number );

		if ( is_wp_error( $version_number ) ) {
			return $version_number;
		}

		$versions = $this->get_versions( $indexable );

		if ( ! isset( $versions[ $version_number ] ) ) {
			return null;
		}

		return $versions[ $version_number ];
	}

	/**
	 * Retrieve details about available index versions
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable for which to create a new version
	 * @return bool Boolean indicating if the new version was successfully added or not
	 */
	public function add_version( Indexable $indexable ) {
		$slug = $indexable->slug;

		$versions = $this->get_versions( $indexable );

		$new_version_number = $this->get_next_version_number( $versions );

		if ( ! $new_version_number ) {
			return new WP_Error( 'unable-to-get-next-version', 'Unable to determine next index version' );
		}

		$current_version_count = count( $versions );
		if ( $current_version_count >= self::MAX_NUMBER_OF_VERSIONS ) {
			return new WP_Error(
				'too-many-index-versions',
				sprintf(
					'Currently, %d versions exist for indexable %s. Only %d versions allowed per indexable.',
					$current_version_count,
					$indexable->slug,
					self::MAX_NUMBER_OF_VERSIONS
				)
			);
		}

		$new_version = array(
			'number' => $new_version_number,
			'active' => false,
			'created_time' => time(),
			'activated_time' => null,
		);

		$versions[ $new_version_number ] = $new_version;

		$result = $this->update_versions( $indexable, $versions );

		if ( true !== $result ) {
			return $result;
		}

		// Setup the index + mapping so that it's available for immediate use (as changes will start getting replicated here)
		$this->create_versioned_index_with_mapping( $indexable, $new_version_number );

		return $new_version;
	}

	/**
	 * Create the index in ES and put the mapping
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to create the new versioned index
	 * @param int|string $version_number The index version number to create
	 */
	public function create_versioned_index_with_mapping( $indexable, $version_number ) {
		$version_number = $this->normalize_version_number( $indexable, $version_number );

		if ( is_wp_error( $version_number ) ) {
			return $version_number;
		}

		$this->set_current_version_number( $indexable, $version_number );

		$result = $indexable->put_mapping();

		$this->reset_current_version_number( $indexable );

		return $result;
	}

	/**
	 * Save details about available index versions
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to update versions
	 * @param array Array of version information for the given Indexable
	 * @return bool Boolean indicating if the version information was saved successfully or not
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
	 * @param array $versions Array of existing versions from which to calculate the next version

	 */
	public function get_next_version_number( $versions ) {
		$new_version = null;

		if ( ! empty( $versions ) && is_array( $versions ) ) {
			$new_version = max( array_keys( $versions ) );
		}

		// If site has no versions yet (1 version), the next version is 2
		if ( ! is_int( $new_version ) || $new_version < 2 ) {
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
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to activate the new index
	 * @param int|string $version_number The new index version to activate
	 * @return bool|WP_Error Boolean indicating success, or WP_Error on error
	 */
	public function activate_version( Indexable $indexable, $version_number ) {
		$version_number = $this->normalize_version_number( $indexable, $version_number );

		if ( is_wp_error( $version_number ) ) {
			return $version_number;
		}

		$versions = $this->get_versions( $indexable );

		// If this wasn't a valid version, abort with error
		if ( ! isset( $versions[ $version_number ] ) ) {
			return new WP_Error( 'invalid-index-version', sprintf( 'The index version %d was not found', $version_number ) );
		}

		// Mark all others as inactive, activate the new one
		foreach ( $versions as &$version ) {
			if ( $version_number === $version['number'] ) {
				$version['active'] = true;
				$version['activated_time'] = time();
			} else {
				$version['active'] = false;
			}
		}

		if ( ! $this->update_versions( $indexable, $versions ) ) {
			return new WP_Error( 'failed-activating-version', sprintf( 'The index version %d failed to activate', $version_number ) );
		}

		return true;
	}

	/**
	 * Delete the version of an index and remove the index from Elasticsearch
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to delete index
	 * @param int|string $version_number The index version to delete
	 * @return bool|WP_Error Boolean indicating success, or WP_Error on error
	 */
	public function delete_version( Indexable $indexable, $version_number ) {
		$version_number = $this->normalize_version_number( $indexable, $version_number );

		if ( is_wp_error( $version_number ) ) {
			return $version_number;
		}

		// Can't delete active version
		$active_version_number = $this->get_active_version_number( $indexable );

		if ( $active_version_number === $version_number ) {
			return new WP_Error( 'cannot-delete-active-index-version', 'The active index version cannot be deleted' );
		}

		$versions = $this->get_versions( $indexable );

		if ( ! isset( $versions[ $version_number ] ) ) {
			return new WP_Error( 'invalid-version-number', sprintf( 'The version %d does not exist for indexable %s', $version_number, $indexable->slug ) );
		}

		$delete_result = $this->delete_versioned_index( $indexable, $version_number );

		if ( ! $delete_result ) {
			return new WP_Error( 'failed-to-delete-index', sprintf( 'Failed to delete index version %d for indexable %s from Elasticsearch', $version_number, $indexable->slug ) );
		}

		\Automattic\VIP\Search\Search::instance()->queue->delete_jobs_for_index_version( $indexable->slug, $version_number );

		unset( $versions[ $version_number ] );

		return $this->update_versions( $indexable, $versions );
	}

	/**
	 * Delete the versioned index from Elasticsearch
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to delete index
	 * @param int|string $version_number The index version to delete
	 * @return bool Boolean indicating success or failure
	 */
	public function delete_versioned_index( $indexable, $version_number ) {
		$version_number = $this->normalize_version_number( $indexable, $version_number );

		if ( is_wp_error( $version_number ) ) {
			return $version_number;
		}

		$this->set_current_version_number( $indexable, $version_number );

		$result = $indexable->delete_index();

		$this->reset_current_version_number( $indexable );

		return $result;
	}

	/**
	 * Get stats for a given index version, such as how many documents it contains
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to activate the new index
	 * @param int The index version to get stats for
	 * @return array Array of index stats
	 */
	public function get_version_stats( Indexable $indexable, $version ) {
		// Need helper function in \ElasticPress\Elasticsearch
	}

	/**
	 * Implements the vip_search_indexing_object_queued action to keep track of queued objects so that we can transparently
	 * replicate the queued job to the non-active index versions
	 *
	 *
	 * @param int $object_id Object id
	 * @param string $object_type Object type (the Indexable slug)
	 * @param array $options The options passed to queue_object()
	 * @param int $index_version The index version that was used when queuing the object
	 */
	public function action__vip_search_indexing_object_queued( $object_id, $object_type, $options, $index_version ) {
		// Each time an object is queued, we keep track of that by version + type, so on shutdown, we can process them all in bulk
		if ( ! isset( $this->queued_objects_by_type_and_version[ $object_type ] ) ) {
			$this->queued_objects_by_type_and_version[ $object_type ] = array();
		}

		if ( ! isset( $this->queued_objects_by_type_and_version[ $object_type ][ $index_version ] ) ) {
			$this->queued_objects_by_type_and_version[ $object_type ][ $index_version ] = array();
		}

		$this->queued_objects_by_type_and_version[ $object_type ][ $index_version ][] = array(
			'object_id' => $object_id,
			'options' => $options,
		);
	}

	/**
	 * When the request finishes, find all items that had been queued up on the active index and replicate those jobs out to each non-active index version
	 *
	 * This ensures that the active index version is treated as The Truth, and non-active index versions follow it (and not the other way around)
	 */
	public function action__shutdown() {
		$this->replicate_queued_objects_to_other_versions( $this->queued_objects_by_type_and_version );
	}

	/**
	 * Given an array of object types and the objects queued by version, replicate those jobs to the
	 * _other_ index versions to keep them in sync
	 *
	 * @param $queued_objects Multidimensional array of queued objects, keyed first by type, then index version
	 */
	public function replicate_queued_objects_to_other_versions( $queued_objects ) {
		if ( ! is_array( $queued_objects ) || empty( $queued_objects ) ) {
			return;
		}

		// Loop over every type of object that was changed
		foreach ( $queued_objects as $object_type => $objects_by_version ) {
			$indexable = \ElasticPress\Indexables::factory()->get( $object_type );

			// If it's not a valid indexable, just skip
			if ( ! $indexable ) {
				continue;
			}

			$versions = $this->get_versions( $indexable );

			// Do we have any other index versions for this type? If not, nothing to do.
			if ( ! $versions || empty( $versions ) ) {
				continue;
			}

			$active_version_number = $this->get_active_version_number( $indexable );

			// Were there any changes to the active version? If not, we skip - we don't keep replicate non-active indexes to others
			if ( ! isset( $objects_by_version[ $active_version_number ] ) || empty( $objects_by_version[ $active_version_number ] ) ) {
				continue;
			}

			// Other index versions, besides active
			$inactive_versions = $this->get_inactive_versions( $indexable );

			// There were changes for active version - now we need to loop over every object that was queued for the active version and replicate that job to the other versions
			foreach ( $inactive_versions as $version ) {
				$this->set_current_version_number( $indexable, $version['number'] );

				foreach ( $objects_by_version[ $active_version_number ] as $entry ) {
					$object_id = $entry['object_id'];
					$options = is_array( $entry['options'] ) ? $entry['options'] : array();

					// Override the index version in the options
					$options['index_version'] = $version['number'];

					\Automattic\VIP\Search\Search::instance()->queue->queue_object( $object_id, $object_type, $options );
				}

				$this->reset_current_version_number( $indexable );
			}
		}
	}

	public function filter__pre_ep_index_sync_queue( $bail, $sync_manager, $indexable_slug ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		// If it's not a valid indexable, just skip
		if ( ! $indexable ) {
			return $bail;
		}

		$inactive_versions = $this->get_inactive_versions( $indexable );

		// If there are no inactive versions or nothing in the queue, we can just skip
		if ( empty( $inactive_versions ) || empty( $sync_manager->sync_queue ) ) {
			return $bail;
		}

		foreach ( $inactive_versions as $version ) {
			foreach ( $sync_manager->sync_queue as $object_id => $value ) {
				$options = array(
					'index_version' => $version['number'],
				);

				\Automattic\VIP\Search\Search::instance()->queue->queue_object( $object_id, $indexable_slug, $options );
			}
		}

		// We're not altering $bail, just transparently hooking in to re-index these items on the non-active index versions
		return $bail;
	}

	/**
	 * When an item is deleted from an index, replicate that delete out to all other index versions
	 *
	 * NOTE - this behaves differently than action__vip_search_indexing_object_queued() because it doesn't collect
	 * all the deletions during the request, then process them on shutdown. That would be an over optimization here
	 * since deletes are comparatively much less frequent, so the savings of preventing back-and-forth switching between
	 * index versions is not as great. We also process the deletes immediately, b/c the queue system currently does not
	 * support deletes (just indexing)
	 */
	public function action__ep_delete_indexable( $object_id, $indexable_slug ) {
		// Prevent infinite loops :)
		if ( $this->is_doing_object_delete ) {
			return;
		}

		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		// If it's not a valid indexable, just skip
		if ( ! $indexable ) {
			return;
		}

		$inactive_versions = $this->get_inactive_versions( $indexable );

		// If there are no inactive versions or nothing in the queue, we can just skip
		if ( empty( $inactive_versions ) ) {
			return;
		}

		// Set the flag to prevent infinite loops
		$this->is_doing_object_delete = true;

		foreach ( $inactive_versions as $version ) {
			$this->set_current_version_number( $indexable, $version['number'] );

			$indexable->delete( $object_id );

			$this->reset_current_version_number( $indexable );
		}

		// Clear the flag to return to normal
		$this->is_doing_object_delete = false;
	}

	/**
	 * Check if the versions are persisted correctly. If not recreate them.
	 */
	public function maybe_self_heal() {
		if ( $this->is_versioning_valid() ) {
			return;
		}

		$indicies = $this->get_all_accesible_indicies();


		// Well, self heal
	}

	public function is_versioning_valid() {
		$all_versions = get_option( self::INDEX_VERSIONS_OPTION, array() );

		if ( ! is_array( $all_versions ) || count( $all_versions ) == 0 ) {
			return false;
		}

		$valid_slugs = \ElasticPress\Indexables::factory()->get_all( null, true );


		foreach ( $all_versions as $slug => $versions ) {
			if ( ! in_array( $slug, $valid_slugs ) ) {
				return false;
			}

			if ( ! is_array( $versions ) || count( $versions ) == 0 ) {
				return false;
			}

			$active_statuses = wp_list_pluck( $versions, 'active' );

			$array_index = array_search( true, $active_statuses, true );

			if ( false === $array_index ) {
				return false;
			}
		}

		return true;
	}

	public function get_all_accesible_indicies() {
		$found_indices = [];

		$response = $this->elastic_search_instance->remote_request( '_cat/indices?format=json' );

		$response_code = (int) wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) || $response_code >= 400 ) {
			return $found_indices;
		}

		$response_body_json = wp_remote_retrieve_body( $response );
		$response_body = json_decode( $response_body_json, true );

		if ( ! is_array( $response_body ) ) {
			return $found_indices;
		}

		foreach ( $response_body as $index_obj ) {
			if ( is_array( $index_obj ) && isset( $index_obj['index'] ) ) {
				$found_indices[] = $index_obj['index'];
			}
		}

		return $found_indices;
	}

	public function reconstruct_versioning( $indicies ) {
		if ( ! is_array( $indicies ) ) {
			return [];
		}

		$versioning = [];

		foreach ( $indicies as $index ) {
			$index_parts = explode( '-', $index );

			// Proper index is `vip-<env_id>-<indexable-slug>(-<blog_id>)(-v<version>)`
			if ( count( $index_parts ) >= 3 ) {
				$slug = $index_parts[2];
				$last_part = $index_parts[ count( $index_parts ) - 1 ];
				$version = 1;
				if ( 'v' === substr( $last_part, 0, 1 ) && is_numeric( substr( $last_part, 1 ) ) ) {
					$version = intval( substr( $last_part, 1 ) );
				}

				if ( ! isset( $versioning[ $slug ] ) ) {
					$versioning[ $slug ] = [];
				}
				$versioning[ $slug ][] = $version;
			}
		}

		foreach ( $versioning as $slug => $versions ) {
			sort( $versions );
			$version_objects = array_map( function( $version ) {
				return [
					'number' => $version,
					'active' => false,
				];
			}, $versions);

			if ( count( $version_objects ) > 0 ) {
				$version_objects[0]['active'] = true;
			}

			$versioning[ $slug ] = $version_objects;
		}


		return $versioning;
	}
}
