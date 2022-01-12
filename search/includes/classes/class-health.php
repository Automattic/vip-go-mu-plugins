<?php

namespace Automattic\VIP\Search;

use \ElasticPress\Indexables as Indexables;

use \WP_Query as WP_Query;
use \WP_User_Query as WP_User_Query;
use \WP_Error as WP_Error;

class Health {
	const CONTENT_VALIDATION_BATCH_SIZE        = 500;
	const CONTENT_VALIDATION_MAX_DIFF_SIZE     = 1000;
	const CONTENT_VALIDATION_LOCK_NAME         = 'vip_search_content_validation_lock';
	const CONTENT_VALIDATION_LOCK_TIMEOUT      = 900; // 15 min
	const CONTENT_VALIDATION_PROCESS_OPTION    = 'vip_search_content_validation_process_post_id';
	const DOCUMENT_IGNORED_KEYS                = array(
		// This field is proving problematic to reliably diff due to differences in the filters
		// that run during normal indexing and this validator
		'post_content_filtered',

		// Meta fields from EP's "magic" formatting, which is non-deterministic and impossible to validate
		'datetime',
		'date',
		'time',
	);
	const INDEX_SETTINGS_HEALTH_MONITORED_KEYS = array(
		'index.max_result_window',
		'index.number_of_replicas',
		'index.number_of_shards',
		'index.routing.allocation.include.dc',
	);
	const INDEX_SETTINGS_HEALTH_AUTO_HEAL_KEYS = array(
		'index.max_result_window',
		'index.number_of_replicas',
		'index.routing.allocation.include.dc',
	);

	const REINDEX_JOB_DEFAULT_PRIORITY = 15;

	/**
	 * Instance of Search class
	 *
	 * Useful for overriding (dependency injection) for tests
	 */
	public $search;

	public function __construct( \Automattic\VIP\Search\Search $search ) {
		$this->search     = $search;
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
			'reason'   => 'N/A',
			'db_total' => 'N/A',
			'es_total' => 'N/A',
			'diff'     => 'N/A',
		];

		if ( 'N/A' === $result['type'] && isset( $query_args['type'] ) ) {
			$result['type'] = $query_args['type'];
		}

		if ( ! $indexable->index_exists() ) {
			// If index doesnt exist and we will skip the rest of the check
			$result['skipped'] = true;
			$result['reason']  = 'index-not-found';
			return $result;
		}

		$es_total = $this->get_index_entity_count_from_elastic_search( $query_args, $indexable );
		if ( is_wp_error( $es_total ) ) {
			return $es_total;
		}

		if ( 0 === $es_total ) {
			// If there is 0 docs in ES, we assume it wasnet initialized and we will skip the rest of the check
			$result['skipped']  = true;
			$result['reason']   = 'index-empty';
			$result['es_total'] = 0;
			return $result;
		}

		try {
			// Get total count in DB
			$db_result = $indexable->query_db( $query_args );

			$db_total = (int) $db_result['total_objects'];
		} catch ( \Exception $e ) {
			return new WP_Error( 'db_query_error', sprintf( 'failure querying the DB: %s #vip-search', $e->getMessage() ) );
		}

		$diff = 0;
		if ( $db_total !== $es_total ) {
			$diff = $es_total - $db_total;
		}

		$result['db_total'] = $db_total;
		$result['es_total'] = $es_total;
		$result['diff']     = $diff;

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
			// We don't really need any of the fields.
			$formatted_args['_source'] = false;

