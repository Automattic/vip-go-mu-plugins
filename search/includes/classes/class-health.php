<?php

namespace Automattic\VIP\Search;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;

use \WP_Query as WP_Query;
use \WP_User_Query as WP_User_Query;
use \WP_Error as WP_Error;

class Health {
	const CONTENT_VALIDATION_BATCH_SIZE    = 500;
	const CONTENT_VALIDATION_MAX_DIFF_SIZE = 1000;
	const CONTENT_VALIDATION_LOCK_NAME = 'vip_search_content_validation_ongoing';
	const CONTENT_VALIDATION_LOCK_TIMEOUT = 900; // 15 min
	const DOCUMENT_IGNORED_KEYS            = array(
		// This field is proving problematic to reliably diff due to differences in the filters
		// that run during normal indexing and this validator
		'post_content_filtered',

		// Meta fields from EP's "magic" formatting, which is non-deterministic and impossible to validate
		'datetime',
		'date',
		'time',
	);

	public function __construct() {
		$this->indexables = \ElasticPress\Indexables::factory();
	}

	/**
	 * Verify the difference in number for a given entity between the DB and the index.
	 * Entities can be either posts or users.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param array $query_args Valid WP_Query criteria, mandatory fields as in following example:
	 * $query_args = [
	 *      'post_type' => $post_type,
	 *      'post_status' => array( $post_statuses )
	 * ];
	 *
	 * @param mixed $indexable Instance of an ElasticPress Indexable Object to search on
	 * @return WP_Error|array
	 */
	public function validate_index_entity_count( array $query_args, \ElasticPress\Indexable $indexable ) {
		$result = [
			'entity'   => $indexable->slug,
			'type'     => ( array_key_exists( 'post_type', $query_args ) ? $query_args['post_type'] : 'N/A' ),
			'skipped'  => false,
			'db_total' => 'N/A',
			'es_total' => 'N/A',
			'diff' => 'N/A',
		];

		$es_total = $this->get_index_entity_count_from_elastic_search( $query_args, $indexable );
		if ( is_wp_error( $es_total ) ) {
			return $es_total;
		}

		if ( 0 === $es_total ) {
			// If there is 0 docs in ES, we assume it wasnet initialized and we will skip the rest of the check
			$result['skipped'] = true;
			$result['es_total'] = 0;
			return $result;
		}

		try {
			// Get total count in DB
			$db_result = $indexable->query_db( $query_args );

			$db_total = (int) $db_result['total_objects'];
		} catch ( \Exception $e ) {
			return new WP_Error( 'db_query_error', sprintf( 'failure querying the DB: %s #vip-search', $e->get_error_message() ) );
		}

		$diff = 0;
		if ( $db_total !== $es_total ) {
			$diff = $es_total - $db_total;
		}

		$result['db_total'] = $db_total;
		$result['es_total'] = $es_total;
		$result['diff'] = $diff;

		return $result;
	}

	/**
	 * Fetches the count of entities in ES index
	 * Entities can be either posts or users.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param array $query_args Valid WP_Query criteria, mandatory fields as in following example:
	 * $query_args = [
	 *      'post_type' => $post_type,
	 *      'post_status' => array( $post_statuses )
	 * ];
	 *
	 * @param mixed $indexable Instance of an ElasticPress Indexable Object to search on
	 * @return WP_Error|int
	 */
	public function get_index_entity_count_from_elastic_search( array $query_args, \ElasticPress\Indexable $indexable ) {
		// Get total count in ES index
		try {
			$query          = self::query_objects( $query_args, $indexable->slug );
			$formatted_args = $indexable->format_args( $query->query_vars, $query );

			// Get exact total count since Elasticsearch default stops at 10,000.
			$formatted_args['track_total_hits'] = true;

			$es_result = $indexable->query_es( $formatted_args, $query->query_vars );
		} catch ( \Exception $e ) {
			$source = method_exists( $e, 'get_error_message' ) ? $e->get_error_message() : $e->getMessage();
			return new WP_Error( 'es_query_error', sprintf( 'failure querying ES: %s #vip-search', $source ) );
		}

		// There is not other useful information out of query_es(): it just returns false in case of failure.
		// This may be due to different causes, e.g. index not existing or incorrect connection parameters.
		if ( ! $es_result ) {
			return new WP_Error( 'es_query_error', 'failure querying ES. #vip-search' );
		}

		return (int) $es_result['found_documents']['value'];
	}

