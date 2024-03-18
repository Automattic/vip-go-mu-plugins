<?php
// phpcs:disable Squiz.PHP.CommentedOutCode.Found
/**
 * HyperDB configuration file
 *
 * This file should be installed at ABSPATH/db-config.php
 *
 * $wpdb is an instance of the hyperdb class which extends the wpdb class.
 *
 * See readme.txt for documentation.
 */

/**
 * Introduction to HyperDB configuration
 *
 * HyperDB can manage connections to a large number of databases. Queries are
 * distributed to appropriate servers by mapping table names to datasets.
 *
 * A dataset is defined as a group of tables that are located in the same
 * database. There may be similarly-named databases containing different
 * tables on different servers. There may also be many replicas of a database
 * on different servers. The term "dataset" removes any ambiguity. Consider a
 * dataset as a group of tables that can be mirrored on many servers.
 *
 * Configuring HyperDB involves defining databases and datasets. Defining a
 * database involves specifying the server connection details, the dataset it
 * contains, and its capabilities and priorities for reading and writing.
 * Defining a dataset involves specifying its exact table names or registering
 * one or more callback functions that translate table names to datasets.
 */

global $wpdb, $db_servers;

$db_servers = [
	[ DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, 1, 1 ],
];

/** Variable settings **/

/**
 * save_queries (bool)
 * This is useful for debugging. Queries are saved in $wpdb->queries. It is not
 * a constant because you might want to use it momentarily.
 * Default: false
 */
$wpdb->save_queries = false;

/**
 * persistent (bool)
 * This determines whether to use mysql_connect or mysql_pconnect. The effects
 * of this setting may vary and should be carefully tested.
 * Default: false
 */
$wpdb->persistent = false;

/**
 * max_connections (int)
 * This is the number of mysql connections to keep open. Increase if you expect
 * to reuse a lot of connections to different servers. This is ignored if you
 * enable persistent connections.
 * Default: 10
 */
$wpdb->max_connections = 10;

/**
 * check_tcp_responsiveness
 * Enables checking TCP responsiveness by fsockopen prior to mysql_connect or
 * mysql_pconnect. This was added because PHP's mysql functions do not provide
 * a variable timeout setting. Disabling it may improve average performance by
 * a very tiny margin but lose protection against connections failing slowly.
 * Default: true
 */
$wpdb->check_tcp_responsiveness = true;

/** Configuration Functions **/

/**
 * $wpdb->add_database( $database );
 *
 * $database is an associative array with these parameters:
 * host          (required) Hostname with optional :port. Default port is 3306.
 * user          (required) MySQL user name.
 * password      (required) MySQL user password.
 * name          (required) MySQL database name.
 * read          (optional) Whether server is readable. Default is 1 (readable).
 *                         Also used to assign preference. See "Network topology".
 * write         (optional) Whether server is writable. Default is 1 (writable).
 *                          Also used to assign preference in multi-master mode.
 * dataset       (optional) Name of dataset. Default is 'global'.
 * timeout       (optional) Seconds to wait for TCP responsiveness. Default is 0.2
 * lag_threshold (optional) The minimum lag on a slave in seconds before we consider it lagged.
 *                          Set null to disable. When not set, the value of $wpdb->default_lag_threshold is used.
 */

/**
 * $wpdb->add_table( $dataset, $table );
 *
 * $dataset and $table are strings.
 */

/**
 * $wpdb->add_callback( $callback, $callback_group = 'dataset' );
 *
 * $callback is a callable function or method. $callback_group is the
 * group of callbacks, this $callback belongs to.
 *
 * Callbacks are executed in the order in which they are registered until one
 * of them returns something other than null.
 *
 * The default $callback_group is 'dataset'. Callback in this group
 * will be called with two arguments and expected to compute a dataset or return null.
 * $dataset = $callback($table, &$wpdb);
 *
 * Anything evaluating to false will cause the query to be aborted.
 *
 * For more complex setups, the callback may be used to overwrite properties of
 * $wpdb or variables within hyperdb::connect_db(). If a callback returns an
 * array, HyperDB will extract the array. It should be an associative array and
 * it should include a $dataset value corresponding to a database added with
 * $wpdb->add_database(). It may also include $server, which will be extracted
 * to overwrite the parameters of each randomly selected database server prior
 * to connection. This allows you to dynamically vary parameters such as the
 * host, user, password, database name, lag_threshold and TCP check timeout.
 */

