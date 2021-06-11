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

	private function _verify_arguments_compatibility( $assoc_args ) {
		if ( array_key_exists( 'version', $assoc_args ) && array_key_exists( 'using-versions', $assoc_args ) ) {
			WP_CLI::error( 'The --version argument is not allowed when specifying --using-versions' );
		}

		// If version is specified, the indexable must also be specified, as different indexables can have different versions
		if ( array_key_exists( 'version', $assoc_args ) && ! isset( $assoc_args['indexables'] ) ) {
			WP_CLI::error( 'The --indexables argument is required when specifying --version, as each indexable has separate versioning' );
		}

		if ( array_key_exists( 'using-versions', $assoc_args ) && ! isset( $assoc_args['indexables'] ) ) {
			WP_CLI::error( 'The --indexables argument is required when specifying --using-versions' );
		}

		if ( array_key_exists( 'using-versions', $assoc_args ) && isset( $assoc_args['network-wide'] ) ) {
			WP_CLI::error( 'The --using-versions argument is not supported together with --network-wide' );
		}
	}

	private function _shift_version_after_index( $assoc_args ) {
		$search = \Automattic\VIP\Search\Search::instance();

		$indexables = $this->_parse_indexables( $assoc_args );
		$skip_confirm = isset( $assoc_args['skip-confirm'] ) && $assoc_args['skip-confirm'];

		foreach ( $indexables as $indexable ) {
			WP_CLI::line( sprintf( 'Updating active version for "%s"', $indexable->slug ) );
			$result = $search->versioning->activate_version( $indexable, 'next' );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( sprintf( 'Error activating next version: %s', $result->get_error_message() ) );
			}

			if ( ! $skip_confirm ) {
				WP_CLI::confirm( '⚠️  You are about to remove previously used index version. It is advised to verify that the new version is being used before continuing. Continue?' );
			}


			WP_CLI::line( sprintf( 'Removing inactive version for "%s"', $indexable->slug ) );
			$result = $search->versioning->delete_version( $indexable, 'previous' );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( sprintf( 'Error deleting previous version: %s', $result->get_error_message() ) );
			}
		}
	}

	private function _parse_indexables( $assoc_args ) {
		$indexable_slugs = explode( ',', str_replace( ' ', '', $assoc_args['indexables'] ) );

		$indexables = [];

		foreach ( $indexable_slugs as $slug ) {
			$indexable = \ElasticPress\Indexables::factory()->get( $slug );

			if ( ! $indexable ) {
				WP_CLI::error( sprintf( 'Indexable %s not found - is the feature active?', $slug ) );
			}

			$indexables[] = $indexable;
		}
		return $indexables;
	}

	protected function _maybe_setup_index_version( $assoc_args ) {
		if ( array_key_exists( 'version', $assoc_args ) || array_key_exists( 'using-versions', $assoc_args ) ) {
			$version_number = '';
			$using_versions = $assoc_args['using-versions'] ?? false;
			if ( $assoc_args['version'] ?? false ) {
				$version_number = $assoc_args['version'];
			} else if ( $using_versions ) {
				$version_number = 'next';
			}

			if ( $version_number ) {
				$search = \Automattic\VIP\Search\Search::instance();

				// For each indexable specified, override the version
				$indexables = $this->_parse_indexables( $assoc_args );

				if ( $using_versions ) {
					foreach ( $indexables as $indexable ) {
						$current_versions = $search->versioning->get_versions( $indexable );
						if ( count( $current_versions ) > 1 ) {
							WP_CLI::error( sprintf(
								'There needs to be only one version per indexable in order to automatically use versions to reindex. Please remove inactive versions for indexable "%s".',
								$indexable->slug
							) );
						}
					}

					foreach ( $indexables as $indexable ) {
						$result = $search->versioning->add_version( $indexable );
						if ( is_wp_error( $result ) ) {
							WP_CLI::error( sprintf( 'Error adding new version: %s', $result->get_error_message() ) );
						}
					}
				}

				foreach ( $indexables as $indexable ) {
					$result = $search->versioning->set_current_version_number( $indexable, $version_number );

					if ( is_wp_error( $result ) ) {
						WP_CLI::error( sprintf( 'Error setting version number: %s', $result->get_error_message() ) );
					}
				}
			}
		}
	}

	/**
	 * Index all posts for a site or network wide
	 *
	 * ## OPTIONS
	 *
	 * [--version]
	 * : The index version to index into. Used to build up a new index in parallel with the currently active index version
	 *
	 * [--using-versions]
	 * : This switch will create a new version and reindex that version (while the current version will continue to serve content).
	 * After the indexing is done the new version will be activated and old version removed.
	 *
	 * @synopsis [--setup] [--network-wide] [--per-page] [--nobulk] [--show-errors] [--offset] [--start-object-id] [--end-object-id] [--indexables] [--show-bulk-errors] [--show-nobulk-errors] [--post-type] [--include] [--post-ids] [--ep-host] [--ep-prefix] [--version] [--skip-confirm] [--using-versions]
	 *
	 * @param array $args Positional CLI args.
	 * @since 0.1.2
	 * @param array $assoc_args Associative CLI args.
	 */
	public function index( $args, $assoc_args ) {
		if ( isset( $assoc_args['setup'] ) && $assoc_args['setup'] ) {
			self::confirm_destructive_operation( $assoc_args );
		}
		$this->_verify_arguments_compatibility( $assoc_args );

		$using_versions = $assoc_args['using-versions'] ?? false;
		$skip_confirm = isset( $assoc_args['skip-confirm'] ) && $assoc_args['skip-confirm'];

		$this->_maybe_setup_index_version( $assoc_args );


		// Unset our --version param, otherwise WP_CLI complains that it's unknown
		unset( $assoc_args['version'] );
		// Unset our --using-versions param, otherwise WP_CLI complains that it's unknown
		unset( $assoc_args['using-versions'] );

		/**
		 * EP's `--network-wide` mode uses switch_to_blog to index the content,
		 * that may not be reliable if the codebase differs between subsites.
		 *
		 * Side-step the issue by spawning child proccesses for each subsite.
		 */
		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			$start = microtime( true );
			WP_CLI::line( 'Operating in network mode!' );

			unset( $assoc_args['network-wide'] );

			foreach ( get_sites() as $site ) {
				switch_to_blog( $site->blog_id );
				$assoc_args['url'] = home_url();

				WP_CLI::line( 'Indexing ' . $assoc_args['url'] );
				WP_CLI::runcommand( 'vip-search index ' . Utils\assoc_args_to_str( $assoc_args ), [
					'exit_error' => false,
				] );
				Utils\wp_clear_object_cache();
				restore_current_blog();
			}

			WP_CLI::line( WP_CLI::colorize( '%CNetwork-wide run took: ' . ( round( microtime( true ) - $start, 3 ) ) . '%n' ) );
		} else {
			// Unset skip-confirm since it doesn't exist in ElasticPress and causes
			// an error for indexing operations exclusively for some reason.
			unset( $assoc_args['skip-confirm'] );
			array_unshift( $args, 'elasticpress', 'index' );
			WP_CLI::run_command( $args, $assoc_args );
		}

		if ( $using_versions ) {
			// resetting skip-confirm after it was cleared for elasticpress
			$assoc_args['skip-confirm'] = $skip_confirm;
			$this->_shift_version_after_index( $assoc_args );
		}
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
