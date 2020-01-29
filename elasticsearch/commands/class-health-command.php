<?php

namespace Automattic\VIP\Elasticsearch;

use \WP_CLI;
use \WP_CLI\Utils;
use \ElasticPress\Indexables as Indexables;
use \ElasticPress\Features as Features;
//use \ElasticPress\Elasticsearch as Elasticsearch;

/**
 * Commands to view and manage the health of VIP Go Elasticsearch indexes
 *
 * @package Automattic\VIP\Elasticsearch
 */
class Health_Command extends \WPCOM_VIP_CLI_Command {
	private const SUCCESS_ICON = "\u{2705}"; // unicode check mark
	private const FAILURE_ICON = "\u{274C}"; // unicode cross mark

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
		$this->validate_posts_count( $args, $assoc_args );
		$this->validate_users_count( $args, $assoc_args );
		// TODO: check return values and WP_CLI:error if any === false
		// WP_CLI::error
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
	 * Move this function inside of ElasticSearch class (separate PR?)
	 * Remove all WP_CLI and make it return meaningful values (according to WP standards)
	 */
	public function validate_posts_count( $args, $assoc_args ) {
		$consistency_check = true;
		// Get indexable objects
		$posts = Indexables::factory()->get( 'post' );

		$post_types = $posts->get_indexable_post_types();

		WP_CLI::line( sprintf( "Checking %d post types (%s)\n", count( $post_types ), implode( ', ', $post_types ) ) );

		foreach( $post_types as $post_type ) {
			$post_statuses = Indexables::factory()->get( 'post' )->get_indexable_post_status();

			$query_args = [
				'post_type' => $post_type,
				'post_status' => array_values( $post_statuses ),
			];

			$result = Elasticsearch::factory()->validate_entity_count( $query_args, $posts );

			// In case of error skip to the next post type
			if ( is_wp_error( $result ) ) {
				WP_CLI::line( self::FAILURE_ICON . ' error while verifying post type: ' . $post_type . ', details: ' . $result->get_error_message() );
				continue;
			}

			$diff_details = sprintf( 'DB: %s, ES: %s', $result[ 'db_total' ], $result[ 'es_total' ] );

			if ( $result[ 'diff' ] ) {
				WP_CLI::line( self::FAILURE_ICON . ' found inconsistent counts for post type: ' . $post_type . '; ' . $diff_details );
				$consistency_check = false;
			} else {
				WP_CLI::line( self::SUCCESS_ICON . ' counts for post type: ' . $post_type . ' correct; ' . $diff_details );
			}
		}

		WP_CLI::line( '' );
		if ( $consistency_check ) {
			WP_CLI::success( self::SUCCESS_ICON . ' counts for post types are all equal.' );
		} else {
			WP_CLI::error( self::FAILURE_ICON . ' inconsistencies found for posts!' );
		}
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

		$query_args = [
			'order' => 'asc',
		];

		if ( ! Elasticsearch::factory()->validate_entity_count( $query_args, $users, 'user' ) ) {
			WP_CLI::error( 'found inconsistent counts for users.' );
		}

		WP_CLI::line( '' );
		WP_CLI::success( 'counts for users are all equal!' );

	}
}
