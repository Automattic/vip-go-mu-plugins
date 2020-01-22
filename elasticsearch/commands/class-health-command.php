<?php

namespace Automattic\VIP\Elasticsearch;

use \WP_CLI;
use \WP_CLI\Utils;
use \WP_Query as WP_Query;
use \ElasticPress\Indexables as Indexables;
use \ElasticPress\Features as Features;
use \ElasticPress\Elasticsearch as Elasticsearch;

/**
 * Commands to view and manage the health of VIP Go Elasticsearch indexes
 *
 * @package Automattic\VIP\Elasticsearch
 */
class Health_Command extends \WPCOM_VIP_CLI_Command {
	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		parent::__construct();
	}

	/**
	 * Validate DB and ES index counts for all objects
	 *
	 * ## OPTIONS
	 *
	 *
	 * ## EXAMPLES
	 *     wp vip-es health validate-counts
	 *
	 * @subcommand validate-counts
	 */
	public function validate_counts( $args, $assoc_args ) {
		$this->validate_posts_counts( $args, $assoc_args );
		$this->validate_users_count( $args, $assoc_args );
	}

	/**
	 * Validate DB and ES index post counts
	 *
	 * ## OPTIONS
	 *
	 *
	 * ## EXAMPLES
	 *     wp vip-es health validate-posts-count
	 *
	 * @subcommand validate-posts-count
	 */
	public function validate_posts_counts( $args, $assoc_args ) {
		// Get indexable objects
		$indexable = Indexables::factory()->get( 'post' );

		$post_types = $indexable->get_indexable_post_types();

		WP_CLI::line( sprintf( "Checking %d post types (%s)\n", count( $post_types ), implode( ', ', $post_types ) ) );

		foreach( $post_types as $post_type ) {
			$error = false;
			$es_conn_err = false;

			$query_args = [
				'post_type' => $post_type,
			];

			// Get total count in DB
			$result = $indexable->query_db( $query_args );

			$db_total = (int) $result[ 'total_objects' ];

			// Get total count in ES index
			$query = new WP_Query( $query_args );

			$formatted_args = $indexable->format_args( $query->query_vars, $query );

			$es_result = $indexable->query_es( $formatted_args, $query->query_vars );
			
			$diff = '';
			if ( ! $es_result ) {
				$es_total = 'N/A';
				$error = true;
				// Most likely an issue either connecting to ElasticSearch, or no index was found
				$es_conn_err = true;
				// Something is broken, bail instead of returning partial/incorrect data
				$msg = 'error connecting to Elasticsearch instance, or no index was found. Please verify your settings.';
				WP_CLI::error( $msg );
			}
			// Verify actual results
			$es_total = (int) $es_result[ 'found_documents' ][ 'value' ];

			if ( $db_total !== $es_total ) {
				$error = true;

				$diff = sprintf( ', diff: %d', $es_total - $db_total );
			}

			$icon = "\u{2705}"; // unicode check mark
			if ( $error ) {
				$icon = "\u{274C}"; // unicode cross mark
			}

			WP_CLI::line( sprintf( "%s %s (DB: %d, ES: %s%s)", $icon, $post_type, $db_total, $es_total, $diff ) );
		}

		WP_CLI::line( '' );

		if( $error ) {
			$msg = 'found inconsistent counts for post types.';
			WP_CLI::error( $msg );
		}

		WP_CLI::success( 'counts for public post types are all equal!' );
	}

	/**
	 * Validate DB and ES index users counts
	 *
	 * ## OPTIONS
	 *
	 *
	 * ## EXAMPLES
	 *     wp vip-es health validate-users-count
	 *
	 * @subcommand validate-users-count
	 */
	public function validate_users_count( $args, $assoc_args ) {
		$users = Indexables::factory()->get( 'user' );
		
		WP_CLI::line( sprintf( "Validating users count\n" ) );

		$error = false;
		$es_conn_err = false;

		$query_args = [
			'order' => 'asc',
		];

		// Get total count in DB
		$result = $users->query_db( $query_args );

		$db_total = (int) $result[ 'total_objects' ];

		// Get total count in ES index
		$query = new WP_Query( $query_args );

		$formatted_args = $users->format_args( $query->query_vars, $query );

		$es_result = $users->query_es( $formatted_args, $query->query_vars );
		
		$diff = '';
		if ( ! $es_result ) {
			$es_total = 'N/A';
			$error = true;
			// Most likely an issue either connecting to ElasticSearch, or no index was found
			$es_conn_err = true;
			// Something is broken, bail instead of returning partial/incorrect data
			$msg = 'error connecting to Elasticsearch instance, or index not found: did you activate Users feature?';
			WP_CLI::error( $msg );
		}

		$icon = "\u{2705}"; // unicode check mark

		// Verify actual results
		$es_total = (int) $es_result[ 'found_documents' ][ 'value' ];

		if ( $db_total !== $es_total ) {
			$error = true;
			$icon = "\u{274C}"; // unicode cross mark
			$diff = sprintf( ', diff: %d', $es_total - $db_total );
		}

		$users_comparison_message = sprintf( "%s (DB: %d, ES: %s%s)", $icon, $db_total, $es_total, $diff );

		WP_CLI::line( $users_comparison_message );

		WP_CLI::line( '' );

		if( $error ) {
			$msg = 'found inconsistent counts for users.';
			WP_CLI::error( $msg );
		}

		WP_CLI::success( 'counts for users are all equal!' );

	}
}
