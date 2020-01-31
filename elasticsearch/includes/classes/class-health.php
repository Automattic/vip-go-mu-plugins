<?php

namespace Automattic\VIP\Elasticsearch;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;

use \WP_Query as WP_Query;
use \WP_User_Query as WP_User_Query;


class Health {
	/**
	 * Verify the difference in number for a given entity between the DB and ElasticSearch.
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
	public static function verify_index_entity_count( array $query_args, \ElasticPress\Indexable $indexable ) {
		try {
			// Get total count in DB
			$result = $indexable->query_db( $query_args );

			$db_total = (int) $result[ 'total_objects' ];
		} catch ( \Exception $e ) {
			return new WP_Error( 'db_query_error', sprintf( 'failure querying the DB: %s #vip-go-elasticsearch', $e->get_error_message() ) );
		}

		// Get total count in ES index
		try {
			$query = self::query_objects( $query_args, $indexable->slug );
			$formatted_args = $indexable->format_args( $query->query_vars, $query );
			$es_result = $indexable->query_es( $formatted_args, $query->query_vars );
		} catch ( \Exception $e ) {
			return new WP_Error( 'es_query_error', sprintf( 'failure querying ES: %s #vip-go-elasticsearch', $e->get_error_message() ) );
		}

		$diff = '';
		// There is not other useful information out of query_es(): it just returns false in case of failure
		if ( ! $es_result ) {
			$es_total = 'N/A';
			$msg = 'error while querying ElasticSearch.';
			return new WP_Error( 'es_query_error', 'failure querying ES. Hint: verify arguments format. #vip-go-elasticsearch' );
		}

		// Verify actual results
		$es_total = (int) $es_result[ 'found_documents' ][ 'value' ];

		if ( $db_total !== $es_total ) {
			$diff = sprintf( ', diff: %d', $es_total - $db_total );
		}

		return [ 'entity' => $indexable->slug, 'type' => ( array_key_exists( 'post_type', $query_args ) ? $query_args[ 'post_type' ] : 'N/A' ), 'db_total' => $db_total, 'es_total' => $es_total, 'diff' => $diff ];
	}

	/**
	 * Validate DB and ES index users counts
	 */
	public static function verify_index_users_count( $args, $assoc_args ) {
		$users = Indexables::factory()->get( 'user' );

		$query_args = [
			'order' => 'asc',
		];

		$result = self::verify_index_entity_count( $query_args, $users );
		if ( is_wp_error( $result ) ) {
			$result = [ 'entity' => $users->slug, 'type' => 'N/A', 'error' => $result->get_error_message() ];
		}
		return array( $result );
	}

	/**
	 * Validate DB and ES index post counts
	 */
	public static function verify_index_posts_count( $args, $assoc_args ) {
		// Get indexable objects
		$posts = Indexables::factory()->get( 'post' );

		$post_types = $posts->get_indexable_post_types();

		$results = [];

		foreach( $post_types as $post_type ) {
			$post_statuses = Indexables::factory()->get( 'post' )->get_indexable_post_status();

			$query_args = [
				'post_type' => $post_type,
				'post_status' => array_values( $post_statuses ),
			];

			$result = self::verify_index_entity_count( $query_args, $posts );

			// In case of error skip to the next post type
			if ( is_wp_error( $result ) ) {
				$result = [ 'entity' => $posts->slug, 'type' => $post_type, 'error' => $result->get_error_message() ];
			}

			$results[] = $result;

		}
		return $results;
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