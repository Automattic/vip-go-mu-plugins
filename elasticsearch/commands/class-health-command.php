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
			if ( ! $this->vip_elasticsearch->validate_entity_count( $query_args, $posts, $post_type ) ) {
				// TODO: do not error out
				WP_CLI::error( 'found inconsistent counts for post type: ' . $post_type );
			}
		}

		WP_CLI::line( '' );
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
