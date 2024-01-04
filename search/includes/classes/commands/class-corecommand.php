<?php

namespace Automattic\VIP\Search\Commands;

use WP_CLI;
use WP_CLI\Utils;
use ElasticPress\Elasticsearch;

/**
 * Core commands for interacting with VIP Search
 *
 * @package Automattic\VIP\Search
 */
class CoreCommand {
	private $ep_command;

	public function __construct() {
		$this->ep_command = new \ElasticPress\Command();
	}

	private function verify_arguments_compatibility( $assoc_args ) {
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

		if ( array_key_exists( 'blog-ids', $assoc_args ) && isset( $assoc_args['network-wide'] ) ) {
			WP_CLI::error( 'The --blog-ids argument is not supported together with --network-wide' );
		}
	}

	private function shift_version_after_index( $assoc_args ) {
		$search = \Automattic\VIP\Search\Search::instance();

		$indexables   = $this->parse_indexables( $assoc_args );
		$skip_confirm = isset( $assoc_args['skip-confirm'] ) && $assoc_args['skip-confirm'];

		foreach ( $indexables as $indexable ) {
			WP_CLI::line( sprintf( 'Updating active version for "%s"', $indexable->slug ) );
			if ( ! $skip_confirm ) {
				WP_CLI::confirm( sprintf( 'Update the active index version for "%s"?', $indexable->slug ) );
			}

			$result = $search->versioning->activate_version( $indexable, 'next' );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( sprintf( 'Error activating next version: %s', $result->get_error_message() ) );
			}

			if ( ! $skip_confirm ) {
				WP_CLI::confirm( '⚠️ The previous version of the index is now inactive and should be deleted. Delete previous index version?' );
			}

			WP_CLI::line( sprintf( 'Removing inactive version for "%s"', $indexable->slug ) );
			$result = $search->versioning->delete_version( $indexable, 'previous' );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( sprintf( 'Error deleting previous version: %s', $result->get_error_message() ) );
			}
		}
	}

	private function parse_indexable( $slug ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $slug );
		if ( ! $indexable ) {
			WP_CLI::error( sprintf( 'Indexable %s not found - is the feature active?', $slug ) );
		}

		return $indexable;
	}

	private function parse_indexables( $assoc_args ) {
		$indexable_slugs = explode( ',', str_replace( ' ', '', $assoc_args['indexables'] ) );

		$indexables = [];

		foreach ( $indexable_slugs as $slug ) {
			$indexable    = $this->parse_indexable( $slug );
			$indexables[] = $indexable;
		}
		return $indexables;
	}

	private function set_version( $indexable, $version ) {
		$search = \Automattic\VIP\Search\Search::instance();

		$result = $search->versioning->set_current_version_number( $indexable, $version );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( sprintf( 'Error setting version number: %s', $result->get_error_message() ) );
		}
	}

	/**
	 * List all of the available indexes.
	 *
	 * @return array|WP_Error $indexes Array of available indexes.
	 */
	private function list_indexes() {
		$path = '_cat/indices?format=json';

		$response = Elasticsearch::factory()->remote_request( $path );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'list-indexes-error', $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_array( $body ) ) {
			$message = property_exists( $body, 'error' ) ? $body->error : 'Something went wrong during list_indexes().';
			return new \WP_Error( 'list-indexes-error', $message );
		}

		$indexes = array_column( $body, 'index' );
		// Remove system operation indexes from list.
		$indexes = array_filter( $indexes, fn( $index ) => ! $index || '.' !== $index[0] );
		sort( $indexes );

		return $indexes;
	}

	protected function maybe_setup_index_version( $assoc_args ) {
		if ( array_key_exists( 'version', $assoc_args ) || array_key_exists( 'using-versions', $assoc_args ) ) {
			$version_number = '';
			$using_versions = $assoc_args['using-versions'] ?? false;
			if ( $assoc_args['version'] ?? false ) {
				$version_number = $assoc_args['version'];
			} elseif ( $using_versions ) {
				$version_number = 'next';
			}

			if ( $version_number ) {
				$search = \Automattic\VIP\Search\Search::instance();

				// For each indexable specified, override the version
				$indexables = $this->parse_indexables( $assoc_args );

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
					$this->set_version( $indexable, $version_number );
				}
			}
		}
	}

	/**
	 * Index all posts for a site or network wide
	 *
	 * ## OPTIONS
	 *
	 * [--setup]
	 * : Drop the index, send the new mappings to the server, and re-index the site.
	 *
	 * [--network-wide]
	 * : Sequentially index every site in your multisite network.
	 *
	 * [--blog-ids]
	 * : Index a list of specific blog ids in your multisite work.
	 *
	 * [--version]
	 * : The index version to index into. Used to build up a new index in parallel with the currently active index version.
	 *
	 * [--using-versions]
	 * : This switch will create a new version and reindex that version (while the current version will continue to serve content).
	 * After the indexing is done the new version will be activated and old version removed.
	 *
	 * [--per-page]
	 * : Lets you determine the amount of posts to be indexed per bulk index (or cycle). Default: 500.
	 *
	 * [--include]
	 * : Comma-separated list of object ID to index.
	 *
	 * [--post-ids]
	 * : Alias of --include (deprecated).
	 *
	 * [--post-type]
	 * : Comma-separated list of post types to index. By default all public post types are indexed.
	 *
	 * [--indexables]
	 * : Comma-separated list of Indexables to index, default includes all registered Indexables.
	 *
	 * [--upper-limit-object-id]
	 * : Upper limit of a range of IDs to be indexed. If indexing IDs from 30 to 45, this should be 45.
	 *
	 * [--lower-limit-object-id]
	 * : Lower limit of a range of IDs to be indexed. If indexing IDs from 30 to 45, this should be 30.
	 *
	 * [--show-bulk-errors]
	 * : displays the error message returned from Elasticsearch when a post fails to index (as opposed to just the title and ID of the post)
	 *
	 * [--skip-confirm]
	 * : Skip Enterprise Search confirmation prompts for destructive operations.
	 *
	 * @synopsis [--setup] [--network-wide] [--blog-ids] [--per-page] [--nobulk] [--show-errors] [--offset] [--upper-limit-object-id] [--lower-limit-object-id] [--indexables] [--show-bulk-errors] [--show-nobulk-errors] [--post-type] [--include] [--post-ids] [--version] [--skip-confirm] [--using-versions]
	 *
	 * @param array $args Positional CLI args.
	 * @since 0.1.2
	 * @param array $assoc_args Associative CLI args.
	 */
	public function index( $args, $assoc_args ) {
		$this->verify_arguments_compatibility( $assoc_args );

		$using_versions = $assoc_args['using-versions'] ?? false;
		$skip_confirm   = isset( $assoc_args['skip-confirm'] ) && $assoc_args['skip-confirm'];

		$this->maybe_setup_index_version( $assoc_args );

		$network_mode = isset( $assoc_args['network-wide'] );
		$batch_mode   = isset( $assoc_args['blog-ids'] );
		/**
		 * EP's `--network-wide` mode uses switch_to_blog to index the content,
		 * that may not be reliable if the codebase differs between subsites.
		 *
		 * Side-step the issue by spawning child proccesses for each subsite.
		 */
		if ( is_multisite() && ( $network_mode || $batch_mode ) ) {
			if ( $network_mode ) {
				if ( isset( $assoc_args['setup'] ) && 100 < get_blog_count() ) {
					WP_CLI::Error( 'Blog limit reached for --network-wide! Please create indexes on per-site or batch by removing the --network-wide flag and passing in the --url or --blog-ids parameter instead. For more information, see https://docs.wpvip.com/how-tos/vip-search/index-with-vip-search/#h-network-wide.' );
				}
				WP_CLI::line( 'Operating in network mode!' );
				unset( $assoc_args['network-wide'] );
			} else {
				WP_CLI::line( 'Starting batch...' );
			}

			$start = microtime( true );

			if ( $batch_mode ) {
				$blog_ids = $assoc_args['blog-ids'];
				unset( $assoc_args['blog-ids'] );
				if ( false !== strpos( $blog_ids, ',' ) ) {
					$sites = explode( ',', $blog_ids );
				} else {
					// Single blog ID passed in.
					if ( ! is_numeric( $blog_ids ) ) {
						WP_CLI::Error( "Invalid input for blog ID {$blog_ids}!" );
					}
					$sites = [ $blog_ids ];
				}
				$valid_sites = get_sites(
					[
						'fields'   => 'ids',
						'site__in' => $sites,
						'number'   => count( $sites ),
					]
				);
				foreach ( $sites as $site ) {
					// Verify it's a valid blog ID before proceeding.
					if ( ! in_array( (int) $site, $valid_sites, true ) ) {
						WP_CLI::error( "Blog ID {$site} does not exist!" );
					}
				}
			} else {
				$sites = get_sites( [ 'fields' => 'ids' ] );
			}

			foreach ( $sites as $blog_id ) {
				switch_to_blog( (int) $blog_id );
				$assoc_args['url'] = home_url();
				WP_CLI::line( "* Indexing blog {$blog_id}: {$assoc_args['url']}" );
				WP_CLI::runcommand( 'vip-search index ' . Utils\assoc_args_to_str( $assoc_args ), [
					'exit_error' => false,
				] );
				Utils\wp_clear_object_cache();
				restore_current_blog();
			}

			WP_CLI::line( WP_CLI::colorize( '%CRun took: ' . ( round( microtime( true ) - $start, 3 ) ) . '%n' ) );
		} else {
			if ( isset( $assoc_args['setup'] ) && $assoc_args['setup'] ) {
				self::confirm_destructive_operation( $assoc_args );
			}
			
			// Unset our arguments since they don't exist in ElasticPress and causes
			// an error for indexing operations exclusively for some reason.
			unset( $assoc_args['version'] );
			unset( $assoc_args['using-versions'] );
			unset( $assoc_args['skip-confirm'] );
			if ( $skip_confirm ) {
				$assoc_args['yes'] = true;
			}

			\Automattic\VIP\Logstash\log2logstash(
				[
					'severity' => 'info',
					'feature'  => 'search_cli',
					'message'  => 'Indexing content',
					'blog_id'  => get_current_blog_id(),
					'extra'    => [
						'homeurl' => home_url(),
					],
				]
			);

			array_unshift( $args, 'elasticpress', 'index' );
			WP_CLI::run_command( $args, $assoc_args );
		}

		if ( $using_versions ) {
			// resetting skip-confirm after it was cleared for elasticpress
			$assoc_args['skip-confirm'] = $skip_confirm;
			$this->shift_version_after_index( $assoc_args );
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
		$this->ep_command->put_mapping( $args, $assoc_args );
	}

	/**
	 * Get settings for index which includes shard count and mapping
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 *
	 * [--version]
	 * : The index version to index into. Used to build up a new index in parallel with the currently active index version
	 *
	 * [--format=<string>]
	 * : Optional one of: table json csv yaml ids count
	 *
	 * ## EXAMPLES
	 * wp vip-search get-settings post --format=json | jq
	 *
	 * @subcommand get-index-settings
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_index_settings( $args, $assoc_args ) {
		$slug = array_shift( $args );

		$indexable = $this->parse_indexable( $slug );

		if ( isset( $assoc_args['version'] ) ) {
			$this->set_version( $indexable, $assoc_args['version'] );
		}

		$index_name = $indexable->get_index_name();

		$settings = Elasticsearch::factory()->get_mapping( $index_name );

		$keys = array_keys( $settings );
		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', array( $settings ), $keys );
	}

	/**
	 * Throw error when delete-index command is attempted to be used.
	 *
	 * @subcommand delete-index
	 */
	public function delete_index() {
		WP_CLI::error( 'Please use index versioning to manage your indices: https://docs.wpvip.com/how-tos/vip-search/version-with-enterprise-search/' );
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

	/**
	 * Return all index names as a JSON object.
	 *
	 * @subcommand get-indexes
	 */
	public function get_indexes() {

		$indexes = $this->list_indexes();

		if ( is_wp_error( $indexes ) ) {
			WP_CLI::error( wp_json_encode( $indexes ) );
		}

		WP_CLI::line( wp_json_encode( $indexes ) );
	}

	/**
	 * Return all index names as a JSON object.
	 *
	 * @subcommand get-mapping
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_mapping( $args, $assoc_args ) {
		$index_names = (array) ( isset( $assoc_args['index-name'] ) ? $assoc_args['index-name'] : $this->list_indexes() );

		$path = join( ',', $index_names ) . '/_mapping';

		$response = Elasticsearch::factory()->remote_request( $path );

		$body = wp_remote_retrieve_body( $response );

		WP_CLI::line( $body );
	}

	/**
	 * Activate a feature. If a re-indexing is required, you will need to do it manually.
	 *
	 * ## OPTIONS
	 *
	 * <feature-slug>
	 * : The feature slug
	 *
	 * @subcommand activate-feature
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function activate_feature( $args, $assoc_args ) {
		if ( $this->is_unsupported_feature( $args[0] ) ) {
			WP_CLI::error( "The feature {$args[0]} is not currently supported." );
		}

		$this->ep_command->activate_feature( $args, $assoc_args );
	}

	/**
	 * Check if feature is unsupported.
	 *
	 * @param string $feature EP feature
	 * @return bool Whether feature is unsupported or not.
	 */
	private function is_unsupported_feature( $feature ) {
		$unsupported_features = [ 'autosuggest', 'documents', 'comments' ];
		if ( in_array( $feature, $unsupported_features, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Dectivate a feature.
	 *
	 * ## OPTIONS
	 *
	 * <feature-slug>
	 * : The feature slug
	 *
	 * @subcommand deactivate-feature
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function deactivate_feature( $args, $assoc_args ) {
		if ( 'search' === $args[0] ) {
			WP_CLI::confirm( "Are you sure you want to deactivate $args[0]? This will break all search-related functionality!" );
		}

		$this->ep_command->deactivate_feature( $args, $assoc_args );
	}

	/**
	 * Get the last indexed post ID on an incomplete indexing operation.
	 *
	 * @subcommand get-last-indexed-post-id
	 */
	public function get_last_indexed_post_id() {
		$search = \Automattic\VIP\Search\Search::instance();

		$last_id = get_option( $search::LAST_INDEXED_POST_ID_OPTION );

		if ( false === $last_id ) {
			WP_CLI::line( 'No last indexed object ID found!' );
		} else {
			WP_CLI::line( wp_json_encode( $last_id ) );
		}
	}

	/**
	 * Clean the ep_feature_settings individual blog option if it exists for sites with EP_IS_NETWORK.
	 *
	 * @subcommand clean-ep-feature-settings
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function clean_ep_feature_settings() {
		if ( is_multisite() && defined( 'EP_IS_NETWORK' ) && true === constant( 'EP_IS_NETWORK' ) ) {
			$delete_option = delete_option( 'ep_feature_settings' );
			if ( $delete_option ) {
				WP_CLI::success( 'Deleted ep_feature_settings blog option!' );
			} else {
				WP_CLI::error( 'Failed to delete ep_feature_settings_blog_option!' );
			}
		} else {
			WP_CLI::error( 'Not a multisite or EP_IS_NETWORK is not enabled!' );
		}
	}

	/**
	 * Stop the current indexing operation.
	 *
	 * @subcommand stop-indexing
	 * 
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function stop_indexing( $args, $assoc_args ) {
		$this->ep_command->stop_indexing( $args, $assoc_args );
	}

	/**
	 * List features (either active or all).
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Show all registered features
	 *
	 * @subcommand list-features
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function list_features( $args, $assoc_args ) {
		$this->ep_command->list_features( $args, $assoc_args );
	}

	/**
	 * Recreates the alias index which points to every index in the network.
	 *
	 * Map network alias to every index in the network for every non-global indexable
	 *
	 * @subcommand recreate-network-alias
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function recreate_network_alias( $args, $assoc_args ) {
		$this->ep_command->recreate_network_alias( $args, $assoc_args );
	}

	/**
	 * Clear a sync/index process.
	 *
	 * If an index was stopped prematurely and won't start again, this will clear this cached data such that a new index can start.
	 *
	 * @subcommand clear-index
	 * @alias delete-transient
	 */
	public function clear_index( $args, $assoc_args ) {
		$this->ep_command->clear_index( $args, $assoc_args );
	}

	/**
	 * Returns the status of an ongoing index operation in JSON array.
	 *
	 * Returns the status of an ongoing index operation in JSON array with the following fields:
	 * indexing | boolean | True if index operation is ongoing or false
	 * items_indexed | integer | Total number of items indexed
	 * total_items | integer | Total number of items indexed or -1 if not yet determined
	 *
	 * ## OPTIONS
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand get-indexing-status
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_indexing_status( $args, $assoc_args ) {
		$this->ep_command->get_indexing_status( $args, $assoc_args );
	}

	/**
	 * Returns a JSON array with the results of the last CLI index (if present) or an empty array.
	 *
	 * ## OPTIONS
	 *
	 * [--clear]
	 * : Clear the `ep_last_cli_index` option.
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand get-last-index
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_last_index( $args, $assoc_args ) {
		$this->ep_command->get_last_cli_index( $args, $assoc_args );
	}

	/**
	 * Get the algorithm version.
	 *
	 * Get the value of the `ep_search_algorithm_version` option, or
	 * `default` if empty.
	 *
	 * @subcommand get-algorithm-version
	 */
	public function get_algorithm_version() {
		$version = apply_filters( 'ep_search_algorithm_version', get_option( 'ep_search_algorithm_version', '3.5' ) );
		WP_CLI::line( $version );
	}

	/**
	 * Get stats on the current index.
	 * 
	 * @subcommand stats
	 */
	public function get_stats() {
		$this->ep_command->stats();
	}
}
