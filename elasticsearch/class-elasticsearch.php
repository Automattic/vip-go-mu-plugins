<?php

namespace Automattic\VIP\Elasticsearch;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;
use \ElasticPress\Features as Features;

use \WP_CLI;
use \WP_Query as WP_Query;
use \WP_User_Query as WP_User_Query;

class Elasticsearch {
	/**
	 * Initialize the VIP Elasticsearch plugin
	 */
	public function init() {
		$this->load_dependencies();
		$this->setup_constants();
		$this->setup_hooks();
		$this->load_commands();
	}

	protected function load_dependencies() {
		/**
		 * Load ES Health command class
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/commands/class-health-command.php';
		}
		// Load ElasticPress
		require_once __DIR__ . '/elasticpress/elasticpress.php';
	}

	protected function setup_constants() {
		// Ensure we limit bulk indexing chunk size to a reasonable number (no limit by default)
		if ( ! defined( 'EP_SYNC_CHUNK_LIMIT' ) ) {
			define( 'EP_SYNC_CHUNK_LIMIT', 250 );
		}
	}

	protected function setup_hooks() {
		add_filter( 'ep_index_name', [ $this, 'filter__ep_index_name' ], PHP_INT_MAX, 3 ); // We want to enforce the naming, so run this really late.

		// Network layer replacement to use VIP helpers (that handle slow/down upstream server)
		add_filter( 'ep_intercept_remote_request', '__return_true', 9999 );
		add_filter( 'ep_do_intercept_request', [ $this, 'filter__ep_do_intercept_request' ], 9999, 4 );
		add_filter( 'jetpack_active_modules', [ $this, 'filter__jetpack_active_modules' ], 9999 );
	}

	protected function load_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'vip-es health', __NAMESPACE__ . '\Health_Command' );
		}
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Verify the difference in number for a given entity between the DB and ElasticSearch.
	 * Entities can be either posts or users.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param array $query_args Valid WP_Query criteria, mandatory fields as in following example:
	 * $query_args = [
	 *		'post_type' => $post_type,
	 *		'post_status' => array( $post_statuses )
	 * ];
	 *
	 * @param mixed $indexable Instance of an ElasticPress Indexable Object to search on
	 * @return WP_Error|array
	 */
	public function validate_entity_count( array $query_args, \ElasticPress\Indexable $indexable ) {
		try {
			// Get total count in DB
			$result = $indexable->query_db( $query_args );

			$db_total = (int) $result[ 'total_objects' ];
		} catch ( \Exception $e ) {
			return new WP_Error( 'db_query_error', sprintf( 'failure querying the DB: %s #vip-go-elasticsearch', $e->get_error_message() ) );
		}

		// Get total count in ES index
		try {
			$query = $this->queryObjects( $query_args, $indexable->slug );
			$formatted_args = $indexable->format_args( $query->query_vars, $query );
			$es_result = $indexable->query_es( $formatted_args, $query->query_vars );
		} catch ( \Exception $e ) {
			return new WP_Error( 'es_query_error', sprintf( 'failure querying ES: %s #vip-go-elasticsearch', $e->get_error_message() ) );
		}

		$diff = '';
		// There is not other useful information out of query_es(): it just returns false in case of failure
		if ( ! $es_result ) {
			$es_total = 'N/A';
			$msg = 'error while querying ElasticSearch.';
			return new WP_Error( 'es_query_error', 'failure querying ES. Hint: verify arguments format. #vip-go-elasticsearch' );
		}

		// Verify actual results
		$es_total = (int) $es_result[ 'found_documents' ][ 'value' ];

		if ( $db_total !== $es_total ) {
			$diff = sprintf( ', diff: %d', $es_total - $db_total );
		}

		return [ 'entity' => $indexable->slug, 'type' => ( array_key_exists( 'post_type', $query_args ) ? $query_args[ 'post_type' ] : 'N/A' ), 'db_total' => $db_total, 'es_total' => $es_total, 'diff' => $diff ];
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

		$query_args = [
			'order' => 'asc',
		];

		$result = $this->validate_entity_count( $query_args, $users );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'es_validate_users_count_error', $result->get_error_message() );
		}
		return array( $result );
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

		$results = [];

		foreach( $post_types as $post_type ) {
			$post_statuses = Indexables::factory()->get( 'post' )->get_indexable_post_status();

			$query_args = [
				'post_type' => $post_type,
				'post_status' => array_values( $post_statuses ),
			];

			$result = Elasticsearch::factory()->validate_entity_count( $query_args, $posts );

			// In case of error skip to the next post type
			if ( is_wp_error( $result ) ) {
				WP_CLI::line( ' error while verifying post type: ' . $post_type . ', details: ' . $result->get_error_message() );
				continue;
			}

			$diff_details = sprintf( 'DB: %s, ES: %s', $result[ 'db_total' ], $result[ 'es_total' ] );

			$results[] = $result;

		}
		return $results;
	}


	/**
	 * Helper function to wrap WP_*Query
	 *
	 * @since   1.0.0
	 * @access  private
	 * @param array $query_args Valid WP_Query criteria, mandatory fields as in following example:
	 * $query_args = [
	 *		'post_type' => $post_type,
	 *		'post_status' => array( $post_statuses )
	 * ];
	 *
	 * @param string $type Type (Slug) of the objects to be searched (should be either 'user' or 'post')
	 * @return WP_Query
	 */
	private function queryObjects( array $query_args, string $type ) {
		if ( 'user' === $type ) {
			return new WP_User_Query( $query_args );
		}
		return new WP_Query( $query_args );
	}

	/**
	 * Filter ElasticPress index name if using VIP ES infrastructure
	 */
	public function filter__ep_index_name( $index_name, $blog_id, $indexables ) {
		// TODO: Use FILES_CLIENT_SITE_ID for now as VIP_GO_ENV_ID is not ready yet. Should replace once it is.
		$index_name = sprintf( 'vip-%s-%s', FILES_CLIENT_SITE_ID, $indexables->slug );

		// $blog_id won't be present on global indexes (such as users)
		if ( $blog_id ) {
			$index_name .= sprintf( '-%s', $blog_id );
		}

		return $index_name;
	}

	public function filter__ep_do_intercept_request( $request, $query, $args, $failures ) {
		$fallback_error = new \WP_Error( 'vip-elasticsearch-upstream-request-failed', 'There was an error connecting to the upstream Elasticsearch server' );

		$request = vip_safe_wp_remote_request( $query['url'], $fallback_error, 3, 1, 20, $args );
	
		return $request;
	}

	public function filter__jetpack_active_modules( $modules ) {
		// Filter out 'search' from the active modules. We use array_filter() to get _all_ instances, as it could be present multiple times
		$filtered = array_filter ( $modules, function( $module ) {
			if ( 'search' === $module ) {
				return false;
			}
			return true;
		} );

		// array_filter() preserves keys, so to get a clean / flat array we must pass it through array_values()
		return array_values( $filtered );
	}
}
