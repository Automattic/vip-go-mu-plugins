<?php

namespace Automattic\VIP\Search;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;

use \WP_Query as WP_Query;
use \WP_User_Query as WP_User_Query;
use \WP_Error as WP_Error;

class Health {
	const CONTENT_VALIDATION_BATCH_SIZE = 100;

	/**
	 * Verify the difference in number for a given entity between the DB and the index.
	 * Entities can be either posts or users.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param array $query_args Valid WP_Query criteria, mandatory fields as in following example:
	 * $query_args = [
	 *		'post_type' => $post_type,
	 *		'post_status' => array( $post_statuses )
	 * ];
	 *
	 * @param mixed $indexable Instance of an ElasticPress Indexable Object to search on
	 * @return WP_Error|array
	 */
	public static function validate_index_entity_count( array $query_args, \ElasticPress\Indexable $indexable ) {
		try {
			// Get total count in DB
			$result = $indexable->query_db( $query_args );

			$db_total = (int) $result[ 'total_objects' ];
		} catch ( \Exception $e ) {
			return new WP_Error( 'db_query_error', sprintf( 'failure querying the DB: %s #vip-search', $e->get_error_message() ) );
		}

		// Get total count in ES index
		try {
			$query = self::query_objects( $query_args, $indexable->slug );
			$formatted_args = $indexable->format_args( $query->query_vars, $query );

			// Get exact total count since Elasticsearch default stops at 10,000.
			$formatted_args['track_total_hits'] = true;

			$es_result = $indexable->query_es( $formatted_args, $query->query_vars );
		} catch ( \Exception $e ) {
			return new WP_Error( 'es_query_error', sprintf( 'failure querying ES: %s #vip-search', $e->get_error_message() ) );
		}

		// There is not other useful information out of query_es(): it just returns false in case of failure.
		// This may be due to different causes, e.g. index not existing or incorrect connection parameters.
		if ( ! $es_result ) {
			$es_total = 'N/A';
			return new WP_Error( 'es_query_error', 'failure querying ES. #vip-search' );
		}

		// Verify actual results
		$es_total = (int) $es_result[ 'found_documents' ][ 'value' ];

		$diff = 0;
		if ( $db_total !== $es_total ) {
			$diff = $es_total - $db_total;
		}

		return [
			'entity' => $indexable->slug,
			'type' => ( array_key_exists( 'post_type', $query_args ) ? $query_args[ 'post_type' ] : 'N/A' ),
			'db_total' => $db_total,
			'es_total' => $es_total,
			'diff' => $diff,
		];
	}