	/**
	 * Validate DB and ES index users counts
	 *
	 * @return array Array containing entity (post/user), type (N/A), error, ES count, DB count, difference
	 */
	public static function validate_index_users_count( $options = array() ) {
		$users = Indexables::factory()->get( 'user' );

		// Indexables::factory()->get() returns boolean|array
		// False is returned in case of error
		if ( ! $users ) {
			return new WP_Error( 'es_users_query_error', 'failure retrieving user indexable from ES #vip-search' );
		}

		$search = \Automattic\VIP\Search\Search::instance();

		if ( $options['index_version'] ) {
			$version_result = $search->versioning->set_current_version_number( $users, $options['index_version'] );

			if ( is_wp_error( $version_result ) ) {
				return $version_result;
			}
		}

		$index_version = $search->versioning->get_current_version_number( $users );

		$query_args = [
			'order' => 'asc',
		];

		$result = ( new self() )->validate_index_entity_count( $query_args, $users );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'es_users_query_error', sprintf( 'failure retrieving users from ES: %s #vip-search', $result->get_error_message() ) );
		}

		$result['index_version'] = $index_version;

		$search->versioning->reset_current_version_number( $users );

		return array( $result );
	}

	/**
	 * Validate DB and ES index post counts
	 *
	 * @return array Array containing entity (post/user), type (N/A), error, ES count, DB count, difference
	 */
	public static function validate_index_posts_count( $options = array() ) {
		// Get indexable objects
		$posts = Indexables::factory()->get( 'post' );

		// Indexables::factory()->get() returns boolean|array
		// False is returned in case of error
		if ( ! $posts ) {
			return new WP_Error( 'es_users_query_error', 'failure retrieving post indexable from ES #vip-search' );
		}

		$post_types = $posts->get_indexable_post_types();

		$results = [];

		$search = \Automattic\VIP\Search\Search::instance();

		if ( $options['index_version'] ) {
			$version_result = $search->versioning->set_current_version_number( $posts, $options['index_version'] );

			if ( is_wp_error( $version_result ) ) {
				return $version_result;
			}
		}

		$index_version = $search->versioning->get_current_version_number( $posts );

		$health = new self();
		foreach ( $post_types as $post_type ) {
			$post_statuses = Indexables::factory()->get( 'post' )->get_indexable_post_status();

			$query_args = [
				'post_type'   => $post_type,
				'post_status' => array_values( $post_statuses ),
			];

			$result = $health->validate_index_entity_count( $query_args, $posts );

			// In case of error skip to the next post type
			// Not returning an error, otherwise there is no visibility on other post types
			if ( is_wp_error( $result ) ) {
				$result = [
					'entity'        => $posts->slug,
					'type'          => $post_type,
					'error'         => $result->get_error_message(),
					'index_version' => $index_version,
				];
			}

			$result['index_version'] = $index_version;

			$results[] = $result;

		}

		$search->versioning->reset_current_version_number( $posts );

		return $results;
	}

	/**
	 * Validate DB and ES index post content
	 *
	 * @return array Array containing counts and ids of posts with inconsistent content
	 */
	public function validate_index_posts_content( $start_post_id = 1, $last_post_id = null, $batch_size, $max_diff_size, $silent, $inspect = false, $do_not_heal = false ) {
		if ( $this->is_validate_content_ongoing() ) {
			return new WP_Error( 'content_validation_already_ongoing', 'Content validation is already ongoing' );
		}
		// If batch size value NOT a numeric value over 0 but less than or equal to PHP_INT_MAX, reset to default
		//     Otherwise, turn it into an int
		if ( ! is_numeric( $batch_size ) || 0 >= $batch_size || $batch_size > PHP_INT_MAX ) {
			$batch_size = self::CONTENT_VALIDATION_BATCH_SIZE;
		} else {
			$batch_size = intval( $batch_size );
		}

		// If max diff size NOT an int over 0, reset to default
		//     Otherwise convert max diff size to bytes
		if ( ! is_numeric( $max_diff_size ) || 0 > $max_diff_size || $max_diff_size > PHP_INT_MAX ) {
			$max_diff_size = self::CONTENT_VALIDATION_MAX_DIFF_SIZE;
		} else {
			$max_diff_size = intval( $max_diff_size );
		}

		// Get indexable objects
		$indexable = $this->indexables->get( 'post' );

		// Indexables::get() returns boolean|array
		// False is returned in case of error
		if ( ! $indexable ) {
			return new WP_Error( 'es_posts_query_error', 'Failure retrieving post indexable #vip-search' );
		}

		$is_cli = defined( 'WP_CLI' ) && WP_CLI;

		$results = [];

		// To fully validate the index, we have to check batches of post IDs
		// to compare the values in the DB with the index (and catch any that don't exist in either)
		// The most efficient way to do this is to iterate through all post IDs, which solves
		// high-offset performance problems and catches objects in the index that aren't in the DB
		$dynamic_last_post_id = false;

		if ( ! $last_post_id ) {
			$last_post_id = self::get_last_post_id();

			$dynamic_last_post_id = true;
		}

		do {
			$this->reset_validate_content_ongoing();

			$next_batch_post_id = $start_post_id + $batch_size;

			if ( $last_post_id < $next_batch_post_id ) {
				$next_batch_post_id = $last_post_id + 1;
			}

			if ( $is_cli && ! $silent ) {
				echo sprintf( 'Validating posts %d - %d', $start_post_id, $next_batch_post_id - 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			$result = $this->validate_index_posts_content_batch( $indexable, $start_post_id, $next_batch_post_id, $inspect );

			if ( is_wp_error( $result ) ) {
				$result['errors'] = array( sprintf( 'batch %d - %d (entity: %s) error: %s', $start_post_id, $next_batch_post_id - 1, $indexable->slug, $result->get_error_message() ) );
			} elseif ( count( $result ) && ! $do_not_heal ) {
					self::reconcile_diff( $result );
			}

			$results = array_merge( $results, $result );

			// Limit $results size
			if ( count( $results ) > $max_diff_size && ( $is_cli && ! $silent ) ) {
				echo sprintf( "...%s\n", \WP_CLI::colorize( '🛑' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				$error = new WP_Error( 'diff-size-limit-reached', sprintf( 'Reached diff size limit of %d elements, aborting', $max_diff_size ) );

				$error->add_data( $results, 'diff' );

				$this->delete_validate_content_ongoing();

				return $error;
			}

			$start_post_id += $batch_size;

			if ( $dynamic_last_post_id ) {
				// Requery for the last post id after each batch b/c the site is probably growing
				// while this runs
				$last_post_id = self::get_last_post_id();
			}

			// Cleanup WordPress object cache to keep memory usage under control
			vip_reset_local_object_cache();

			if ( $is_cli && ! $silent ) {
				echo sprintf( "...%s %s\n", empty( $result ) ? '✓' : '✘', $do_not_heal || empty( $result ) ? '' : '(attempted to reconcile)' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} while ( $start_post_id <= $last_post_id );

		$this->delete_validate_content_ongoing();

		return $results;
	}

	public function is_validate_content_ongoing(): bool {
		$is_locked = get_transient( self::CONTENT_VALIDATION_LOCK_NAME, false );

		return (bool) $is_locked;
	}

	public function reset_validate_content_ongoing() {
		set_transient( self::CONTENT_VALIDATION_LOCK_NAME, true, self::CONTENT_VALIDATION_LOCK_TIMEOUT );
	}

	public function delete_validate_content_ongoing() {
		delete_transient( self::CONTENT_VALIDATION_LOCK_NAME );
	}

	public function validate_index_posts_content_batch( $indexable, $start_post_id, $next_batch_post_id, $inspect ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status FROM $wpdb->posts WHERE ID >= %d AND ID < %d", $start_post_id, $next_batch_post_id ) );

		$post_types    = $indexable->get_indexable_post_types();
		$post_statuses = $indexable->get_indexable_post_status();

		// First we need to see identify which posts are actually expected in the index, by checking the same filters that
		// are used in ElasticPress\Indexable\Post\SyncManager::action_sync_on_update()
		$expected_post_rows = self::filter_expected_post_rows( $rows, $post_types, $post_statuses );

		$document_ids = self::get_document_ids_for_batch( $start_post_id, $next_batch_post_id - 1 );

		// Grab all of the documents from ES
		$documents = $indexable->multi_get( $document_ids );

		// Filter out any that weren't found
		$documents = array_filter( $documents, function( $document ) {
			return ! is_null( $document );
		} );

		$found_post_ids     = wp_list_pluck( $expected_post_rows, 'ID' );
		$found_document_ids = wp_list_pluck( $documents, 'ID' );

		$diffs = $inspect ? self::get_missing_docs_or_posts_diff( $found_post_ids, $found_document_ids )
		                  : self::simplified_get_missing_docs_or_posts_diff( $found_post_ids, $found_document_ids ); // phpcs:ignore Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed

		// Compare each indexed document with what it _should_ be if it were re-indexed now
		foreach ( $documents as $document ) {
			$prepared_document = $indexable->prepare_document( $document['post_id'] );

			$diff = $inspect ? self::diff_document_and_prepared_document( $document, $prepared_document )
			                 : self::simplified_diff_document_and_prepared_document( $document, $prepared_document ); // phpcs:ignore Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed

			if ( $diff ) {
				$key           = self::get_post_key( $document['ID'] );
				$diffs[ $key ] = $inspect ? $diff
				                          : self::simplified_format_post_diff( $document['ID'], 'inconsistent' ); // phpcs:ignore Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed
			}
		}

		return $diffs;
	}

	public static function simplified_get_missing_docs_or_posts_diff( $found_post_ids, $found_document_ids ) {
		$diffs = [];

		// What's missing in ES?
		$missing_from_index = array_diff( $found_post_ids, $found_document_ids );

		// If anything is missing from index, record it
		if ( 0 < count( $missing_from_index ) ) {
			foreach ( $missing_from_index as $post_id ) {
				$key           = self::get_post_key( $post_id );
				$diffs[ $key ] = self::simplified_format_post_diff( $post_id, 'missing_from_index' );
			}
		}

		// What's in ES but shouldn't be?
		$extra_in_index = array_diff( $found_document_ids, $found_post_ids );

		// If anything is in the index that shouldn't be, record it
		if ( 0 < count( $extra_in_index ) ) {
			foreach ( $extra_in_index as $document_id ) {
				$key           = self::get_post_key( $document_id );
				$diffs[ $key ] = self::simplified_format_post_diff( $document_id, 'extra_in_index' );
			}
		}

		return $diffs;
	}

	public static function get_missing_docs_or_posts_diff( $found_post_ids, $found_document_ids ) {
		$diffs = [];

		// What's missing in ES?
		$missing_from_index = array_diff( $found_post_ids, $found_document_ids );

		// If anything is missing from index, record it
		if ( 0 < count( $missing_from_index ) ) {
			foreach ( $missing_from_index as $post_id ) {
				$diffs[ 'post_' . $post_id ] = array(
					'existence' => array(
						'expected' => sprintf( 'Post %d to be indexed', $post_id ),
						'actual'   => null,
					),
				);
			}
		}

		// What's in ES but shouldn't be?
		$extra_in_index = array_diff( $found_document_ids, $found_post_ids );

		// If anything is in the index that shouldn't be, record it
		if ( 0 < count( $extra_in_index ) ) {
			foreach ( $extra_in_index as $document_id ) {
				// Grab the actual doc from
				$diffs[ 'post_' . $document_id ] = array(
					'existence' => array(
						'expected' => null,
						'actual'   => sprintf( 'Post %d is currently indexed', $document_id ),
					),
				);
			}
		}

		return $diffs;
	}

	public static function filter_expected_post_rows( $rows, $post_types, $post_statuses ) {
		$filtered = array_filter( $rows, function( $row ) use ( $post_types, $post_statuses ) {
			if ( ! in_array( $row->post_type, $post_types, true ) ) {
				return false;
			}

			if ( ! in_array( $row->post_status, $post_statuses, true ) ) {
				return false;
			}

			$skipped = apply_filters( 'ep_post_sync_kill', false, $row->ID, $row->ID );

			return ! $skipped;
		} );

		return $filtered;
	}

	public static function simplified_diff_document_and_prepared_document( $document, $prepared_document ) {
		foreach ( $document as $key => $value ) {
			if ( in_array( $key, self::DOCUMENT_IGNORED_KEYS, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$recursive_diff = self::simplified_diff_document_and_prepared_document( $document[ $key ], $prepared_document[ $key ] );
			} elseif ( $prepared_document[ $key ] != $document[ $key ] ) { // Intentionally weak comparison b/c some types like doubles don't translate to JSON
				return true;
			}
		}

		return false;
	}

	public static function diff_document_and_prepared_document( $document, $prepared_document ) {
		$diff = [];

		foreach ( $document as $key => $value ) {
			if ( in_array( $key, self::DOCUMENT_IGNORED_KEYS, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$recursive_diff = self::diff_document_and_prepared_document( $document[ $key ], $prepared_document[ $key ] );

				if ( ! empty( $recursive_diff ) ) {
					$diff[ $key ] = $recursive_diff;
				}
			} elseif ( $prepared_document[ $key ] != $document[ $key ] ) { // Intentionally weak comparison b/c some types like doubles don't translate to JSON
				$diff[ $key ] = array(
					'expected' => $prepared_document[ $key ],
					'actual'   => $document[ $key ],
				);
			}
		}

		if ( empty( $diff ) ) {
			return null;
		}

		return $diff;
	}

	/**
	 * Iterate over an array of inconsistencies and address accordingly.
	 *
	 * If an object is missing from the index or inconsistent - add it to the queue for the sweep.
	 *
	 * If an object is missing from the DB, remove it from the index.
	 *
	 * @param array $diff array of inconsistenices in the following shape: [ id => string, type => string (Indexable), issue => <missing_from_index|extra_in_index|inconsistent> ].
	 */
	public static function reconcile_diff( array $diff ) {
		foreach ( $diff as $key => $obj_to_reconcile ) {
			switch ( $obj_to_reconcile['issue'] ) {
				case 'missing_from_index':
				case 'inconsistent':
					\Automattic\VIP\Search\Search::instance()->queue->queue_object( $obj_to_reconcile['id'], $obj_to_reconcile['type'] );
					break;
				case 'extra_in_index':
					\ElasticPress\Indexables::factory()->get( 'post' )->delete( $obj_to_reconcile['id'], false );
					break;
			}
		}
	}

	public static function get_last_post_id() {
		global $wpdb;

		$last = $wpdb->get_var( "SELECT MAX( `ID` ) FROM $wpdb->posts" );

		return (int) $last;
	}

	public static function get_document_ids_for_batch( $start_post_id, $last_post_id ) {
		return range( $start_post_id, $last_post_id );
	}

	/**
	 * Helper function to wrap WP_*Query
	 *
	 * @since   1.0.0
	 * @access  private
	 * @param array $query_args Valid WP_Query criteria, mandatory fields as in following example:
	 * $query_args = [
	 *      'post_type' => $post_type,
	 *      'post_status' => array( $post_statuses )
	 * ];
	 *
	 * @param string $type Type (Slug) of the objects to be searched (should be either 'user' or 'post')
	 * @return WP_Query
	 */
	private static function query_objects( array $query_args, string $type ) {
		if ( 'user' === $type ) {
			return new WP_User_Query( $query_args );
		}
		return new WP_Query( $query_args );
	}

	private static function simplified_format_post_diff( $id, $issue ) {
		return array(
			'id'    => $id,
			'type'  => 'post',
			'issue' => $issue,
		);
	}

	private static function get_post_key( $id ) {
		return sprintf( '%s_%d', 'post', $id );
	}
}
