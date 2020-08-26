<?php

namespace Automattic\VIP\Search\Commands;

use \WP_CLI;
use \WP_CLI\Utils;

/**
 * Core commands for interacting with VIP Search
 *
 * @package Automattic\VIP\Search
 */
class CoreCommand extends \ElasticPress\Command {
	private const SUCCESS_ICON = "\u{2705}"; // unicode check mark
	private const FAILURE_ICON = "\u{274C}"; // unicode cross mark

	protected function _maybe_setup_index_version( &$assoc_args ) {
		if ( $assoc_args['version'] ) {
			$version = intval( $assoc_args['version'] );

			// If version is specified, the indexable must also be specified, as different indexables can have different versions
			if ( ! isset( $assoc_args['indexables'] ) ) {
				return WP_CLI::error( 'The --indexables argument is required when specifying --version, as each indexable has separate versioning' );
			}

			$search = \Automattic\VIP\Search\Search::instance();

			// Additionally, --version is not compatible with --network-wide in non-network mode, because subsites will also have different versions
			if ( isset( $assoc_args['network-wide'] ) && ! $search->is_network_mode() ) {
				return WP_CLI::error( 'The --network-wide argument is not compatible with --version when not using network mode (the `EP_IS_NETWORK` constant), as subsites can have differing index versions' );
			}

			// For each indexable specified, override the version
			$indexable_slugs = explode( ',', str_replace( ' ', '', $assoc_args['indexables'] ) );

			foreach ( $indexable_slugs as $slug ) {
				$indexable = \ElasticPress\Indexables::factory()->get( $slug );

				if ( ! $indexable ) {
					return WP_CLI::error( sprintf( 'Indexable %s not found - is the feature active?' ) );
				}

				$result = $search->versioning->set_current_version_number( $indexable, $version );

				if ( is_wp_error( $result ) ) {
					return WP_CLI::error( sprintf( 'Error setting version number: %s', $result->get_error_message() ) );
				}
			}
		}

		// Unset our --version param, otherwise WP_CLI complains that it's unknown
		unset( $assoc_args['version'] );
	}

	/**
	 * Index all posts for a site or network wide
	 *
	 * ## OPTIONS
	 *
	 * [--version]
	 * : The index version to index into. Used to build up a new index in parallel with the currently active index version
	 *
	 * @synopsis [--setup] [--network-wide] [--per-page] [--nobulk] [--show-errors] [--offset] [--indexables] [--show-bulk-errors] [--show-nobulk-errors] [--post-type] [--include] [--post-ids] [--ep-host] [--ep-prefix] [--version] [--skip-confirm]
	 *
	 * @param array $args Positional CLI args.
	 * @since 0.1.2
	 * @param array $assoc_args Associative CLI args.
	 */
	public function index( $args, $assoc_args ) {
		if ( isset( $assoc_args['setup'] ) && $assoc_args['setup'] ) {
			self::confirm_destructive_operation( $assoc_args );
		}

		$this->_maybe_setup_index_version( $assoc_args );

		array_unshift( $args, 'elasticpress', 'index' );

		WP_CLI::run_command( $args, $assoc_args );
	}

	/**
	 * Add document mappings for every indexable
	 *
	 * @synopsis [--network-wide] [--indexables] [--ep-host] [--ep-prefix] [--skip-confirm]
	 * @subcommand put-mapping

	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function put_mapping( $args, $assoc_args ) {
		self::confirm_destructive_operation( $assoc_args );
		parent::put_mapping( $args, $assoc_args );
	}

	/**
	 * Delete the index for each indexable. !!Warning!! This removes your elasticsearch index(s)
	 * for the entire site.
	 *
	 * @synopsis [--index-name] [--network-wide] [--skip-confirm]
	 * @subcommand delete-index
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function delete_index( $args, $assoc_args ) {
		self::confirm_destructive_operation( $assoc_args );
		parent::delete_index( $args, $assoc_args );
	}

	/**
	 * Certain operations might result in data loss (deleting an index version or putting a new mapping).
	 *
	 * We need to make sure we get a user's confirmation before proceeding with a destructive operation
	 *
	 * @param array $assoc_args arguments that were passed to the caller command.
	 * @return void
	 */
	public static function confirm_destructive_operation( array $assoc_args ) {
		if ( isset( $assoc_args['skip-confirm'] ) && $assoc_args['skip-confirm'] ) {
			return;
		}

		WP_CLI::confirm( '⚠️  You are about to run ' . WP_CLI::colorize( '%ra destructive operation%n' ) . '. Are you sure?' );
	}
}