	/**
	 * Validate DB and ES index users counts
	 *
	 * @return array Array containing entity (post/user), type (N/A), error, ES count, DB count, difference
	 */
	public static function validate_index_users_count() {
		$users = Indexables::factory()->get( 'user' );
		// Indexables::factory()->get() returns boolean|array
		// False is returned in case of error
		if ( ! $users ) {
			return new WP_Error( 'es_users_query_error', 'failure retrieving user documents from ES #vip-search' );
		}

		$query_args = [
			'order' => 'asc',
		];

		$result = self::validate_index_entity_count( $query_args, $users );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'es_users_query_error', sprintf( 'failure retrieving users from ES: %s #vip-search', $result->get_error_message() ) );
		}
		return array( $result );
	}

	/**
	 * Validate DB and ES index post counts
	 *
	 * @return array Array containing entity (post/user), type (N/A), error, ES count, DB count, difference
	 */
	public static function validate_index_posts_count() {
		// Get indexable objects
		$posts = Indexables::factory()->get( 'post' );

		// Indexables::factory()->get() returns boolean|array
		// False is returned in case of error
		if ( ! $posts ) {
			return new WP_Error( 'es_users_query_error', 'failure retrieving post documents from ES #vip-search' );
		}

		$post_types = $posts->get_indexable_post_types();

		$results = [];

		foreach( $post_types as $post_type ) {
			$post_statuses = Indexables::factory()->get( 'post' )->get_indexable_post_status();

			$query_args = [
				'post_type' => $post_type,
				'post_status' => array_values( $post_statuses ),
			];

			$result = self::validate_index_entity_count( $query_args, $posts );

			// In case of error skip to the next post type
			// Not returning an error, otherwise there is no visibility on other post types
			if ( is_wp_error( $result ) ) {
				$result = [
					'entity' => $posts->slug,
					'type' => $post_type,
					'error' => $result->get_error_message()
				];
			}

			$results[] = $result;

		}
		return $results;
	}

	/**
	 * Validate DB and ES index post content
	 *
	 * @return array Array containing counts and ids of posts with inconsistent content
	 */
	public static function validate_index_posts_content() {
		// Get indexable objects
		$indexable = Indexables::factory()->get( 'post' );

		// Indexables::factory()->get() returns boolean|array
		// False is returned in case of error
		if ( ! $indexable ) {
			return new WP_Error( 'es_posts_query_error', 'Failure retrieving post indexable #vip-search' );
		}

		$results = [];

		// To fully validate the index, we have to check batches of post IDs
		// to compare the values in the DB with the index (and catch any that don't exist in either)
		// The most efficient way to do this is to iterate through all post IDs, which solves
		// high-offset performance problems and catches objects in the index that aren't in the DB
		$start_post_id = 1;
		$last_post_id = self::get_last_post_id();

		do {
			$query_args = [
				'post_type' => $post_type,
				'post_status' => $post_statuses,
			];

			$results = validate_index_posts_content_batch( $indexable, $start_post_id );

			if ( is_wp_error( $result ) ) {
				$result = [
					'entity' => $indexable->slug,
					'start_post_id' => $start_post_id,
					'error' => $result->get_error_message()
				];
			}

			$results[] = $result;

			$start_post_id += self::CONTENT_VALIDATION_BATCH_SIZE;

			// Requery for the last post id after each batch b/c the site is probably growing
			// while this runs
			$last_post_id = self::get_last_post_id();
		} while ( $start_post_id > $last_post_id );

		return $results;
	}

	public static function validate_index_posts_content_batch( $indexable, $start_post_id ) {
		$sql = $wpdb->prepare( "SELECT ID, post_type, post_status FROM $wpdb->posts WHERE ID >= %d AND ID < %d", $start_post_id, self::CONTENT_VALIDATION_BATCH_SIZE );

		$rows = $wpdb->get_results( $sql );

		$post_types = $indexable->get_indexable_post_types();
		$post_statuses = $indexable->get_indexable_post_status();

		// First we need to see identify which posts are actually expected in the index, by checking the same filters that
		// are used in ElasticPress\Indexable\Post\SyncManager::action_sync_on_update()
		$expected_post_rows = array_filter( $rows, function( $row ) {
			if ( ! in_array( $row->post_type, $post_types ) ) {
				return false;
			}
			
			if ( ! in_array( $row->post_status, $post_statuses ) ) {
				return false;
			}

			$skipped = apply_filters( 'ep_post_sync_kill', false, $post->ID, $post->ID );

			return ! $skipped;
		} );

		$document_ids = self::get_document_ids_for_batch( $start_post_id );

		// Grab all of the documents from ES
		$documents = $indexable->multi_get( $document_ids );

		$diff = [];

		// Compare the docs with what's in the DB
		foreach( $expected_post_rows as $row ) {
			$prepared_document = $indexable->prepare_document( $row->ID );

			// TODO calculate and return the diff between the objects
		}

		return $diff;
	}

	public static function get_last_post_id() {
		global $wpdb;

		$last = $wpdb->get_var( "SELECT MAX( `ID` ) FROM $wpdb->posts" );

		return $last;
	}

	public static function get_document_ids_for_batch( $start_post_id ) {
		return range( $start_post_id, self::CONTENT_VALIDATION_BATCH_SIZE );
	}

	/**
	 * Helper function to wrap WP_*Query
	 *
	 * @since   1.0.0
	 * @access  private
	 * @param array $query_args Valid WP_Query criteria, mandatory fields as in following example:
	 * $query_args = [
	 *		'post_type' => $post_type,
	 *		'post_status' => array( $post_statuses )
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

}