			$es_result = $indexable->query_es( $formatted_args, $query->query_vars );
		} catch ( \Exception $e ) {
			return new WP_Error( 'es_query_error', sprintf( 'failure querying ES: %s #vip-search', $e->getMessage() ) );
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

		$result = ( new self( $search ) )->validate_index_entity_count( $query_args, $users );

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

		$health = new self( $search );

		foreach ( $post_types as $post_type ) {
			$post_indexable = Indexables::factory()->get( 'post' );
			$post_statuses  = $post_indexable->get_indexable_post_status();

			$query_args = [
				'post_type'      => $post_type,
				'post_status'    => array_values( $post_statuses ),
				// Force fetching just one post, otherwise the query may get killed on large datasets.
				// This works for at least ten million records in posts table.
				'posts_per_page' => 1,
			];

			$result = $health->validate_index_entity_count( $query_args, $posts );

			// In case of error skip to the next post type
			// Not returning an error, otherwise there is no visibility on other post types
			if ( is_wp_error( $result ) ) {
				$result = [
					'entity' => $posts->slug,
					'type'   => $post_type,
					'error'  => $result->get_error_message(),
				];
			}

			$result['index_version'] = $index_version;
			$result['index_name']    = $post_indexable->get_index_name();

			$results[] = $result;

		}

		$search->versioning->reset_current_version_number( $posts );

		return $results;
	}

	/**
	 * Validate DB and ES index terms counts
	 *
	 * @return array Array containing entity (term), type (N/A), error, ES count, DB count, difference
	 */
	public static function validate_index_terms_count( $options = array() ) {
		// Get indexable object
		$terms = Indexables::factory()->get( 'term' );

		// Indexables::factory()->get() returns boolean|array
		// False is returned in case of error
		if ( ! $terms ) {
			return new WP_Error( 'es_terms_query_error', 'failure retrieving term indexable from ES #vip-search' );
		}

		$search = \Automattic\VIP\Search\Search::instance();

		if ( $options['index_version'] ) {
			$version_result = $search->versioning->set_current_version_number( $terms, $options['index_version'] );

			if ( is_wp_error( $version_result ) ) {
				return $version_result;
			}
		}

		$index_version = $search->versioning->get_current_version_number( $terms );

		$query_args = [
			'order' => 'asc',
		];

		$result = ( new self( $search ) )->validate_index_entity_count( $query_args, $terms );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'es_terms_query_error', sprintf( 'failure retrieving terms from ES: %s #vip-search', $result->get_error_message() ) );
		}

		$result['index_version'] = $index_version;

		$search->versioning->reset_current_version_number( $terms );

		return array( $result );
	}

	/**
	 * Validate DB and ES index comments counts
	 *
	 * @return array Array containing entity (comment), type (comment,review), error, ES count, DB count, difference
	 */
	public static function validate_index_comments_count( $options = array() ) {
		// Get indexable object
		$comments = Indexables::factory()->get( 'comment' );

		$results = [];

		// Indexables::factory()->get() returns boolean|array
		// False is returned in case of error
		if ( ! $comments ) {
			return new WP_Error( 'es_comments_query_error', 'failure retrieving comment indexable from ES #vip-search' );
		}

		$search = \Automattic\VIP\Search\Search::instance();

		if ( $options['index_version'] ) {
			$version_result = $search->versioning->set_current_version_number( $comments, $options['index_version'] );

			if ( is_wp_error( $version_result ) ) {
				return $version_result;
			}
		}

		$index_version = $search->versioning->get_current_version_number( $comments );

		$comment_types = $comments->get_indexable_comment_types();

		$health = new self( $search );

		foreach ( $comment_types as $comment_type ) {

			$query_args = [
				'type'     => $comment_type,
				// Force fetching just one comment, otherwise the query may get killed on large datasets.
				// This works for at least ten million records in comments table.
				'per_page' => 1,
				// Empty arguments to silence warnings
				'karma'    => '',
				'parent'   => '',

			];

			$result = $health->validate_index_entity_count( $query_args, $comments );

			// In case of error skip to the next comment type
			// Not returning an error, otherwise there is no visibility on other comment types
			if ( is_wp_error( $result ) ) {
				$result = [
					'entity' => $comments->slug,
					'type'   => $comment_type,
					'error'  => $result->get_error_message(),
				];
			}

			$result['index_version'] = $index_version;
			$result['index_name']    = $comments->get_index_name();

			$results[] = $result;
		}

		$search->versioning->reset_current_version_number( $comments );

		return $results;
	}

	/**
	 * Validate DB and ES index post content
	 *
	 * ## OPTIONS
	 *
	 * [inspect]
	 * : Optional gives more verbose output for index inconsistencies
	 *
	 * [start_post_id=<int>]
	 * : Optional starting post id (defaults to 1)
	 *
	 * [last_post_id=<int>]
	 * : Optional last post id to check
	 *
	 * [batch_size=<int>]
	 * : Optional batch size (max is 5000)
	 *
	 * [max_diff_size=<int>]
	 * : Optional max count of diff before exiting
	 *
	 * [do_not_heal]
	 * : Optional Don't try to correct inconsistencies
	 *
	 * [silent]
	 * : Optional silences all non-error output except for the final results
	 *
	 * [force_parallel_execution]
	 * : Optional Force execution even if the process is already ongoing
	 *
	 *
	 * @param array $options list of options
	 *
	 *
	 * @return array Array containing counts and ids of posts with inconsistent content
	 */
	public function validate_index_posts_content( $options ) {
		$start_post_id            = $options['start_post_id'] ?? 1;
		$last_post_id             = $options['last_post_id'] ?? null;
		$batch_size               = $options['batch_size'] ?? null;
		$max_diff_size            = $options['max_diff_size'] ?? null;
		$silent                   = isset( $options['silent'] );
		$inspect                  = isset( $options['inspect'] );
		$do_not_heal              = isset( $options['do_not_heal'] );
		$force_parallel_execution = isset( $options['force_parallel_execution'] );

		$process_parallel_execution_lock = ! $force_parallel_execution;
		// We only work with process if we can guarantee no parallel execution and user did't pick specific start_post_id to avoid unexpected overwriting of that.
		$track_process = ( ! $start_post_id || 1 === $start_post_id ) && ! $force_parallel_execution;

		if ( $process_parallel_execution_lock && $this->is_validate_content_ongoing() ) {
			return new WP_Error( 'content_validation_already_ongoing', 'Content validation is already ongoing' );
		}
		$interrupted_start_post_id = $this->get_validate_content_abandoned_process();
		if ( $track_process && $interrupted_start_post_id ) {
			$start_post_id = $interrupted_start_post_id;
		}

		// If batch size value NOT a numeric value over 0 but less than or equal to PHP_INT_MAX, reset to default
		//     Otherwise, turn it into an int
		if ( ! is_numeric( $batch_size ) || 0 >= $batch_size || $batch_size > PHP_INT_MAX ) {
			$batch_size = self::CONTENT_VALIDATION_BATCH_SIZE;
		} else {
			$batch_size = min( 5000, intval( $batch_size ) );
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

		// $this->indexables->get() returns boolean|array
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
			if ( $process_parallel_execution_lock ) {
				$this->set_validate_content_lock();
			}
			if ( $track_process ) {
				$this->update_validate_content_process( $start_post_id );
			}

			$next_batch_post_id = $start_post_id + $batch_size;

			if ( $last_post_id < $next_batch_post_id ) {
				$next_batch_post_id = $last_post_id + 1;
			}

			if ( $is_cli && ! $silent ) {
				echo sprintf( 'Validating posts %d - %d', $start_post_id, $next_batch_post_id - 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			/** @var array|WP_Error */
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

				if ( $process_parallel_execution_lock ) {
					$this->remove_validate_content_lock();
				}
				if ( $track_process ) {
					$this->remove_validate_content_process();
				}

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
			if ( $is_cli && $silent ) {
				// To prevent continuous hammering of clusters.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
				sleep( mt_rand( 2, 5 ) );
			}
		} while ( $start_post_id <= $last_post_id );

		if ( $process_parallel_execution_lock ) {
			$this->remove_validate_content_lock();
		}
		if ( $track_process ) {
			$this->remove_validate_content_process();
		}

		return $results;
	}

	/**
	 * Method checks if there is an abandoned process stored. This should only happen when the validate_contents process exits unexpectedly.
	 * In all other cases the process information should have been removed at the end of processing. This tool enables us
	 * to potentially pick-up where we left of on long running validate contents that got interrupted.
	 *
	 * @return int|bool returns the ID of the first post in a batch that was process when process was updated OR false if no such values is saved.
	 */
	public function get_validate_content_abandoned_process() {
		return get_option( self::CONTENT_VALIDATION_PROCESS_OPTION );
	}

	public function update_validate_content_process( $next_post_id ) {
		update_option( self::CONTENT_VALIDATION_PROCESS_OPTION, $next_post_id, false );
	}

	public function remove_validate_content_process() {
		delete_option( self::CONTENT_VALIDATION_PROCESS_OPTION );
	}

	public function is_validate_content_ongoing(): bool {
		$is_locked = get_transient( self::CONTENT_VALIDATION_LOCK_NAME, false );

		return (bool) $is_locked;
	}

	public function set_validate_content_lock() {
		set_transient( self::CONTENT_VALIDATION_LOCK_NAME, true, self::CONTENT_VALIDATION_LOCK_TIMEOUT );
	}

	public function remove_validate_content_lock() {
		delete_transient( self::CONTENT_VALIDATION_LOCK_NAME );
	}

	public function validate_index_posts_content_batch( $indexable, $start_post_id, $next_batch_post_id, $inspect ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
		// Filter out any that are extra or missing in index
		$documents = array_filter( $documents, function( $document ) use ( $diffs ) {
			$key = self::get_post_key( $document['ID'] );
			return ! array_key_exists( $key, $diffs );
		} );

		// Compare each indexed document with what it _should_ be if it were re-indexed now
		foreach ( $documents as $document ) {
			$prepared_document = $indexable->prepare_document( $document['post_id'] );

			$diff = $inspect ? self::diff_document_and_prepared_document( $document, $prepared_document )
			                : self::simplified_diff_document_and_prepared_document( $document, $prepared_document ); // phpcs:ignore Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed

			if ( $diff ) {
				$key           = self::get_post_key( $document['ID'] );
				$diffs[ $key ] = $inspect ? $diff : self::simplified_format_post_diff( $document['ID'], 'inconsistent' );
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
		$checked_keys = [];

		foreach ( $document as $key => $value ) {
			$checked_keys[ $key ] = true;
			if ( in_array( $key, self::DOCUMENT_IGNORED_KEYS, true ) ) {
				continue;
			}

			$prepared_value = $prepared_document[ $key ] ?? null;
			if ( is_array( $value ) ) {
				$recursive_diff = self::simplified_diff_document_and_prepared_document( $value, is_array( $prepared_value ) ? $prepared_value : [] );
				if ( $recursive_diff ) {
					return true;
				}
			} elseif ( $prepared_value != $value ) { // Intentionally weak comparison b/c some types like doubles don't translate to JSON
				return true;
			}
		}

		// Check that there is no missing key that would only be on $prepared_document
		foreach ( $prepared_document as $key => $value ) {
			if ( ! array_key_exists( $key, $checked_keys ) ) {
				return true;
			}
		}

		return false;
	}

	public static function diff_document_and_prepared_document( $document, $prepared_document ) {
		$diff         = [];
		$checked_keys = [];

		foreach ( $document as $key => $value ) {
			$checked_keys[ $key ] = true;
			if ( in_array( $key, self::DOCUMENT_IGNORED_KEYS, true ) ) {
				continue;
			}

			$prepared_value = $prepared_document[ $key ] ?? null;
			if ( is_array( $value ) ) {
				$recursive_diff = self::diff_document_and_prepared_document( $value, is_array( $prepared_value ) ? $prepared_value : [] );

				if ( ! empty( $recursive_diff ) ) {
					$diff[ $key ] = $recursive_diff;
				}
			} elseif ( $prepared_value != $value ) { // Intentionally weak comparison b/c some types like doubles don't translate to JSON
				$diff[ $key ] = array(
					'expected' => $prepared_document[ $key ] ?? null,
					'actual'   => $value,
				);
			}
		}

		// Check that there is no missing key that would only be on $prepared_document
		foreach ( $prepared_document as $key => $value ) {
			if ( ! array_key_exists( $key, $checked_keys ) ) {
				$diff[ $key ] = array(
					'expected' => $value,
					'actual'   => null,
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
		foreach ( $diff as $obj_to_reconcile ) {
			switch ( $obj_to_reconcile['issue'] ) {
				case 'missing_from_index':
				case 'inconsistent':
					/**
					 * Filter to determine the priority of the reindex job
					 *
					 * @param int $priority         Job priority
					 * @param int $object_id        Object ID
					 * @param string $object_type   Object type
					 * @return int                  Job priority
					 */
					$priority = apply_filters( 'vip_healthcheck_reindex_priority', self::REINDEX_JOB_DEFAULT_PRIORITY, $obj_to_reconcile['id'], $obj_to_reconcile['type'] );
					\Automattic\VIP\Search\Search::instance()->queue->queue_object( $obj_to_reconcile['id'], $obj_to_reconcile['type'], [ 'priority' => $priority ] );
					break;
				case 'extra_in_index':
					\ElasticPress\Indexables::factory()->get( 'post' )->delete( $obj_to_reconcile['id'], false );
					break;
			}
		}
	}

	/**
	 * Get the last post ID from the database.
	 * 
	 * @return int $last_db_id The last post ID from the database.
	 */
	public static function get_last_db_post_id() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$last_db_id = $wpdb->get_var( "SELECT MAX( `ID` ) FROM $wpdb->posts" );

		return (int) $last_db_id;
	}

	/**
	 * Get the last post ID from Elasticsearch.
	 * 
	 * @return int $last_es_id The last post ID from ES.
	 */
	public static function get_last_es_post_id() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		if ( $indexable ) {
			$query_args     = [
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'desc',
				'fields'         => 'ids',
			];
			$query          = self::query_objects( $query_args, 'post' );
			$formatted_args = $indexable->format_args( $query->query_vars, $query );
			$es_result      = $indexable->query_es( $formatted_args, $query->query_vars );
		}
		$last_es_id = $es_result['documents'][0]['post_id'] ?? false;

		return (int) $last_es_id;
	}

	/**
	 * Get the latter post ID between the database and Elasticsearch.
	 * 
	 * @return int $last The latter post ID.
	 */
	public static function get_last_post_id() {
		$last_db_id = self::get_last_db_post_id();
		$last_es_id = self::get_last_es_post_id();
		$last       = max( $last_db_id, $last_es_id );
		
		return $last;
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

	public function get_index_settings_health_for_all_indexables() {
		// For each indexable, we want to ensure that the desired index settings match the actual index settings
		$indexables = \ElasticPress\Indexables::factory()->get_all();

		if ( ! is_array( $indexables ) ) {
			$message = sprintf( 'Unable to find indexables to check index settings on %s for environment %d', home_url(), FILES_CLIENT_SITE_ID );

			return new \WP_Error( 'no-indexables-found', $message );
		}

		$unhealthy = array();

		foreach ( $indexables as $indexable ) {
			$diff = $this->get_index_versions_settings_diff_for_indexable( $indexable );

			if ( is_wp_error( $diff ) ) {
				$unhealthy[ $indexable->slug ] = $diff;

				continue;
			}

			if ( empty( $diff ) ) {
				continue;
			}

			$unhealthy[ $indexable->slug ] = $diff;
		}

		return $unhealthy;
	}

	public function get_index_versions_settings_diff_for_indexable( \ElasticPress\Indexable $indexable ) {
		$versions = $this->search->versioning->get_versions( $indexable );

		$diff = array();

		foreach ( $versions as $version ) {
			$version_result = $this->get_index_settings_diff_for_indexable( $indexable, array(
				'index_version' => $version['number'],
			) );

			if ( empty( $version_result ) ) {
				continue;
			}

			$diff[] = $version_result;
		}

		return $diff;
	}

	public function get_index_settings_diff_for_indexable( \ElasticPress\Indexable $indexable, $options = array() ) {
		if ( isset( $options['index_version'] ) ) {
			$version_result = $this->search->versioning->set_current_version_number( $indexable, $options['index_version'] );

			if ( is_wp_error( $version_result ) ) {
				return $version_result;
			}
		}

		$diff = [];

		if ( $indexable->index_exists() ) {
			$actual_settings = $indexable->get_index_settings();

			if ( is_wp_error( $actual_settings ) ) {
				$this->search->versioning->reset_current_version_number( $indexable );

				return $actual_settings;
			}

			$desired_settings = $indexable->build_settings();

			// We only monitor certain settings
			$actual_settings_to_check  = self::limit_index_settings_to_keys( $actual_settings, self::INDEX_SETTINGS_HEALTH_MONITORED_KEYS );
			$desired_settings_to_check = self::limit_index_settings_to_keys( $desired_settings, self::INDEX_SETTINGS_HEALTH_MONITORED_KEYS );

			$diff = self::get_index_settings_diff( $actual_settings_to_check, $desired_settings_to_check );
		}

		$result = [];
		if ( ! empty( $diff ) ) {
			$result = array(
				'index_version' => $options['index_version'] ?? 1,
				'index_name'    => $indexable->get_index_name(),
				'diff'          => $diff,
			);
		}

		$this->search->versioning->reset_current_version_number( $indexable );

		return $result;
	}

	public static function limit_index_settings_to_keys( $settings, $keys ) {
		// array_intersect_key() expects 2 associative arrays, so convert the allowed $keys to associative
		$assoc_keys = array_fill_keys( $keys, true );

		return array_intersect_key( $settings, $assoc_keys );
	}

	public static function get_index_settings_diff( array $actual_settings, array $desired_settings ) {
		$diff = array();

		foreach ( $desired_settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$recursive_diff = self::get_index_settings_diff( $actual_settings[ $key ], $desired_settings[ $key ] );

				if ( ! empty( $recursive_diff ) ) {
					$diff[ $key ] = $recursive_diff;
				}
			} elseif ( $actual_settings[ $key ] != $desired_settings[ $key ] ) { // Intentionally weak comparison b/c some types like doubles don't translate to JSON
				$diff[ $key ] = array(
					'expected' => $desired_settings[ $key ],
					'actual'   => $actual_settings[ $key ],
				);
			}
		}

		return $diff;
	}

	public function heal_index_settings_for_indexable( \ElasticPress\Indexable $indexable, array $options = array() ) {
		if ( isset( $options['index_version'] ) ) {
			$version_result = $this->search->versioning->set_current_version_number( $indexable, $options['index_version'] );

			if ( is_wp_error( $version_result ) ) {
				return $version_result;
			}
		}

		$desired_settings = $indexable->build_settings();

		// Limit to only the settings that we auto-heal
		$desired_settings_to_heal = self::limit_index_settings_to_keys( $desired_settings, self::INDEX_SETTINGS_HEALTH_AUTO_HEAL_KEYS );

		$result = $indexable->update_index_settings( $desired_settings_to_heal );

		$index_name    = $indexable->get_index_name();
		$index_version = $this->search->versioning->get_current_version_number( $indexable );

		$this->search->versioning->reset_current_version_number( $indexable );

		return array(
			'index_name'    => $index_name,
			'index_version' => $index_version,
			'result'        => $result,
		);
	}
}
