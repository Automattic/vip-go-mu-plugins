<?php

namespace Automattic\VIP\Search\Commands;

use \WP_CLI;
use \WP_CLI\Utils;

/**
 * Commands to view and manage index versions
 *
 * @package Automattic\VIP\Search
 */
class VersionCommand extends \WPCOM_VIP_CLI_Command {
	private const SUCCESS_ICON = "\u{2705}"; // unicode check mark
	private const FAILURE_ICON = "\u{274C}"; // unicode cross mark

	/**
	 * Register a new index version
	 *
	 * ## OPTIONS
	 * 
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 *
	 * ## EXAMPLES
	 *     wp vip-search index-versions add post
	 *
	 * @subcommand add
	 */
	public function add( $args, $assoc_args ) {
		$type = $args[ 0 ];
	
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		$new_version = $search->versioning->add_version( $indexable );

		if ( is_wp_error( $result ) ) {
			return WP_CLI::error( $result->get_error_message() );
		}

		if ( false === $result ) {
			return WP_CLI::error( 'Failed to register the new index version' );
		}

		WP_CLI::success( sprintf( 'Registered new index version %d. The new index has not yet been created', $new_version['number'] ) );
	}
}
