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
		$type = $args[0];
	
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}

		$new_version = $search->versioning->add_version( $indexable );

		if ( is_wp_error( $result ) ) {
			return WP_CLI::error( $result->get_error_message() );
		}

		if ( false === $result ) {
			return WP_CLI::error( 'Failed to register the new index version' );
		}

		WP_CLI::success( sprintf( 'Registered and created new index version %d', $new_version['number'] ) );
	}

	/**
	 * Get details about a version of an index
	 *
	 * ## OPTIONS
	 * 
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 * 
	 * <version_number>
	 * : The version number to retrieve
	 *
	 * ## EXAMPLES
	 *     wp vip-search index-versions get post 2
	 *
	 * @subcommand get
	 */
	public function get( $args, $assoc_args ) {
		$type = $args[0];
		$version_number = intval( $args[1] );

		if ( $version_number <= 0 ) {
			return WP_CLI::error( 'New version number must be a positive int' );
		}
	
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}

		$version = $search->versioning->get_version( $indexable, $version_number );

		if ( is_wp_error( $version ) ) {
			return WP_CLI::error( $result->get_error_message() );
		}

		if ( ! $version ) {
			return WP_CLI::error( sprintf( 'Failed to get index version %d for type %s. Does it exist?', $version_number, $type ) );
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'], array( $version ), array( 'number', 'active', 'created_time', 'activated_time' ) );
	}

	/**
	 * List all registered index versions
	 *
	 * ## OPTIONS
	 * 
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 *
	 * [--format=<string>]
	 * : Optional one of: table json csv yaml ids count
	 *
	 * ## EXAMPLES
	 *     wp vip-search index-versions list post
	 *
	 * @subcommand list
	 */
	public function list( $args, $assoc_args ) {
		$type = $args[0];
	
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}

		$versions = $search->versioning->get_versions( $indexable );

		if ( is_wp_error( $versions ) ) {
			return WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'], $versions, array( 'number', 'active', 'created_time', 'activated_time' ) );
	}

	/**
	 * Activate a version of an index. This will start sending all requests to the index version specified
	 *
	 * ## OPTIONS
	 * 
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 * 
	 * <version_number>
	 * : The version number of the index to activate
	 *
	 * ## EXAMPLES
	 *     wp vip-search index-versions activate post
	 *
	 * @subcommand activate
	 */
	public function activate( $args, $assoc_args ) {
		$type = $args[0];
		$new_version_number = intval( $args[1] );

		if ( $new_version_number <= 0 ) {
			return WP_CLI::error( 'New version number must be a positive int' );
		}
	
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}

		$active_version_number = $search->versioning->get_active_version_number( $indexable );

		if ( $active_version_number === $new_version_number ) {
			return WP_CLI::error( sprintf( 'Index version %d is already active for type %s', $new_version_number, $type ) );
		}

		WP_CLI::confirm( sprintf( 'Are you sure you want to activate index version %d for type %s?', $new_version_number, $type ), $assoc_args );

		$result = $search->versioning->activate_version( $indexable, $new_version_number );

		if ( is_wp_error( $result ) ) {
			return WP_CLI::error( $result->get_error_message() );
		}

		if ( ! $result ) {
			return WP_CLI::error( sprintf( 'Failed to activate index version %d for type %s', $new_version_number, $type ) );
		}

		WP_CLI::success( sprintf( 'Successfully activated index version %d for type %s', $new_version_number, $type ) );
	}

	/**
	 * Get details about the currently active index version
	 *
	 * ## OPTIONS
	 * 
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 *
	 * ## EXAMPLES
	 *     wp vip-search index-versions get-active post
	 *
	 * @subcommand get-active
	 */
	public function get_active( $args, $assoc_args ) {
		$type = $args[0];
	
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}

		$version = $search->versioning->get_active_version( $indexable );

		if ( is_wp_error( $version ) ) {
			return WP_CLI::error( $version->get_error_message() );
		}

		if ( ! is_array( $version ) ) {
			return WP_CLI::error( 'Failed to retrieve the active index version' );
		}
		
		\WP_CLI\Utils\format_items( $assoc_args['format'], array( $version ), array( 'number', 'active', 'created_time', 'activated_time' ) );
	}
}
