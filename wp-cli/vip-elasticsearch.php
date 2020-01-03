<?php

namespace Automattic\VIP\CLI;

use \WP_CLI;
use \WP_CLI\Utils;
use \WP_Query as WP_Query;
use \ElasticPress\Indexables as Indexables;
use \ElasticPress\Elasticsearch as Elasticsearch;

/**
 * Helper commands to manage VIP Go Elasticsearch indexes
 *
 * @package Automattic\VIP\CLI
 */
class VIP_Elasticsearch_CLI_Command extends \WPCOM_VIP_CLI_Command {

	/**
	 * @var WP_CLI\Fetchers\Plugin Plugin fetcher
	 */
	protected $plugin_fetcher;

	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		parent::__construct();

		$this->plugin_fetcher = new \WP_CLI\Fetchers\Plugin();
	}

	/**
	 * Validate DB and ES index post counts
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
		// Make sure ElasticPress is installed and active
		$this->is_elasticpress_active();

		// Get indexable objects
		$indexable = Indexables::factory()->get( 'post' );

		$post_types = $indexable->get_indexable_post_types();

		WP_CLI::line( sprintf( "Checking %d post types (%s)\n", count( $post_types ), implode( ',', $post_types ) ) );

		$error = false;
		$es_conn_err = false;

		foreach( $post_types as $post_type ) {
			$query_args = [ 
				'post_type' => $post_type,
			];

			// Get total count in DB
			$result = $indexable->query_db( $query_args );

			$db_total = $result[ 'total_objects' ];

			// Get total count in ES index
			$query = new WP_Query( $query_args );

			$formatted_args = $indexable->format_args( $query->query_vars, $query );

			$es_result = $indexable->query_es( $formatted_args, $query->query_vars );
			
			if ( ! $es_result ) {
				$es_total = 'N/A';
				$error = true;
				$es_conn_err = true;
			} else {
				$es_total = $es_result[ 'found_documents' ][ 'value' ];
			}

			$icon = "\u{2705}"; // unicode check mark
			$diff = '';
			if ( $db_total !== $es_total ) {
				$icon = "\u{274C}"; // unicode cross mark

				if ( $es_result ) {
					$diff = sprintf( ', diff: %d', $es_total - $db_total );
				}
			}

			WP_CLI::line( sprintf( "%s %s (db: %d, es: %s%s)", $icon, $post_type, $db_total, $es_total, $diff ) );
		}

		WP_CLI::line( '' );

		if( $error ) {
			if ( $es_conn_err ) {
				$msg = 'cannot connect to Elasticsearch instance.';
			} else {
				$msg = 'found inconsistent counts for post types.';
			}
			WP_CLI::error( $msg );
		}

		WP_CLI::success( 'counts for public post types are all equal!' );
	}

	protected function is_elasticpress_active() {
		$plugin = $this->plugin_fetcher->get( 'ElasticPress' );

		if ( ! $plugin ) {
			WP_CLI::error( 'ElasticPress plugin not found' );
		}		

		$file = $plugin->file;

		if ( ! is_plugin_active( $file ) && ! is_plugin_active_for_network( $file ) ) {
			WP_CLI::error( 'ElasticPress plugin not active' );
		}
	}
}

WP_CLI::add_command( 'vip-es health', __NAMESPACE__ . '\VIP_Elasticsearch_CLI_Command' );
