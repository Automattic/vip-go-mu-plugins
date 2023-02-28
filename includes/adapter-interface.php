<?php

namespace Automattic\Memcached;

interface Adapter_Interface {

	/**
	 * @psalm-param array<string,array<string>>|array<int,string> $memcached_servers
	 *
	 * @return void
	 */
	public function __construct( array $memcached_servers );

	/**
	 * Get a list of all connection pools, indexed by group name.
	 *
	 * @psalm-return array<string, \Memcached|\Memcache>
	 */
	public function get_connections();

	/**
	 * Get a connection for each individual default server.
	 *
	 * @psalm-return array<int, \Memcached|\Memcache>
	 */
	public function get_default_connections();

	/**
	 * Get list of servers with connection errors.
	 *
	 * @psalm-return array<array{host: string, port: string}>
	 */
	public function get_connection_errors();

	/**
	 * Close the memcached connections.
	 *
	 * @return bool
	 */
	public function close_connections();

	/**
	 * Add an item under a new key.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 * @param mixed  $data             The contents to store in the cache.
	 * @param int    $expiration       When to expire the cache contents, in seconds.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function add( $key, $connection_group, $data, $expiration );

	/**
	 * Replace the item under an existing key.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 * @param mixed  $data             The contents to store in the cache.
	 * @param int    $expiration       When to expire the cache contents, in seconds.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function replace( $key, $connection_group, $data, $expiration );

	/**
	 * Store an item.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 * @param mixed  $data             The contents to store in the cache.
	 * @param int    $expiration       When to expire the cache contents, in seconds.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function set( $key, $connection_group, $data, $expiration );

	/**
	 * Retrieve an item.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return array{value: mixed, found: bool}
	 */
	public function get( $key, $connection_group );

	/**
	 * Retrieve multiple items.
	 *
	 * @param array<string> $keys      List of keys to retrieve.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return array<mixed>|false
	 */
	public function get_multiple( $keys, $connection_group );

	/**
	 * Delete an item.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return boolean
	 */
	public function delete( $key, $connection_group );

	/**
	 * Delete multiple items.
	 *
	 * @param string[] $keys           Array of the full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return array<string, boolean>
	 */
	public function delete_multiple( $keys, $connection_group );

	/**
	 * Increment numeric item's value.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 * @param int    $offset           The amount by which to increment the item's value.
	 *
	 * @return false|int The new item's value on success or false on failure.
	 */
	public function increment( $key, $connection_group, $offset );

	/**
	 * Decrement numeric item's value.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 * @param int    $offset           The amount by which to decrement the item's value.
	 *
	 * @return false|int The new item's value on success or false on failure.
	 */
	public function decrement( $key, $connection_group, $offset );

	/**
	 * Set a key across all default memcached servers.
	 *
	 * @param string    $key               The full key, including group & flush prefixes.
	 * @param mixed     $data              The contents to store in the cache.
	 * @param int       $expiration        When to expire the cache contents, in seconds.
	 * @param ?string[] $servers_to_update Specific default servers to update, in string format of "host:port".
	 *
	 * @return void
	 */
	public function set_with_redundancy( $key, $data, $expiration, $servers_to_update = null );

	/**
	 * Get a key across all default memcached servers.
	 *
	 * @param string $key The full key, including group & flush prefixes.
	 *
	 * @psalm-return array<string, mixed> Key is the server's "host:port", value is returned from Memcached.
	 */
	public function get_with_redundancy( $key );
}
