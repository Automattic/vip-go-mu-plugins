<?php
/**
 * Offload cron event storage to a custom table
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

class Events_Store extends Singleton {
	const TABLE_SUFFIX = 'a8c_cron_control_jobs';

	const DB_VERSION        = 1;
	const DB_VERSION_OPTION = 'a8c_cron_control_db_version';

	const STATUS_PENDING   = 'pending';
	const STATUS_RUNNING   = 'running';
	const STATUS_COMPLETED = 'complete';
	const ACTIVE_STATUSES  = [ self::STATUS_PENDING, self::STATUS_RUNNING ];
	const ALLOWED_STATUSES = [ self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_COMPLETED ];

	protected function class_init() {
		if ( ! self::is_installed() ) {
			// Create tables during site installations.
			add_action( 'wp_install', array( $this, 'install' ) );

			// Keep trying in case we weren't around during the site installation.
			add_action( 'shutdown', array( $this, 'maybe_install_during_shutdown' ) );
		}

		// Handle adding/removing tables when subsites are created/deleted.
		add_action( 'wp_insert_site', array( $this, 'install' ) );
		add_filter( 'wpmu_drop_tables', array( $this, 'drop_tables_on_subsite_removal' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Custom table related methods.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Check if the table is installed.
	 */
	public static function is_installed(): bool {
		global $wpdb;

		// Can't rely on the DB_VERSION_OPTION here due to subsite copy/paste scenarios.
		// Must truly check that the table is installed.
		$is_installed = wp_cache_get( 'is_installed', 'cron-control', false, $cache_exists );
		if ( $cache_exists ) {
			return $is_installed;
		}

		$table_name = $wpdb->prefix . self::TABLE_SUFFIX;
		$is_installed = 1 === count( $wpdb->get_col( $wpdb->prepare( 'SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_NAME = %s', $table_name ) ) );

		// Cache the results, will be overridden by _prepare_table() during installation.
		wp_cache_add( 'is_installed', $is_installed, 'cron-control' );

		return $is_installed;
	}

	/**
	 * Build appropriate table name for this site.
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Run the installation process, usually for freshly created sites/subsites.
	 *
	 * @param WP_Site|null $new_site New site object during subsite creation, null for single/root site creation.
	 */
	public function install( $new_site = null ) {
		if ( ! isset( $new_site->blog_id ) ) {
			$this->_prepare_table();
			return;
		}

		// Swap over to the new subsite being created.
		switch_to_blog( (int) $new_site->blog_id );
		$this->_prepare_table();
		restore_current_blog();
	}

	/**
	 * For certain requests, create the table on shutdown if needed.
	 */
	public function maybe_install_during_shutdown() {
		$is_cron_or_cli = wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
		$is_admin = is_admin() && ! wp_doing_ajax();

		if ( ! $is_cron_or_cli && ! $is_admin ) {
			// Not a request we should try to install on.
			return;
		}

		if ( self::is_installed() ) {
			// Must have been installed earlier on in this request already.
			return;
		}

		if ( wp_cache_add( 'installation_lock', true, 'cron-control', \MINUTE_IN_SECONDS ) ) {
			// We've claimed the lock, run the installation.
			$this->_prepare_table();
		}
	}

	/**
	 * Create the plugin's DB table when necessary
	 */
	protected function _prepare_table() {
		global $wpdb;

		// Use Core's method of creating/updating tables.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}

		$table_name = $this->get_table_name();

		// Define schema and create the table.
		$schema = "CREATE TABLE `{$table_name}` (
			`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,

			`timestamp` bigint(20) unsigned NOT NULL,
			`action` varchar(255) NOT NULL,
			`action_hashed` varchar(32) NOT NULL,
			`instance` varchar(32) NOT NULL,

			`args` longtext NOT NULL,
			`schedule` varchar(255) DEFAULT NULL,
			`interval` int unsigned DEFAULT 0,
			`status` varchar(32) NOT NULL DEFAULT 'pending',

			`created` datetime NOT NULL,
			`last_modified` datetime NOT NULL,

			PRIMARY KEY (`ID`),
			UNIQUE KEY `ts_action_instance_status` (`timestamp`, `action` (191), `instance`, `status`),
			KEY `status` (`status`)
		) ENGINE=InnoDB;\n";

		dbDelta( $schema, true );

		// Confirm that the table was created, and set the option to prevent further updates.
		$is_installed = 1 === count( $wpdb->get_col( $wpdb->prepare( 'SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_NAME = %s', $table_name ) ) );
		wp_cache_set( 'is_installed', $is_installed, 'cron-control' );

		if ( $is_installed ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}

		// Clear caches now that the table exists.
		self::flush_event_cache();
	}

	/**
	 * When deleting a subsite from a multisite instance, include the plugin's table.
	 *
	 * @param array $tables_to_drop Array of prefixed table names to drop.
	 */
	public function drop_tables_on_subsite_removal( $tables_to_drop ): array {
		return array_merge( $tables_to_drop, [ $this->get_table_name() ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Methods that are likely to be deprecated in the future.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Remove entries for non-recurring events that have been run.
	 *
	 * @param bool $count_first Should events be counted before they're deleted.
	 */
	public function purge_completed_events( $count_first = true ) {
		global $wpdb;

		// Skip count if already performed.
		$count = 1;
		if ( $count_first ) {
			if ( property_exists( $wpdb, 'srtm' ) ) {
				$wpdb->srtm = true;
			}

			$count = $this->count_events_by_status( self::STATUS_COMPLETED );
		}

		if ( $count > 0 ) {
			$wpdb->delete(
				$this->get_table_name(),
				array(
					'status' => self::STATUS_COMPLETED,
				)
			);
			self::flush_event_cache();
		}
	}

	/**
	 * Count number of events with a given status
	 *
	 * @param string $status Event status to count.
	 * @return int|false
	 */
	public function count_events_by_status( $status ) {
		global $wpdb;

		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return false;
		}

		// Cannot prepare table name. @codingStandardsIgnoreLine
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$this->get_table_name()} WHERE status = %s", $status ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Internal-usage only. See comments about alternatives.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create an event.
	 * For internal use only, please use Event:save() as this method does not validate.
	 *
	 * @param array $row_data The row data used to create the event.
	 * @return int The newly created event ID, 0 if creation failed.
	 */
	public function _create_event( array $row_data ): int {
		global $wpdb;

		if ( empty( $row_data ) ) {
			return 0;
		}

		$result = $wpdb->insert( $this->get_table_name(), $row_data, self::row_formatting( $row_data ) );

		if ( isset( $row_data['action'], $row_data['instance'] ) ) {
			self::flush_event_cache( $row_data['action'], $row_data['instance'] );
		} else {
			self::flush_event_cache();
		}

		return false === $result ? 0 : $wpdb->insert_id;
	}

	/**
	 * Update an event.
	 * For internal use only, please use Event::save() as this does not validate.
	 *
	 * @param int   $event_id The ID of the event being updated.
	 * @param array $row_data The row data used to update the event.
	 * @return bool True if update was successful, false otherwise.
	 */
	public function _update_event( int $event_id, array $row_data ): bool {
		global $wpdb;

		if ( empty( $event_id ) || empty( $row_data ) ) {
			return 0;
		}

		$where  = [ 'ID' => $event_id ];
		$result = $wpdb->update( $this->get_table_name(), $row_data, $where, self::row_formatting( $row_data ), self::row_formatting( $where ) );

		if ( isset( $row_data['action'], $row_data['args'] ) ) {
			// Regenerate the initial instance because "completed" events have it randomized to avoid db constraint conflicts.
			$instance = Event::create_instance_hash( maybe_unserialize( $row_data['args'] ) );
			self::flush_event_cache( $row_data['action'], $instance );
		} else {
			self::flush_event_cache();
		}

		return false !== $result;
	}

	/**
	 * Get raw event data by an ID.
	 * For internal use only, please use Event::get( $id ).
	 *
	 * Currently no need for caching here really,
	 * the action/instance/timestamp combination is the query that often happens on the FE.
	 * So perhaps room for enhancement there later.
	 *
	 * @param int $id The ID of the event being retrieved.
	 * @return object|null Raw event object if successful, false otherwise.
	 */
	public function _get_event_raw( int $id ): ?object {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		// Cannot prepare table name. @codingStandardsIgnoreLine
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d", $id ) );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Get raw events data based on various available query args.
	 * For internal use only, please use Event::find( $args ) or Events::query( $args ).
	 *
	 * @param array $args Argument list for the query.
	 * @return array Array of raw event objects.
	 */
	public function _query_events_raw( array $args = [] ): array {
		global $wpdb;

		$valid_args = [
			'action' => [
				'default'    => null,
				'validation' => 'is_string',
			],
			'action_hashed' => [
				'default'    => null,
				'validation' => 'is_string',
			],
			'args' => [
				'default'    => null,
				'validation' => 'is_array',
			],
			'instance' => [
				'default'    => null,
				'validation' => 'is_string',
			],
			'timestamp' => [
				'default'    => null,
				'validation' => fn( $ts ) => self::validate_timestamp( $ts ),
			],
			'schedule' => [
				'default'    => null,
				'validation' => 'is_string',
			],
			'status' => [
				'default'    => self::ACTIVE_STATUSES,
				'validation' => fn( $status ) => self::validate_status( $status ),
			],
			'limit' => [
				'default'    => 100,
				'validation' => 'is_int',
			],
			'page' => [
				'default'    => 1,
				'validation' => fn( $page ) => is_int( $page ) && $page >= 1,
			],
			'orderby' => [
				'default'    => 'timestamp',
				'validation' => fn( $orderby ) => is_null( $orderby ) || ( is_string( $orderby ) && in_array( $orderby, [ 'timestamp', 'ID' ], true ) ),
			],
			'order' => [
				'default'    => 'ASC',
				'validation' => fn( $order ) => is_string( $order ) && in_array( strtoupper( $order ), [ 'ASC', 'DESC'], true ),
			],
		];

		$parsed_args = wp_parse_args( $args, array_map( fn( $arg ) => $arg['default'], $valid_args ) );

		foreach ( $valid_args as $arg_name => $arg_checks ) {
			if ( $parsed_args[ $arg_name ] !== $arg_checks['default'] ) {
				// The arg was changed from the default, let's validate it.
				if ( ! call_user_func( $arg_checks['validation'], $parsed_args[ $arg_name ] ) ) {
					trigger_error( 'Cron-Control: Invalid arguments passed in for the events query', E_USER_WARNING );
					return [];
				}
			}
		}

		$table = $this->get_table_name();
		$sql = "SELECT * FROM `{$table}` WHERE 1=1";
		$placeholders = [];

		// Timestamp can be:
		if ( ! is_null( $parsed_args['timestamp'] ) ) {
			// 1) A direct integer.
			if ( is_numeric( $parsed_args['timestamp'] ) ) {
				$sql .= ' AND timestamp = %d';
				$placeholders[] = $parsed_args['timestamp'];
			}

			// 2) Or a request for everything that is "due now".
			if ( 'due_now' === $parsed_args['timestamp'] ) {
				$sql .= ' AND timestamp <= %d';
				$placeholders[] = time();
			}

			// 3) Or a range between two timestamps.
			if ( is_array( $parsed_args['timestamp'] ) ) {
				$sql .= ' AND timestamp >= %d AND timestamp <= %d';
				$placeholders[] = $parsed_args['timestamp']['from'];
				$placeholders[] = $parsed_args['timestamp']['to'];
			}
		}

		if ( ! is_null( $parsed_args['action'] ) ) {
			$sql .= ' AND action = %s';
			$placeholders[] = $parsed_args['action'];
		}

		if ( ! is_null( $parsed_args['action_hashed'] ) ) {
			// TODO: Deprecate this query arg later once all is converted.
			$sql .= ' AND action_hashed = %s';
			$placeholders[] = $parsed_args['action_hashed'];
		}

		if ( ! is_null( $parsed_args['args'] ) ) {
			// Rather than query args directly, convert to the hash so we can utilize index.
			$instance = Event::create_instance_hash( $parsed_args['args'] );
			$sql .= ' AND instance = %s';
			$placeholders[] = $instance;
		} elseif ( ! is_null( $parsed_args['instance'] ) ) {
			// TODO: Deprecate this query arg later once all is converted.
			$sql .= ' AND instance = %s';
			$placeholders[] = $parsed_args['instance'];
		}

		if ( ! is_null( $parsed_args['schedule'] ) ) {
			$sql .= ' AND schedule = %s';
			$placeholders[] = $parsed_args['schedule'];
		}

		$requested_any_status = is_string( $parsed_args['status'] ) ? 'any' === strtolower( $parsed_args['status'] ) : false;
		if ( ! $requested_any_status ) {
			if ( is_array( $parsed_args['status'] ) ) {
				$statuses = array_map( 'strtolower', $parsed_args['status'] );
				$sql .= ' AND status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
				$placeholders = array_merge( $placeholders, $statuses );
			} elseif ( is_string( $parsed_args['status'] ) ) {
				$sql .= ' AND status = %s';
				$placeholders[] = strtolower( $parsed_args['status'] );
			}
		}

		if ( ! is_null( $parsed_args['orderby'] ) ) {
			$sql .= ' ORDER BY ' . $parsed_args['orderby'];
			$sql .= strtoupper( $parsed_args['order'] ) === 'ASC' ? ' ASC' : ' DESC';
		}

		// Skip paging/limits if "-1" was passed to get all events.
		if ( $parsed_args['limit'] >= 1 ) {
			$sql .= ' LIMIT %d';
			$placeholders[] = $parsed_args['limit'];

			if ( ! is_null( $parsed_args['page'] ) ) {
				$offset = $parsed_args['limit'] * ( $parsed_args['page'] - 1 );
				if ( $offset > 0 ) {
					$sql .= ' OFFSET %d';
					$placeholders[] = $offset;
				}
			}
		}

		$cache_group = 'cron-control-queries';
		$query_hash  = sha1( serialize( [ $sql, $placeholders ] ) );
		$cache_key   = "events::{$query_hash}::" . wp_cache_get_last_changed( $cache_group );

		// Conditionally use a more specific cache for common FE queries, helping avoid most bulk invalidations.
		$allowed_arg_count = array_key_exists( 'timestamp', $args ) ? 4 : 3;
		if ( isset( $args['limit'] ) && 1 === $args['limit'] && count( $args ) === $allowed_arg_count ) {
			$has_timestamp = array_key_exists( 'timestamp', $args ) && ! is_null( $args['timestamp'] );

			// Request was for the next event based on action/args, i.e. wp_next_scheduled()
			if ( isset( $args['action'], $args['args'] ) && ! $has_timestamp ) {
				$cache_group = 'cron-control-event';
				$hashed_args = sha1( serialize( [ 'action' => $args['action'], 'instance' => Event::create_instance_hash( $args['args'] ) ] ) );
				$cache_key   = "event::{$hashed_args}::" . wp_cache_get_last_changed( $cache_group );
			}
		}

		$results = wp_cache_get( $cache_key, $cache_group );
		if ( false === $results ) {
			// Already prepared @codingStandardsIgnoreLine
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $placeholders ) );
			$results = is_array( $results ) ? $results : [];

			wp_cache_set( $cache_key, $results, $cache_group );
		}

		return $results;
	}

	private static function validate_status( $status ): bool {
		$allowed_string_statuses = array_merge( self::ALLOWED_STATUSES, [ 'any' ] );

		if ( is_string( $status ) && in_array( strtolower( $status ), $allowed_string_statuses, true ) ) {
			return true;
		}

		if ( is_array( $status ) ) {
			$statuses = array_map( 'strtolower', $status );
			return empty( array_diff( $statuses, self::ALLOWED_STATUSES ) );
		}

		return false;
	}

	private static function validate_timestamp( $ts ): bool {
		if ( is_numeric( $ts ) ) {
			return true;
		}

		if ( is_string( $ts ) ) {
			return 'due_now' === $ts;
		}

		if ( is_array( $ts ) ) {
			return isset( $ts['from'], $ts['to'] ) && is_numeric( $ts['from'] ) && is_numeric( $ts['to'] );
		}

		return false;
	}

	private static function row_formatting( array $row ): array {
		$int_formats = [ 'ID', 'interval', 'timestamp' ];

		$formatting = [];
		foreach ( $row as $field => $value ) {
			if ( in_array( $field, $int_formats, true ) ) {
				$formatting[] = '%d';
			} else {
				// Strings for all the rest.
				$formatting[] = '%s';
			}
		}

		return $formatting;
	}

	private static function flush_event_cache( string $event_action = null, string $event_instance = null ) {
		// Always have to flush the query caches.
		wp_cache_set( 'last_changed', microtime(), 'cron-control-queries' );

		$cache_group = 'cron-control-event';
		if ( is_null( $event_action ) || is_null( $event_instance ) ) {
			// Flush the whole group when a specific event was not specified.
			wp_cache_set( 'last_changed', microtime(), $cache_group );
		} else {
			$hashed_args = sha1( serialize( [ 'action' => $event_action, 'instance' => $event_instance ] ) );
			$cache_key   = "event::{$hashed_args}::" . wp_cache_get_last_changed( $cache_group );
			wp_cache_delete( $cache_key, $cache_group );
		}
	}
}

Events_Store::instance();
