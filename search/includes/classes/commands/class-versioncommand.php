<?php

namespace Automattic\VIP\Search\Commands;

use \WP_CLI;
use \WP_CLI\Utils;
use \ElasticPress\Indexable as Indexable;

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
	 * [--network-wide]
	 * : Optional - add a new version to all subsites
	 * 
	 * ## EXAMPLES
	 *     wp vip-search index-versions add post
	 *     wp vip-search index-versions add post --network-wide
	 *
	 * @subcommand add
	 */
	public function add( $args, $assoc_args ) {
		$type = $args[0];

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ) {
				$assoc_args['network-wide'] = 0;
			}

			$sites = \ElasticPress\Utils\get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				$new_version = $this->add_helper( $indexable );

				restore_current_blog();

				WP_CLI::line( sprintf( 'Registered and created new index version %d on blog %d (%s)', $new_version['number'], $site['blog_id'], $site['domain'] . $site['path'] ) );
			}

			WP_CLI::success( 'Done!' );
		} else {
			$new_version = $this->add_helper( $indexable );

			WP_CLI::success( sprintf( 'Registered and created new index version %d', $new_version['number'] ) );
		}
	}

	protected function add_helper( Indexable $indexable ) {
		$search = \Automattic\VIP\Search\Search::instance();

		$new_version = $search->versioning->add_version( $indexable );

		if ( is_wp_error( $new_version ) ) {
			return WP_CLI::error( $result->get_error_message() );
		}

		if ( false === $new_version ) {
			return WP_CLI::error( 'Failed to register the new index version' );
		}

		return $new_version;
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
	
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}
	
		$version_number = $search->versioning->normalize_version_number( $indexable, $args[1] );

		if ( is_wp_error( $version_number ) ) {
			return WP_CLI::error( sprintf( 'Index version %s is not valid: %s', $args[1], $version_number->get_error_message() ) );
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
	 * [--network-wide]
	 * : Optional - activate the version to all subsites. Best used with version aliases like `next` instead of individual version numbers
	 *
	 * ## EXAMPLES
	 *     wp vip-search index-versions activate post
	 *
	 * @subcommand activate
	 */
	public function activate( $args, $assoc_args ) {
		$type = $args[0];
		$desired_version_number = $args[1];

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			WP_CLI::confirm( sprintf( 'Are you sure you want to activate index version %s for type %s on all sites in this network?', $desired_version_number, $type ), $assoc_args );

			if ( ! is_numeric( $assoc_args['network-wide'] ) ) {
				$assoc_args['network-wide'] = 0;
			}

			$sites = \ElasticPress\Utils\get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				$result = $this->activate_helper( $indexable, $desired_version_number );

				restore_current_blog();

				if ( is_wp_error( $result ) ) {
					return WP_CLI::error( sprintf( 'Received error for index version %s on site %s - %s', $desired_version_number, $site['domain'] . $site['path'], $result->get_error_message() ) );
				}

				if ( ! $result ) {
					return WP_CLI::error( sprintf( 'Failed to activate index version %s for type %s on blog %d (%s)', $desired_version_number, $type, $site['domain'] . $site['path'] ) );
				}

				WP_CLI::line( sprintf( 'Successfully activated index version %s for type %s on blog %d (%s)', $desired_version_number, $type, $site['blog_id'], $site['domain'] . $site['path'] ) );
			}

			WP_CLI::success( 'Done!' );
		} else {
			WP_CLI::confirm( sprintf( 'Are you sure you want to activate index version %s for type %s?', $desired_version_number, $type ), $assoc_args );

			$result = $this->activate_helper( $indexable, $desired_version_number );

			if ( is_wp_error( $result ) ) {
				return WP_CLI::error( sprintf( 'Received error for index version %s - %s', $desired_version_number, $result->get_error_message() ) );
			}

			if ( ! $result ) {
				return WP_CLI::error( sprintf( 'Failed to activate index version %s for type %s', $desired_version_number, $type ) );
			}

			WP_CLI::success( sprintf( 'Successfully activated index version %s for type %s', $desired_version_number, $type ) );
		}
	}

	protected function activate_helper( Indexable $indexable, $version_number_to_activate ) {
		$search = \Automattic\VIP\Search\Search::instance();

		$new_version_number = $search->versioning->normalize_version_number( $indexable, $version_number_to_activate );

		if ( is_wp_error( $new_version_number ) ) {
			return WP_CLI::error( sprintf( 'Index version %s is not valid: %s', $version_number_to_activate, $new_version_number->get_error_message() ) );
		}

		$active_version_number = $search->versioning->get_active_version_number( $indexable );

		if ( $active_version_number === $new_version_number ) {
			return WP_CLI::error( sprintf( 'Index version %d is already active for type %s', $new_version_number, $type ) );
		}

		$result = $search->versioning->activate_version( $indexable, $new_version_number );

		return $result;
	}

	/**
	 * Delete a version of an index. This will unregister the index version and delete it from Elasticsearch
	 *
	 * ## OPTIONS
	 * 
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 * 
	 * <version_number>
	 * : The version number of the index to delete
	 *
	 * [--skip-confirm]
	 * : Skip confirmation
	 *
	 * ## EXAMPLES
	 *     wp vip-search index-versions delete post 2
	 *
	 * @subcommand delete
	 */
	public function delete( $args, $assoc_args ) {
		$type = $args[0];
	
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}
	
		$version_number = $search->versioning->normalize_version_number( $indexable, $args[1] );

		if ( is_wp_error( $version_number ) ) {
			return WP_CLI::error( sprintf( 'Index version %s is not valid: %s', $args[1], $version_number->get_error_message() ) );
		}

		$active_version_number = $search->versioning->get_active_version_number( $indexable );

		if ( $active_version_number === $version_number ) {
			return WP_CLI::error( sprintf( 'Index version %d is active for type %s and cannot be deleted', $version_number, $type ) );
		}

		CoreCommand::confirm_destructive_operation( $assoc_args );

		$result = $search->versioning->delete_version( $indexable, $version_number );

		if ( is_wp_error( $result ) ) {
			return WP_CLI::error( $result->get_error_message() );
		}

		if ( ! $result ) {
			return WP_CLI::error( sprintf( 'Failed to delete index version %d for type %s', $version_number, $type ) );
		}

		WP_CLI::success( sprintf( 'Successfully deleted index version %d for type %s', $version_number, $type ) );
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
