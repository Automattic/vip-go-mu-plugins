<?php
// phpcs:disable
/*
Plugin Name: HyperDB
Plugin URI: https://wordpress.org/plugins/hyperdb/
Description: An advanced database class that supports replication, failover, load balancing, and partitioning.
Author: Automattic
License: GPLv2 or later
Version: 1.9
*/

// phpcs:disable Squiz.PHP.CommentedOutCode.Found

/** This file should be installed at ABSPATH/wp-content/db.php **/

/**
 * @var wpdb|true
 * @psalm-suppress InvalidGlobal
 */
global $wpdb;

/** Load the wpdb class while preventing instantiation **/
$wpdb = true;   // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
if ( defined( 'WPDB_PATH' ) ) {
	/** @psalm-suppress UnresolvableInclude */
	require_once WPDB_PATH;
} elseif ( file_exists( ABSPATH . WPINC . '/class-wpdb.php' ) ) {
	// WP 6.1 and later.
	/** @psalm-suppress UnresolvableInclude */
	require_once ABSPATH . WPINC . '/class-wpdb.php';
} else {
	// WP 6.0 and earlier.
	/** @psalm-suppress UnresolvableInclude */
	require_once ABSPATH . WPINC . '/wp-db.php';
}

// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
if ( defined( 'DB_CONFIG_FILE' ) && file_exists( DB_CONFIG_FILE ) ) {

	/** The config file was defined earlier. **/

} elseif ( file_exists( ABSPATH . 'db-config.php' ) ) {

	/** The config file resides in ABSPATH. **/
	define( 'DB_CONFIG_FILE', ABSPATH . 'db-config.php' );

} elseif ( file_exists( dirname( ABSPATH ) . '/db-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {

	/** The config file resides one level above ABSPATH but is not part of another install. **/
	define( 'DB_CONFIG_FILE', dirname( ABSPATH ) . '/db-config.php' );

} else {

	/** Lacking a config file, revert to the standard database class. **/
	$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	return;

}

/**
 * Common definitions
 */
define( 'HYPERDB_LAG_OK', 1 );
define( 'HYPERDB_LAG_BEHIND', 2 );
define( 'HYPERDB_LAG_UNKNOWN', 3 );

define( 'HYPERDB_ER_OPTION_PREVENTS_STATEMENT', 1290 ); // The MySQL server is running with the %s option so it cannot execute this statement
define( 'HYPERDB_CONNNECTION_ERROR', 2002 ); // Can't connect to local MySQL server through socket '%s' (%d)
define( 'HYPERDB_CONN_HOST_ERROR', 2003 ); // Can't connect to MySQL server on '%s' (%d)
define( 'HYPERDB_SERVER_GONE_ERROR', 2006 ); // MySQL server has gone away

// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital
class hyperdb extends wpdb {
	/**
	 * The last table that was queried
	 * @var string
	 */
	public $last_table;

	/**
	 * After any SQL_CALC_FOUND_ROWS query, the query "SELECT FOUND_ROWS()"
	 * is sent and the mysql result resource stored here. The next query
	 * for FOUND_ROWS() will retrieve this. We do this to prevent any
	 * intervening queries from making FOUND_ROWS() inaccessible. You may
	 * prevent this by adding "NO_SELECT_FOUND_ROWS" in a comment.
	 * @var resource
	 */
	public $last_found_rows_result;

	/**
	 * Whether to store queries in an array. Useful for debugging and profiling.
	 * @var bool
	 */
	public $save_queries = false;

	/**
	 * The current mysql link resource
	 * @var mysqli|resource|false|null
	 */
	protected $dbh;

	/**
	 * Associative array (dbhname => dbh) for established mysql connections
	 * @var array
	 */
	public $dbhs;

	/**
	 * The multi-dimensional array of datasets and servers
	 * @var array
	 */
	public $hyper_servers = array();

	/**
	 * Optional directory of tables and their datasets
	 * @var array
	 */
	public $hyper_tables = array();

	/**
	 * Optional directory of callbacks to determine datasets from queries
	 * @var array
	 */
	public $hyper_callbacks = array();

	/**
	 * Custom callback to save debug info in $this->queries
	 * @var callable
	 */
	public $save_query_callback = null;

	/**
	 * Whether to use persistent connections
	 * @var bool
	 */
	public $persistent = false;

	/**
	 * The maximum number of db links to keep open. The least-recently used
	 * link will be closed when the number of links exceeds this.
	 * @var int
	 */
	public $max_connections = 10;

	/**
	 * Whether to check with fsockopen prior to connecting to mysql.
	 * @var bool
	 */
	public $check_tcp_responsiveness = true;

	/**
	 * Minimum number of connections to try before bailing
	 * @var int
	 */
	public $min_tries = 3;

	/**
	 * Send Reads To Masters. This disables slave connections while true.
	 * Otherwise it is an array of written tables.
	 * @var array
	 */
	public $srtm = array();

	/**
	 * The log of db connections made and the time each one took
	 * @var array
	 */
	public $db_connections = array();

	/**
	 * The list of unclosed connections sorted by LRU
	 */
	public $open_connections = array();

	/**
	 * The last server used and the database name selected
	 * @var array
	 */
	public $last_used_server;

	/**
	 * Lookup array (dbhname => (server, db name) ) for re-selecting the db
	 * when a link is re-used.
	 * @var array
	 */
	public $used_servers = array();

	/**
	 * Lookup array (dbhname => int) of the number of unique servers in the config
	 * @var array
	 */
	public $unique_servers = array();

	/**
	 * A list of servers we found to be read-only
	 * @var array
	 */
	public $read_only_servers = array();

	/**
	 * A list of servers' state
	 * @var array
	 */
	public $servers_state = array();

	/**
	 * Whether to save debug_backtrace in save_query_callback. You may wish
	 * to disable this, e.g. when tracing out-of-memory problems.
	 */
	public $save_backtrace = true;

	/**
	 * Maximum lag in seconds. Set null to disable. Requires callbacks.
	 * @var integer
	 */
	public $default_lag_threshold = null;

	/**
	 * Lookup array (dbhname => host:port)
	 * @var array
	 */
	public $dbh2host = array();

	/**
	 * Keeps track of the dbhname usage and errors.
	 */
	public $dbhname_heartbeats = array();

	/**
	 * Counter for how many queries have failed during the life of the $wpdb object
	 */
	public $num_failed_queries = 0;

	/**
	 * Gets ready to make database connections
	 * @param array db class vars
	 */
	public function __construct( $args = null ) {
		if ( is_array( $args ) ) {
			foreach ( get_class_vars( __CLASS__ ) as $var => $value ) {
				if ( isset( $args[ $var ] ) ) {
					$this->$var = $args[ $var ];
				}
			}
		}

		$this->use_mysqli = $this->should_use_mysqli();
		if ( $this->use_mysqli ) {
			mysqli_report( MYSQLI_REPORT_OFF );
		}

		$this->init_charset();
	}

	/**
	 * Add the connection parameters for a database
	 */
	public function add_database( $db ) {
		extract( $db, EXTR_SKIP );
		if ( ! isset( $dataset ) ) {
			$dataset = 'global';
		}

		if ( ! isset( $read ) ) {
			$read = 1;
		}

		if ( ! isset( $write ) ) {
			$write = 1;
		}

		unset( $db['dataset'] );

		if ( $read ) {
			$this->hyper_servers[ $dataset ]['read'][ $read ][] = $db;
		}
		if ( $write ) {
			$this->hyper_servers[ $dataset ]['write'][ $write ][] = $db;
		}
	}

	/**
	 * Specify the dateset where a table is found
	 */
	public function add_table( $dataset, $table ) {
		$this->hyper_tables[ $table ] = $dataset;
	}

	/**
	 * Add a callback to a group of callbacks.
	 * The default group is 'dataset', used to examine
	 * queries and determine dataset.
	 */
	public function add_callback( $callback, $group = 'dataset' ) {
		$this->hyper_callbacks[ $group ][] = $callback;
	}

	/**
	 * Find the table to be used for query routing. Falls back on
	 * core get_table_from_query after checking for special cases.
	 * @param string query
	 * @return string table
	 */
	public function get_table_from_query( $q ) {
		// Remove characters that can legally trail the table name
		$q = rtrim( $q, ';/-#' );
		// allow (select...) union [...] style queries. Use the first queries table name.
		$q = ltrim( $q, "\t (" );
		// Strip everything between parentheses except nested
		// selects and use only 1500 chars of the query
		$q = preg_replace( '/\((?!\s*select)[^(]*?\)/is', '()', substr( $q, 0, 1500 ) );

		// SELECT FOUND_ROWS() refers to the previous SELECT query
		if ( preg_match( '/^\s*SELECT.*?\s+FOUND_ROWS\(\)/is', $q ) ) {
			return $this->last_table;
		}

		// SELECT FROM information_schema.* WHERE TABLE_NAME = 'wp_12345_foo'
		if ( preg_match('/^\s*'
				. 'SELECT.*?\s+FROM\s+`?information_schema`?\.'
		. '.*\s+TABLE_NAME\s*=\s*["\']([\w-]+)["\']/is', $q, $maybe) ) {
			return $maybe[1];
		}

		// Transaction support, requires a table hint via comment: IN_TABLE=table_name
		if ( preg_match('/^\s*'
				. '(?:START\s+TRANSACTION|COMMIT|ROLLBACK)\s*\/[*]\s*IN_TABLE\s*=\s*'
		. "'?([\w-]+)'?/is", $q, $maybe) ) {
			return $maybe[1];
		}

		$this->last_table = parent::get_table_from_query( $q );
		return $this->last_table;
	}

	/**
	 * Determine the likelihood that this query could alter anything
	 * @param string query
	 * @return bool
	 */
	public function is_write_query( $q ) {
		// Quick and dirty: only SELECT statements are considered read-only.
		$q = ltrim( $q, "\r\n\t (" );
		return ! preg_match( '/^(?:SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\s/i', $q );
	}

	/**
	 * Set a flag to prevent reading from slaves which might be lagging after a write
	 */
	public function send_reads_to_masters() {
		$this->srtm = true;
	}

	/**
	 * Callbacks are executed in the order in which they are registered until one
	 * of them returns something other than null.
	 */
	public function run_callbacks( $group, $args = null ) {
		if ( ! isset( $this->hyper_callbacks[ $group ] ) || ! is_array( $this->hyper_callbacks[ $group ] ) ) {
			return null;
		}

		if ( ! isset( $args ) ) {
			$args = array( $this );
		} elseif ( is_array( $args ) ) {
			// 8.0+ changed the behavior of call_user_func_array(), associative arrays would turn into named attributes
			// Here we discard the keys and hope for the best
			$args   = array_values( $args );
			$args[] = $this;
		} else {
			$args = array( $args, $this );
		}

		foreach ( $this->hyper_callbacks[ $group ] as $func ) {
			$result = call_user_func_array( $func, $args );
			if ( isset( $result ) ) {
				return $result;
			}
		}
	}

	/**
	 * Figure out which database server should handle the query, and connect to it.
	 * @param string query
	 * @return resource mysql database connection
	 */
	public function db_connect( $query = '' ) {
		if ( empty( $query ) ) {
			return false;
		}

		$this->table = $this->get_table_from_query( $query );

		if ( isset( $this->hyper_tables[ $this->table ] ) ) {
			$dataset               = $this->hyper_tables[ $this->table ];
			$this->callback_result = null;
		} else {
			$this->callback_result = $this->run_callbacks( 'dataset', $query );
			if ( null !== $this->callback_result ) {
				if ( is_array( $this->callback_result ) ) {
					extract( $this->callback_result, EXTR_OVERWRITE );
				} else {
					$dataset = $this->callback_result;
				}
			}
		}

		if ( ! isset( $dataset ) ) {
			$dataset = 'global';
		}

		if ( ! $dataset ) {
			return $this->log_and_bail( "Unable to determine dataset (for table: $this->table)" );
		} else {
			$this->dataset = $dataset;
		}

		$this->run_callbacks( 'dataset_found', $dataset );

		if ( empty( $this->hyper_servers ) ) {
			if ( $this->is_mysql_connection( $this->dbh ) ) {
				return $this->dbh;
			}
			if (
				! defined( 'DB_HOST' )
				|| ! defined( 'DB_USER' )
				|| ! defined( 'DB_PASSWORD' )
				|| ! defined( 'DB_NAME' ) ) {
				return $this->log_and_bail( 'We were unable to query because there was no database defined' );
			}
			$this->dbh = $this->ex_mysql_connect( DB_HOST, DB_USER, DB_PASSWORD, $this->persistent );
			if ( ! $this->is_mysql_connection( $this->dbh ) ) {
				return $this->log_and_bail( 'We were unable to connect to the database. (DB_HOST)' );
			}
			if ( ! $this->ex_mysql_select_db( DB_NAME, $this->dbh ) ) {
				return $this->log_and_bail( 'We were unable to select the database' );
			}
			if ( ! empty( $this->charset ) ) {
				$collation_query = "SET NAMES '$this->charset'";
				if ( ! empty( $this->collate ) ) {
					$collation_query .= " COLLATE '$this->collate'";
				}
				$this->ex_mysql_query( $collation_query, $this->dbh );
			}
			return $this->dbh;
		}

		// Determine whether the query must be sent to the master (a writable server)
		if ( ! empty( $use_master ) || true === $this->srtm || isset( $this->srtm[ $this->table ] ) ) {
			$use_master = true;
		} elseif ( $this->is_write_query( $query ) ) {
			$use_master = true;
			if ( is_array( $this->srtm ) ) {
				$this->srtm[ $this->table ] = true;
			}
		} elseif ( ! isset( $use_master ) && is_array( $this->srtm ) && ! empty( $this->srtm ) ) {
			// Detect queries that have a join in the srtm array.
			$use_master  = false;
			$query_match = substr( $query, 0, 1000 );
			foreach ( $this->srtm as $key => $value ) {
				if ( false !== stripos( $query_match, $key ) ) {
					$use_master = true;
					break;
				}
			}
		} else {
			$use_master = false;
		}

		if ( $use_master ) {
			$dbhname       = $dataset . '__w';
			$this->dbhname = $dbhname;
			$operation     = 'write';
		} else {
			$dbhname       = $dataset . '__r';
			$this->dbhname = $dbhname;
			$operation     = 'read';
		}

		// Try to reuse an existing connection
		while ( isset( $this->dbhs[ $dbhname ] ) && $this->is_mysql_connection( $this->dbhs[ $dbhname ] ) ) {
			// Find the connection for incrementing counters
			foreach ( array_keys( $this->db_connections ) as $i ) {
				if ( $this->db_connections[ $i ]['dbhname'] == $dbhname ) {
					$conn =& $this->db_connections[ $i ];
				}
			}

			if ( isset( $server['name'] ) ) {
				$name = $server['name'];
				// A callback has specified a database name so it's possible the existing connection selected a different one.
				if ( $name != $this->used_servers[ $dbhname ]['name'] ) {
					if ( ! $this->ex_mysql_select_db( $name, $this->dbhs[ $dbhname ] ) ) {
						// this can happen when the user varies and lacks permission on the $name database
						if ( isset( $conn['disconnect (select failed)'] ) ) {
							++$conn['disconnect (select failed)'];
						} else {
							$conn['disconnect (select failed)'] = 1;
						}

						$this->disconnect( $dbhname );
						break;
					}
					$this->used_servers[ $dbhname ]['name'] = $name;
				}
			} else {
				$name = $this->used_servers[ $dbhname ]['name'];
			}

			$this->current_host = $this->dbh2host[ $dbhname ];

			// Keep this connection at the top of the stack to prevent disconnecting frequently-used connections
			$k = array_search( $dbhname, $this->open_connections );
			if ( $k ) {
				unset( $this->open_connections[ $k ] );
				$this->open_connections[] = $dbhname;
			}

			$this->last_used_server = $this->used_servers[ $dbhname ];
			$this->last_connection  = compact( 'dbhname', 'name' );

			if ( $this->should_mysql_ping() && ! $this->ex_mysql_ping( $this->dbhs[ $dbhname ] ) ) {
				if ( isset( $conn['disconnect (ping failed)'] ) ) {
					++$conn['disconnect (ping failed)'];
				} else {
					$conn['disconnect (ping failed)'] = 1;
				}

				$this->disconnect( $dbhname );
				break;
			}

			if ( isset( $conn['queries'] ) ) {
				++$conn['queries'];
			} else {
				$conn['queries'] = 1;
			}

			return $this->dbhs[ $dbhname ];
		}

		if ( $use_master && defined( 'MASTER_DB_DEAD' ) ) {
			return $this->bail( "We're updating the database, please try back in 5 minutes. If you are posting to your blog please hit the refresh button on your browser in a few minutes to post the data again. It will be posted as soon as the database is back online again." );
		}

		if ( empty( $this->hyper_servers[ $dataset ][ $operation ] ) ) {
			return $this->log_and_bail( "No databases available with $this->table ($dataset)" );
		}

		// Put the groups in order by priority
		ksort( $this->hyper_servers[ $dataset ][ $operation ] );

		// Make a list of at least $this->min_tries connections to try, repeating as necessary.
		$servers                          = array();
		$this->unique_servers[ $dbhname ] = array();
		do {
			foreach ( $this->hyper_servers[ $dataset ][ $operation ] as $group => $items ) {
				$keys = array_keys( $items );
				shuffle( $keys );
				foreach ( $keys as $key ) {
					$servers[] = compact( 'group', 'key' );

					if ( ! isset( $this->unique_servers[ $dbhname ][ $items[ $key ]['host'] ] ) ) {
						$this->unique_servers[ $dbhname ][ $items[ $key ]['host'] ] = true;
					}
				}
			}

			if ( 0 === count( $this->unique_servers[ $dbhname ] ) ) {
				return $this->log_and_bail( "No database servers were found to match the query ($this->table, $dataset)" );
			}
		} while ( count( $servers ) < $this->min_tries ); // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found

		// Connect to a database server
		do {
			$unique_lagged_slaves    = array();
			$unique_readonly_masters = array();
			$success                 = false;
			$tries_remaining         = count( $servers );

			foreach ( $servers as $group_key ) {
				--$tries_remaining;

				// Variables: $group, $key
				extract( $group_key, EXTR_OVERWRITE );

				// Variables: $host, $user, $password, $name, $read, $write [, $lag_threshold, $timeout ]
				extract( $this->hyper_servers[ $dataset ][ $operation ][ $group ][ $key ], EXTR_OVERWRITE );
				$port = null;

				// Split host:port into $host and $port
				if ( strpos( $host, ':' ) ) {
					list($host, $port) = explode( ':', $host );
				}

				// Overlay $server if it was extracted from a callback
				if ( isset( $server ) && is_array( $server ) ) {
					extract( $server, EXTR_OVERWRITE );
				}

				// Split again in case $server had host:port
				if ( strpos( $host, ':' ) ) {
					list($host, $port) = explode( ':', $host );
				}

				// Make sure there's always a port number
				if ( empty( $port ) ) {
					$port = 3306;
				}

				// Use a default timeout of 200ms
				if ( ! isset( $timeout ) ) {
					$timeout = 0.2;
				}

				// Get the minimum group here, in case $server rewrites it
				if ( ! isset( $min_group ) || $min_group > $group ) {
					$min_group = $group;
				}

				// If a master is read-only and we have others which might not be RO, go to the next one
				if ( $use_master && $this->unique_servers[ $dbhname ] > 1 && empty( $ignore_server_read_only ) ) {
					if ( count( $unique_readonly_masters ) == count( $this->unique_servers[ $dbhname ] ) ) {
						$ignore_server_read_only = true; // All are read-only, ignore RO and retry
						continue 2;
					}

					if ( $this->is_server_marked_read_only( $host, $port ) ) {
						$unique_readonly_masters[ "$host:$port" ] = true;
						continue;
					}
				}

				// Can be used by the lag callbacks
				$this->lag_cache_key = "$host:$port";
				$this->lag_threshold = isset( $lag_threshold ) ? $lag_threshold : $this->default_lag_threshold;

				// Check for a lagged slave, if applicable
				if ( ! $use_master && ! $write && ! isset( $ignore_slave_lag )
					&& isset( $this->lag_threshold ) && ! isset( $server['host'] )
					// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
					&& ( $lagged_status = $this->get_lag_cache() ) === HYPERDB_LAG_BEHIND
				) {
					// If it is the last lagged slave and it is with the best preference we will ignore its lag
					if ( ! isset( $unique_lagged_slaves[ "$host:$port" ] )
						&& count( $unique_lagged_slaves ) + 1 == count( $this->unique_servers[ $dbhname ] )
						&& $group == $min_group ) {
						$this->lag_threshold = null;
					} else {
						$unique_lagged_slaves[ "$host:$port" ] = $this->lag;
						continue;
					}
				}

				$this->timer_start();

				// Connect if necessary or possible
				$server_state = null;
				if ( $use_master || ! $tries_remaining ||
					// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
					'up' === ( $server_state = $this->get_server_state( $host, $port, $dbhname, $timeout ) ) ) {
					$this->set_connect_timeout( 'pre_connect', $use_master, $tries_remaining );
					$this->dbhs[ $dbhname ] = $this->ex_mysql_connect( "$host:$port", $user, $password, $this->persistent );
					$this->set_connect_timeout( 'post_connect', $use_master, $tries_remaining );
				} else {
					$this->dbhs[ $dbhname ] = false;
				}

				$elapsed = $this->timer_stop();

				if ( $this->is_mysql_connection( $this->dbhs[ $dbhname ] ) ) {
					/**
					 * If we care about lag, disconnect lagged slaves and try to find others.
					 * We don't disconnect if it is the last lagged slave and it is with the best preference.
					 */
					if ( ! $use_master && ! $write && ! isset( $ignore_slave_lag )
						&& isset( $this->lag_threshold ) && ! isset( $server['host'] )
						&& HYPERDB_LAG_OK !== $lagged_status
						&& ( $lagged_status = $this->get_lag() ) === HYPERDB_LAG_BEHIND // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
						&& ! (
							! isset( $unique_lagged_slaves[ "$host:$port" ] )
							&& count( $unique_lagged_slaves ) + 1 === count( $this->unique_servers[ $dbhname ] )
							&& $group == $min_group
						)
					) {
						$success                               = false;
						$unique_lagged_slaves[ "$host:$port" ] = $this->lag;
						$this->disconnect( $dbhname );
						$this->dbhs[ $dbhname ] = false;
						$msg                    = "Replication lag of {$this->lag}s on $host:$port ($dbhname)";
						$this->print_error( $msg );
						continue;
					} elseif ( $this->ex_mysql_select_db( $name, $this->dbhs[ $dbhname ] ) ) {
						$success                    = true;
						$this->current_host         = "$host:$port";
						$this->dbh2host[ $dbhname ] = "$host:$port";
						$queries                    = 1;
						$lag                        = isset( $this->lag ) ? $this->lag : 0;
						$this->last_connection      = compact( 'dbhname', 'host', 'port', 'user', 'name', 'server_state', 'elapsed', 'success', 'queries', 'lag' );
						$this->db_connections[]     = $this->last_connection;
						$this->open_connections[]   = $dbhname;
						break;
					}
				}

				if ( 'down' == $server_state ) {
					continue; // don't flood the logs if already down
				}

				$errno = $this->ex_mysql_errno();
				if ( ( HYPERDB_CONN_HOST_ERROR == $errno || HYPERDB_CONNNECTION_ERROR == $errno ) &&
					( 'up' == $server_state || ! $tries_remaining ) ) {
					$this->mark_server_as_down( $host, $port );
					$server_state = 'down';
				}

				$referrer = '';
				if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- print_error() will sanitize the entire message
					$referrer .= (string) $_SERVER['HTTP_HOST'];
				}

				if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- print_error() will sanitize the entire message
					$referrer .= (string) $_SERVER['REQUEST_URI'];
				}

				if ( ! isset( $tcp ) ) {
					$tcp = '';
				}

				if ( ! isset( $server ) ) {
					$server = '';
				}

				$success                = false;
				$this->last_connection  = compact( 'dbhname', 'host', 'port', 'user', 'name', 'tcp', 'elapsed', 'success' );
				$this->db_connections[] = $this->last_connection;
				$msg                    = date( 'Y-m-d H:i:s' ) . " Can't select $dbhname - \n";    // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$msg                   .= "'referrer' => '{$referrer}',\n";
				$msg                   .= "'server' => {$server},\n";
				$msg                   .= "'host' => {$host},\n";
				$msg                   .= "'error' => " . $this->ex_mysql_error() . ",\n";
				$msg                   .= "'errno' => " . $this->ex_mysql_errno() . ",\n";
				$msg                   .= "'server_state' => $server_state\n";
				$msg                   .= "'lagged_status' => " . ( isset( $lagged_status ) ? $lagged_status : HYPERDB_LAG_UNKNOWN );

				$this->print_error( $msg );
			}

			if ( ! $success || ! isset( $this->dbhs[ $dbhname ] ) || ! $this->is_mysql_connection( $this->dbhs[ $dbhname ] ) ) {
				if ( ! isset( $ignore_slave_lag ) && count( $unique_lagged_slaves ) ) {
					// Lagged slaves were not used. Ignore the lag for this connection attempt and retry.
					$ignore_slave_lag = true;
					continue;
				}

				$error_details = array(
					'host'      => $host,
					'port'      => $port,
					'operation' => $operation,
					'table'     => $this->table,
					'dataset'   => $dataset,
					'dbhname'   => $dbhname,
				);
				$this->run_callbacks( 'db_connection_error', $error_details );

				return $this->bail( "Unable to connect to $host:$port to $operation table '$this->table' ($dataset)" );
			}

			break;
		} while ( true );

		if ( ! isset( $charset ) ) {
			$charset = null;
		}

		if ( ! isset( $collate ) ) {
			$collate = null;
		}

		$this->set_charset( $this->dbhs[ $dbhname ], $charset, $collate );

		$this->dbh = $this->dbhs[ $dbhname ]; // needed by $wpdb->_real_escape()

		$this->last_used_server = compact( 'host', 'user', 'name', 'read', 'write' );

		$this->used_servers[ $dbhname ] = $this->last_used_server;

		// phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found
		while ( ! $this->persistent && count( $this->open_connections ) > $this->max_connections ) {
			$oldest_connection = array_shift( $this->open_connections );
			if ( $this->dbhs[ $oldest_connection ] != $this->dbhs[ $dbhname ] ) {
				$this->disconnect( $oldest_connection );
			}
		}

		return $this->dbhs[ $dbhname ];
	}

	/**
	 * Sets the connection's character set.
	 * @param resource $dbh     The resource given by ex_mysql_connect
	 * @param string   $charset The character set (optional)
	 * @param string   $collate The collation (optional)
	 */
	public function set_charset( $dbh, $charset = null, $collate = null ) {
		if ( ! isset( $charset ) ) {
			$charset = $this->charset;
		}
		if ( ! isset( $collate ) ) {
			$collate = $this->collate;
		}

		if ( ! $this->has_cap( 'collation', $dbh ) || empty( $charset ) ) {
			return;
		}

		if ( ! in_array( strtolower( $charset ), array( 'utf8', 'utf8mb4', 'latin1' ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			wp_die( sprintf( "%s charset isn't supported in HyperDB for security reasons", htmlspecialchars( $charset ) ) );
		}

		if ( $this->is_mysql_set_charset_callable() && $this->has_cap( 'set_charset', $dbh ) ) {
			$this->ex_mysql_set_charset( $charset, $dbh );
			$this->real_escape = true;
		} else {
			$query = $this->prepare( 'SET NAMES %s', $charset );
			if ( ! empty( $collate ) ) {
				$query .= $this->prepare( ' COLLATE %s', $collate );
			}
			$this->ex_mysql_query( $query, $dbh );
		}
	}

	/**
	 * Force addslashes() for the escapes.
	 *
	 * HyperDB makes connections when a query is made
	 * which is why we can't use mysql_real_escape_string() for escapes.
	 * This is also the reason why we don't allow certain charsets. See set_charset().
	 */
	public function _real_escape( $string ) {   // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$escaped = addslashes( $string );
		if ( method_exists( get_parent_class( $this ), 'add_placeholder_escape' ) ) {
			$escaped = $this->add_placeholder_escape( $escaped );
		}
		return $escaped;
	}

	/**
	 * Disconnect and remove connection from open connections list
	 * @param string $tdbhname
	 */
	public function disconnect( $dbhname ) {
		$k = array_search( $dbhname, $this->open_connections );
		if ( false !== $k ) {
			unset( $this->open_connections[ $k ] );
		}

		foreach ( array_keys( $this->db_connections ) as $i ) {
			if ( $this->db_connections[ $i ]['dbhname'] == $dbhname ) {
				unset( $this->db_connections[ $i ] );
			}
		}

		if ( $this->is_mysql_connection( $this->dbhs[ $dbhname ] ) ) {
			$this->ex_mysql_close( $this->dbhs[ $dbhname ] );
		}

		unset( $this->dbhs[ $dbhname ] );
	}

	/**
	 * Kill cached query results
	 */
	public function flush() {
		$this->last_error = '';
		$this->last_errno = 0;
		$this->num_rows   = 0;
		parent::flush();
	}

	/**
	 * Basic query. See docs for more details.
	 * @param string $query
	 * @return int number of rows
	 */
	public function query( $query ) {
		// some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
		if ( function_exists( 'apply_filters' ) ) {
			$query = apply_filters( 'query', $query );
		}

		// initialise return
		$return_val = 0;
		$this->flush();

		// Log how the function was called
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug..
		$this->last_query = $query;

		if ( preg_match( '/^\s*SELECT\s+FOUND_ROWS(\s*)/i', $query ) ) {
			if ( $this->is_mysql_result( $this->last_found_rows_result ) ) {
				$this->result                 = $this->last_found_rows_result;
				$this->last_found_rows_result = null;
				$elapsed                      = 0;
			} else {
				$this->print_error( 'Attempted SELECT FOUND_ROWS() without prior SQL_CALC_FOUND_ROWS.' );
				return false;
			}
		} else {
			$this->dbh = $this->db_connect( $query );

			if ( ! $this->is_mysql_connection( $this->dbh ) ) {
				$this->check_current_query = true;
				$this->last_error          = 'Database connection failed';
				$this->num_failed_queries++;

				if ( function_exists( 'do_action' ) ) {
					do_action( 'sql_query_log', $query, false, $this->last_error );
				}

				return false;
			}

			$query_comment = $this->run_callbacks( 'get_query_comment', $query );
			if ( ! empty( $query_comment ) ) {
				$query = rtrim( $query, ";\t \n\r" ) . ' /* ' . $query_comment . ' */';
			}

			// If we're writing to the database, make sure the query will write safely.
			if ( $this->check_current_query && method_exists( $this, 'check_ascii' ) && ! $this->check_ascii( $query ) ) {
				$stripped_query = $this->strip_invalid_text_from_query( $query );
				if ( $stripped_query !== $query ) {
					$this->insert_id  = 0;
					$this->last_error = 'Invalid query';
					$this->num_failed_queries++;

					if ( function_exists( 'do_action' ) ) {
						do_action( 'sql_query_log', $query, false, $this->last_error );
					}

					return false;
				}
			}

			$this->check_current_query = true;

			// Inject setup and teardown statements
			$statement_before_query = $this->run_callbacks( 'statement_before_query' );
			$statement_after_query  = $this->run_callbacks( 'statement_after_query' );
			$query_for_log          = $query;

			$this->timer_start();
			if ( $statement_before_query ) {
				$query_for_log = "$statement_before_query; $query_for_log";
				$this->ex_mysql_query( $statement_before_query, $this->dbh );
			}

			$this->result = $this->ex_mysql_query( $query, $this->dbh );

			if ( $statement_after_query ) {
				$query_for_log = "$query_for_log; $statement_after_query";
				$this->ex_mysql_query( $statement_after_query, $this->dbh );
			}
			$elapsed = $this->timer_stop();
			++$this->num_queries;

			if ( preg_match( '/^\s*SELECT\s+SQL_CALC_FOUND_ROWS\s/i', $query ) ) {
				if ( false === strpos( $query, 'NO_SELECT_FOUND_ROWS' ) ) {
					$this->timer_start();
					$this->last_found_rows_result = $this->ex_mysql_query( 'SELECT FOUND_ROWS()', $this->dbh );
					$elapsed                     += $this->timer_stop();
					++$this->num_queries;
					$query .= '; SELECT FOUND_ROWS()';
				}
			} else {
				$this->last_found_rows_result = null;
			}

			$this->dbhname_heartbeats[ $this->dbhname ]['last_used'] = microtime( true );

			if ( $this->save_queries ) {
				if ( is_callable( $this->save_query_callback ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
					$saved_query = call_user_func_array( $this->save_query_callback, array( $query_for_log, $elapsed, $this->save_backtrace ? debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) : null, $this ) );
					if ( null !== $saved_query ) {
						$this->queries[] = $saved_query;
					}
				} else {
					$this->queries[] = array( $query_for_log, $elapsed, $this->get_caller() );
				}
			}
		}

		$this->last_error = $this->ex_mysql_error( $this->dbh );

		if ( $this->last_error ) {
			$this->last_errno = $this->ex_mysql_errno( $this->dbh );
			$this->dbhname_heartbeats[ $this->dbhname ]['last_errno'] = $this->last_errno;

			// Write failed, use fallback master connection
			if ( HYPERDB_ER_OPTION_PREVENTS_STATEMENT == $this->last_errno &&
				false !== strpos( $this->last_error, 'read-only' ) &&
				count( $this->unique_servers[ $this->dbhname ] ) > 1 &&
				! $this->is_server_marked_read_only() ) {
				// Stay away from this server
				$this->disconnect( $this->dbhname );
				$this->mark_server_read_only();

				$msg = "Can't write to $this->dbhname - server switched to read-only mode. Retrying on configured fallback connection.";
				$this->print_error( $msg );

				return $this->query( $query );
			}

			$this->print_error( $this->last_error );
			$this->num_failed_queries++;

			if ( function_exists( 'do_action' ) ) {
				do_action( 'sql_query_log', $query, false, $this->last_error );
			}

			return false;
		}

		if ( preg_match( '/^\s*(insert|delete|update|replace|alter)\s/i', $query ) ) {
			$this->rows_affected = $this->ex_mysql_affected_rows( $this->dbh );

			if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
				$this->insert_id = $this->ex_mysql_insert_id( $this->dbh );
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} elseif ( is_bool( $this->result ) ) {
			$return_val   = $this->result;
			$this->result = null;
		} else {
			$i              = 0;
			$this->col_info = array();
			while ( $i < $this->ex_mysql_num_fields( $this->result ) ) {
				$this->col_info[ $i ] = $this->ex_mysql_fetch_field( $this->result );
				$i++;
			}
			$num_rows          = 0;
			$this->last_result = array();
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			while ( ( $row = $this->ex_mysql_fetch_object( $this->result ) ) ) {
				$this->last_result[ $num_rows ] = $row;
				$num_rows++;
			}

			$this->ex_mysql_free_result( $this->result );
			$this->result = null;

			// Log number of rows the query returned
			$this->num_rows = $num_rows;

			// Return number of rows selected
			$return_val = $this->num_rows;
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'sql_query_log', $query, $return_val, $this->last_error );
		}

		return $return_val;
	}

	/**
	 * Whether or not MySQL database is at least the required minimum version.
	 * The additional argument allows the caller to check a specific database.
	 *
	 * @since 2.5.0
	 * @uses $wp_version
	 *
	 * @return WP_Error
	 */
	public function check_database_version( $dbh_or_table = false ) {
		global $wp_version;
		// Make sure the server has MySQL 4.1.2
		$mysql_version = preg_replace( '|[^0-9\.]|', '', $this->db_version( $dbh_or_table ) );
		if ( version_compare( $mysql_version, '4.1.2', '<' ) ) {
			// translators: 1 - WordPress version
			return new WP_Error( 'database_version', sprintf( __( '<strong>ERROR</strong>: WordPress %s requires MySQL 4.1.2 or higher' ), $wp_version ) );
		}
	}

	/**
	 * This function is called when WordPress is generating the table schema to determine wether or not the current database
	 * supports or needs the collation statements.
	 * The additional argument allows the caller to check a specific database.
	 * @return bool
	 */
	public function supports_collation( $dbh_or_table = false ) {
		return $this->has_cap( 'collation', $dbh_or_table );
	}

	/**
	 * Generic function to determine if a database supports a particular feature
	 * The additional argument allows the caller to check a specific database.
	 * @param string $db_cap the feature
	 * @param false|string|resource $dbh_or_table the databaese (the current database, the database housing the specified table, or the database of the mysql resource)
	 * @return bool
	 */
	public function has_cap( $db_cap, $dbh_or_table = false ) {
		$version = $this->db_version( $dbh_or_table );

		switch ( strtolower( $db_cap ) ) :
			case 'collation':
			case 'group_concat':
			case 'subqueries':
				return version_compare( $version, '4.1', '>=' );
			case 'set_charset':
				return version_compare( $version, '5.0.7', '>=' );
			case 'utf8mb4':      // @since WP 4.1.0
				if ( version_compare( $version, '5.5.3', '<' ) ) {
					return false;
				}
				if ( $this->use_mysqli ) {
					$client_version = mysqli_get_client_info();
				} else {
					$client_version = mysql_get_client_info();
				}

				/*
				 * libmysql has supported utf8mb4 since 5.5.3, same as the MySQL server.
				 * mysqlnd has supported utf8mb4 since 5.0.9.
				 */
				if ( false !== strpos( $client_version, 'mysqlnd' ) ) {
					$client_version = preg_replace( '/^\D+([\d.]+).*/', '$1', $client_version );
					return version_compare( $client_version, '5.0.9', '>=' );
				} else {
					return version_compare( $client_version, '5.5.3', '>=' );
				}
			case 'utf8mb4_520': // since WP 4.6
				return $this->has_cap( 'utf8mb4', $dbh_or_table ) && version_compare( $version, '5.6', '>=' );
		endswitch;

		return false;
	}

	/**
	 * The database version number
	 * @param false|string|resource $dbh_or_table the database (the current database, the database housing the specified table, or the database of the mysql resource)
	 * @return false|string false on failure, version number on success
	 */
	public function db_version( $dbh_or_table = false ) {
		if ( ! $dbh_or_table && $this->dbh ) {
			$dbh =& $this->dbh;
		} elseif ( $this->is_mysql_connection( $dbh_or_table ) ) {
			$dbh =& $dbh_or_table;
		} else {
			$dbh = $this->db_connect( "SELECT FROM $dbh_or_table $this->users" );
		}

		if ( $dbh ) {
			return preg_replace( '/[^0-9.].*/', '', $this->ex_mysql_get_server_info( $dbh ) );
		}
		return false;
	}

	/**
	 * Get the name of the function that called wpdb.
	 * @return string the name of the calling function
	 */
	public function get_caller() {
		// requires PHP 4.3+
		if ( ! is_callable( 'debug_backtrace' ) ) {
			return '';
		}

		$hyper_callbacks = array();
		foreach ( $this->hyper_callbacks as $group_callbacks ) {
			$hyper_callbacks = array_merge( $hyper_callbacks, $group_callbacks );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$bt     = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		$caller = '';

		foreach ( (array) $bt as $trace ) {
			if ( isset( $trace['class'] ) && is_a( $this, $trace['class'] ) ) {
				continue;
			} elseif ( ! isset( $trace['function'] ) ) {
				continue;
			} elseif ( strtolower( $trace['function'] ) == 'call_user_func_array' ) {
				continue;
			} elseif ( strtolower( $trace['function'] ) == 'apply_filters' ) {
				continue;
			} elseif ( strtolower( $trace['function'] ) == 'do_action' ) {
				continue;
			}

			if ( in_array( strtolower( $trace['function'] ), $hyper_callbacks ) ) {
				continue;
			}

			if ( isset( $trace['class'] ) ) {
				$caller = $trace['class'] . '::' . $trace['function'];
			} else {
				$caller = $trace['function'];
			}
			break;
		}
		return $caller;
	}

	public function log_and_bail( $msg ) {
		$logged = $this->run_callbacks( 'log_and_bail', $msg );

		if ( ! $logged ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "WordPress database error $msg for query {$this->last_query} made by " . $this->get_caller() );
		}

		return $this->bail( $msg );
	}

	/**
	 * Check the responsiveness of a tcp/ip daemon
	 * @return (string) 'up' when $host:$post responds within $float_timeout seconds,
	 * otherwise a string with details about the failure.
	 */
	public function check_tcp_responsiveness( $host, $port, $float_timeout ) {
		if ( function_exists( 'apcu_store' ) ) {
			$use_apc  = true;
			$apcu_key = "tcp_responsive_{$host}{$port}";
			$apcu_ttl = 10;
		} else {
			$use_apc = false;
		}

		if ( $use_apc ) {
			$server_state = apcu_fetch( $apcu_key );
			if ( $server_state ) {
				return $server_state;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fsockopen
		$socket = @ fsockopen( $host, $port, $errno, $errstr, $float_timeout );
		if ( false === $socket ) {
			$server_state = "down [ > $float_timeout ] ($errno) '$errstr'";
			if ( $use_apc ) {
				apcu_store( $apcu_key, $server_state, $apcu_ttl );
			}

			return $server_state;
		}

		fclose( $socket );

		if ( $use_apc ) {
			apcu_store( $apcu_key, 'up', $apcu_ttl );
		}

		return 'up';
	}

	public function get_server_state( $host, $port, $dbhname, $timeout ) {
		// We still do the check_tcp_responsiveness() until we have
		// mysql connect function with less than 1 second timeout
		if ( $this->check_tcp_responsiveness && $timeout > 0 ) {
			$server_state = $this->check_tcp_responsiveness( $host, $port, $timeout );
			if ( 'up' !== $server_state ) {
				return $server_state;
			}
		}

		if ( ! empty( $this->servers_state[ "$host:$port" ] ) ) {
			return $this->servers_state[ "$host:$port" ];
		}

		if ( ! function_exists( 'apcu_store' ) ) {
			return 'up';
		}

		$server_state = apcu_fetch( "server_state_$host$port" );
		if ( ! $server_state ) {
			return 'up';
		}

		return $this->servers_state[ "$host:$port" ] = $server_state; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
	}

	public function mark_server_as_down( $host, $port, $apcu_ttl = 10 ) {
		$this->servers_state[ "$host:$port" ] = 'down';

		if ( ! function_exists( 'apcu_store' ) ) {
			return;
		}

		apcu_add( "server_state_$host$port", 'down', $apcu_ttl );
	}

	// Signal to other PHP workers that a server can not take writes
	public function mark_server_read_only( $host = null, $port = null ) {
		$host_key = $host ? "$host:$port" : $this->current_host;

		$this->read_only_servers[ $host_key ] = true;

		if ( ! function_exists( 'apcu_store' ) ) {
			return;
		}

		apcu_add( "server_readonly_$host_key", 'read_only', 120 );
	}

	public function is_server_marked_read_only( $host = null, $port = null ) {
		$host_key = $host ? "$host:$port" : $this->current_host;

		if ( ! empty( $this->read_only_servers[ $host_key ] ) ) {
			return true;
		}

		if ( ! function_exists( 'apcu_store' ) ) {
			return false;
		}

		if ( 'read_only' !== apcu_fetch( "server_readonly_$host_key" ) ) //phpcs:ignore Generic.ControlStructures.InlineControlStructure.NotAllowed
			return false;

		return $this->read_only_servers[ $host_key ] = true; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
	}

	public function set_connect_timeout( $tag, $use_master, $tries_remaining ) {
		static $default_connect_timeout;

		if ( ! isset( $default_connect_timeout ) ) {
			$default_connect_timeout = $this->ex_mysql_connect_timeout();
		}

		switch ( $tag ) {
			case 'pre_connect':
				if ( ! $use_master && $tries_remaining ) {
					$this->ex_mysql_connect_timeout( 1 );
				}
				break;
			case 'post_connect':
			default:
				if ( ! $use_master && $tries_remaining ) {
					$this->ex_mysql_connect_timeout( $default_connect_timeout );
				}
				break;
		}
	}

	public function get_lag_cache() {
		$this->lag = $this->run_callbacks( 'get_lag_cache' );

		return $this->check_lag();
	}

	public function get_lag() {
		$this->lag = $this->run_callbacks( 'get_lag' );

		return $this->check_lag();
	}

	public function check_lag() {
		if ( false === $this->lag ) {
			return HYPERDB_LAG_UNKNOWN;
		}

		if ( $this->lag > $this->lag_threshold ) {
			return HYPERDB_LAG_BEHIND;
		}

		return HYPERDB_LAG_OK;
	}

	public function should_use_mysqli() {
		if ( ! function_exists( 'mysqli_connect' ) ) {
			return false;
		}

		if ( defined( 'WP_USE_EXT_MYSQL' ) && WP_USE_EXT_MYSQL ) {
			return false;
		}

		return true;
	}

	public function should_mysql_ping() {
		// Shouldn't happen
		if ( ! isset( $this->dbhname_heartbeats[ $this->dbhname ] ) ) {
			return true;
		}

		// MySQL server has gone away
		if ( isset( $this->dbhname_heartbeats[ $this->dbhname ]['last_errno'] ) &&
			HYPERDB_SERVER_GONE_ERROR == $this->dbhname_heartbeats[ $this->dbhname ]['last_errno'] ) {
			unset( $this->dbhname_heartbeats[ $this->dbhname ]['last_errno'] );
			return true;
		}

		// More than 0.1 seconds of inactivity on that dbhname
		if ( microtime( true ) - $this->dbhname_heartbeats[ $this->dbhname ]['last_used'] > 0.1 ) {
			return true;
		}

		return false;
	}

	public function is_mysql_connection( $dbh ) {
		if ( ! $this->use_mysqli ) {
			return is_resource( $dbh );
		}

		return $dbh instanceof mysqli;
	}

	public function is_mysql_result( $result ) {
		if ( ! $this->use_mysqli ) {
			return is_resource( $result );
		}

		return $result instanceof mysqli_result;
	}

	public function is_mysql_set_charset_callable() {
		if ( ! $this->use_mysqli ) {
			return function_exists( 'mysql_set_charset' );
		}

		return function_exists( 'mysqli_set_charset' );
	}

	// MySQL execution functions.
	// They perform the appropriate calls based on whether we use MySQLi.

	public function ex_mysql_query( $query, $dbh ) {
		if ( ! $this->use_mysqli ) {
			return mysql_query( $query, $dbh );
		}

		return mysqli_query( $dbh, $query );
	}

	public function ex_mysql_unbuffered_query( $query, $dbh ) {
		if ( ! $this->use_mysqli ) {
			return mysql_unbuffered_query( $query, $dbh );
		}

		return mysqli_query( $dbh, $query, MYSQLI_USE_RESULT );
	}

	public function ex_mysql_connect( $db_host, $db_user, $db_password, $persistent ) {
		$client_flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;

		if ( ! $this->use_mysqli ) {
			$connect_function = $persistent ? 'mysql_pconnect' : 'mysql_connect';
			return @$connect_function( $db_host, $db_user, $db_password, true );
		}

		$dbh = mysqli_init();

		// mysqli_real_connect doesn't support the host param including a port or socket
		// like mysql_connect does. This duplicates how mysql_connect detects a port and/or socket file.
		$port           = null;
		$socket         = null;
		$port_or_socket = strstr( $db_host, ':' );
		if ( ! empty( $port_or_socket ) ) {
			$db_host        = substr( $db_host, 0, strpos( $db_host, ':' ) );
			$port_or_socket = substr( $port_or_socket, 1 );
			if ( 0 !== strpos( $port_or_socket, '/' ) ) {
				$port         = intval( $port_or_socket );
				$maybe_socket = strstr( $port_or_socket, ':' );
				if ( ! empty( $maybe_socket ) ) {
					$socket = substr( $maybe_socket, 1 );
				}
			} else {
				$socket = $port_or_socket;
			}
		}

		if ( $persistent ) {
			$db_host = "p:{$db_host}";
		}

		$retval = mysqli_real_connect( $dbh, $db_host, $db_user, $db_password, null, $port, $socket, $client_flags );

		if ( ! $retval || $dbh->connect_errno ) {
			return false;
		}

		return $dbh;
	}

	public function ex_mysql_select_db( $db_name, $dbh ) {
		if ( ! $this->use_mysqli ) {
			return @mysql_select_db( $db_name, $dbh );
		}

		return @mysqli_select_db( $dbh, $db_name );
	}

	public function ex_mysql_close( $dbh ) {
		if ( ! $this->use_mysqli ) {
			return mysql_close( $dbh );
		}

		return mysqli_close( $dbh );
	}

	public function ex_mysql_set_charset( $charset, $dbh ) {
		if ( ! $this->use_mysqli ) {
			return mysql_set_charset( $charset, $dbh );
		}

		return mysqli_set_charset( $dbh, $charset );
	}

	public function ex_mysql_errno( $dbh = null ) {
		if ( ! $this->use_mysqli ) {
			return is_resource( $dbh ) ? mysql_errno( $dbh ) : mysql_errno();
		}

		if ( is_null( $dbh ) ) {
			return mysqli_connect_errno();
		}

		return mysqli_errno( $dbh );
	}

	public function ex_mysql_error( $dbh = null ) {
		if ( ! $this->use_mysqli ) {
			return is_resource( $dbh ) ? mysql_error( $dbh ) : mysql_error();
		}

		if ( is_null( $dbh ) ) {
			return mysqli_connect_error();
		}

		if ( ! $this->is_mysql_connection( $dbh ) ) {
			return false;
		}

		return mysqli_error( $dbh );
	}

	public function ex_mysql_ping( $dbh ) {
		if ( ! $this->use_mysqli ) {
			return @mysql_ping( $dbh );
		}

		return @mysqli_ping( $dbh );
	}

	public function ex_mysql_affected_rows( $dbh ) {
		if ( ! $this->use_mysqli ) {
			return mysql_affected_rows( $dbh );
		}

		return mysqli_affected_rows( $dbh );
	}

	public function ex_mysql_insert_id( $dbh ) {
		if ( ! $this->use_mysqli ) {
			return mysql_insert_id( $dbh );
		}

		return mysqli_insert_id( $dbh );
	}

	public function ex_mysql_num_fields( $result ) {
		if ( ! $this->use_mysqli ) {
			return @mysql_num_fields( $result );
		}

		return @mysqli_num_fields( $result );
	}

	public function ex_mysql_fetch_field( $result ) {
		if ( ! $this->use_mysqli ) {
			return @mysql_fetch_field( $result );
		}

		return @mysqli_fetch_field( $result );
	}

	public function ex_mysql_fetch_assoc( $result ) {
		if ( ! $this->use_mysqli ) {
			return mysql_fetch_assoc( $result );
		}

		if ( ! $this->is_mysql_result( $result ) ) {
			return false;
		}

		$object = mysqli_fetch_assoc( $result );

		return ! is_null( $object ) ? $object : false;
	}

	public function ex_mysql_fetch_object( $result ) {
		if ( ! $this->use_mysqli ) {
			return @mysql_fetch_object( $result );
		}

		if ( ! $this->is_mysql_result( $result ) ) {
			return false;
		}

		$object = @mysqli_fetch_object( $result );

		return ! is_null( $object ) ? $object : false;
	}

	public function ex_mysql_fetch_row( $result ) {
		if ( ! $this->use_mysqli ) {
			return mysql_fetch_row( $result );
		}

		if ( ! $this->is_mysql_result( $result ) ) {
			return false;
		}

		$row = mysqli_fetch_row( $result );

		return ! is_null( $row ) ? $row : false;

	}

	public function ex_mysql_num_rows( $result ) {
		if ( ! $this->use_mysqli ) {
			return mysql_num_rows( $result );
		}

		return mysqli_num_rows( $result );
	}

	public function ex_mysql_free_result( $result ) {
		if ( ! $this->use_mysqli ) {
			@mysql_free_result( $result );
		}

		@mysqli_free_result( $result );
	}

	public function ex_mysql_get_server_info( $dbh ) {
		if ( ! $this->use_mysqli ) {
			return mysql_get_server_info( $dbh );
		}

		return mysqli_get_server_info( $dbh );
	}

	public function ex_mysql_connect_timeout( $timeout = null ) {
		if ( is_null( $timeout ) ) {
			if ( ! $this->use_mysqli ) {
				return ini_get( 'mysql.connect_timeout' );
			}

			return ini_get( 'default_socket_timeout' );
		}

		if ( ! $this->use_mysqli ) {
			// phpcs:ignore WordPress.PHP.IniSet.Risky
			return ini_set( 'mysql.connect_timeout', $timeout );
		}

		// phpcs:ignore WordPress.PHP.IniSet.Risky
		return ini_set( 'default_socket_timeout', $timeout );
	}
	// Helper functions for configuration

} // class hyperdb

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$wpdb = new hyperdb();

/** @psalm-suppress UnresolvableInclude */
require DB_CONFIG_FILE;