/** Masters and slaves
 *
 * A database definition can include 'read' and 'write' parameters. These
 * operate as boolean switches but they are typically specified as integers.
 * They allow or disallow use of the database for reading or writing.
 *
 * A master database might be configured to allow reading and writing:
 *   'write' => 1,
 *   'read'  => 1,
 * while a slave would be allowed only to read:
 *   'write' => 0,
 *   'read'  => 1,
 *
 * It might be advantageous to disallow reading from the master, such as when
 * there are many slaves available and the master is very busy with writes.
 *   'write' => 1,
 *   'read'  => 0,
 * HyperDB tracks the tables that it has written since instantiation and sending
 * subsequent read queries to the same server that received the write query.
 * Thus a master set up this way will still receive read queries, but only
 * subsequent to writes.
 */


/**
 * Network topology / Datacenter awareness
 *
 * When your databases are located in separate physical locations there is
 * typically an advantage to connecting to a nearby server instead of a more
 * distant one. The read and write parameters can be used to place servers into
 * logical groups of more or less preferred connections. Lower numbers indicate
 * greater preference.
 *
 * This configuration instructs HyperDB to try reading from one of the local
 * slaves at random. If that slave is unreachable or refuses the connection,
 * the other slave will be tried, followed by the master, and finally the
 * remote slaves in random order.
 * Local slave 1:   'write' => 0, 'read' => 1,
 * Local slave 2:   'write' => 0, 'read' => 1,
 * Local master:    'write' => 1, 'read' => 2,
 * Remote slave 1:  'write' => 0, 'read' => 3,
 * Remote slave 2:  'write' => 0, 'read' => 3,
 *
 * In the other datacenter, the master would be remote. We would take that into
 * account while deciding where to send reads. Writes would always be sent to
 * the master, regardless of proximity.
 * Local slave 1:   'write' => 0, 'read' => 1,
 * Local slave 2:   'write' => 0, 'read' => 1,
 * Remote slave 1:  'write' => 0, 'read' => 2,
 * Remote slave 2:  'write' => 0, 'read' => 2,
 * Remote master:   'write' => 1, 'read' => 3,
 *
 * There are many ways to achieve different configurations in different
 * locations. You can deploy different config files. You can write code to
 * discover the web server's location, such as by inspecting $_SERVER or
 * php_uname(), and compute the read/write parameters accordingly. An example
 * appears later in this file using the legacy function add_db_server().
 */

/**
 * Slaves lag awareness
 *
 * HyperDB accommodates slave lag by making decisions, based on the defined lag
 * threshold. If the lag threshold is not set, it will ignore the slave lag.
 * Otherwise, it will try to find a non-lagged slave, before connecting to a lagged one.
 *
 * A slave is considered lagged, if it's replication lag is bigger than the lag threshold
 * you have defined in $wpdb->$default_lag_threshold or in the per-database settings, using
 * add_database(). You can also rewrite the lag threshold, by returning
 * $server['lag_threshold'] variable with the 'dataset' group callbacks.
 *
 * HyperDB does not check the lag on the slaves. You have to define two callbacks
 * callbacks to do that:
 *
 * $wpdb->add_callback( $callback, 'get_lag_cache' );
 *
 * and
 *
 * $wpdb->add_callback( $callback, 'get_lag' );
 *
 * The first one is called, before connecting to a slave and should return
 * the replication lag in seconds or false, if unknown, based on $wpdb->lag_cache_key.
 *
 * The second callback is called after a connection to a slave is established.
 * It should return it's replication lag or false, if unknown,
 * based on the connection in $wpdb->dbhs[ $wpdb->dbhname ].
 */

foreach ( $GLOBALS['db_servers'] as $db_server ) {
	$wpdb->add_database( array(
		'host'     => $db_server[0],
		'user'     => $db_server[1],
		'password' => $db_server[2],
		'name'     => $db_server[3],
		'read'     => $db_server[4],
		'write'    => $db_server[5],
	) );
}
