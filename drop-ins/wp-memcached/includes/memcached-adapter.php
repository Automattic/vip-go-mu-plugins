<?php

namespace Automattic\Memcached;

/**
 * Utilizes the PHP Memcached extension.
 * @see https://www.php.net/manual/en/book.memcached.php
 */
class Memcached_Adapter implements Adapter_Interface {
	/** @psalm-var array<string, \Memcached> */
	private array $connections = [];

	/** @psalm-var array<int, \Memcached> */
	private array $default_connections = [];

	/** @psalm-var array<string, string> */
	private array $redundancy_server_keys = [];

	/** @psalm-var array<array{host: string, port: string}> */
	private array $connection_errors = [];

	/**
	 * @psalm-param array<string,array<string>>|array<int,string> $memcached_servers
	 *
	 * @return void
	 */
	public function __construct( array $memcached_servers ) {
		if ( is_int( key( $memcached_servers ) ) ) {
			$memcached_servers = [ 'default' => $memcached_servers ];
		}

		/** @psalm-var array<string,array<string>> $memcached_servers */
		foreach ( $memcached_servers as $bucket => $addresses ) {
			$bucket_servers = [];

			foreach ( $addresses as $index => $address ) {
				$parsed_address = $this->parse_address( $address );
				$server         = [
					'host'   => $parsed_address['host'],
					'port'   => $parsed_address['port'],
					'weight' => 1,
				];

				$bucket_servers[] = $server;

				// Prepare individual connections to servers in the default bucket for flush_number redundancy.
				if ( 'default' === $bucket ) {
					// Deprecated in this adapter. As long as no requests are made from these pools, the connections should never be established.
					$this->default_connections[] = $this->create_connection_pool( 'redundancy-' . $index, [ $server ] );
				}
			}

			$this->connections[ $bucket ] = $this->create_connection_pool( 'bucket-' . $bucket, $bucket_servers );
		}

		$this->redundancy_server_keys = $this->get_server_keys_for_redundancy();
	}

	/*
	|--------------------------------------------------------------------------
	| Connection-related adapter methods.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get a list of all connection pools, indexed by group name.
	 *
	 * @psalm-return array<string, \Memcached>
	 */
	public function get_connections() {
		return $this->connections;
	}

	/**
	 * Get a connection for each individual default server.
	 *
	 * @psalm-return array<int, \Memcached>
	 */
	public function get_default_connections() {
		return $this->default_connections;
	}

	/**
	 * Get list of servers with connection errors.
	 *
	 * @psalm-return array<array{host: string, port: string}>
	 */
	public function &get_connection_errors() {
		// Not supported atm. We could look at Memcached::getResultCode() after each call,
		// looking for MEMCACHED_CONNECTION_FAILURE for example.
		// But it wouldn't tell us the exact host/port that failed.
		// Memcached::getStats() is another possibility, though not the same behavior-wise.
		return $this->connection_errors;
	}

	/**
	 * Close the memcached connections.
	 * @return bool
	 */
	public function close_connections() {
		// Memcached::quit() closes persistent connections, which we don't want to do.
		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Main adapter methods for memcached server interactions.
	|--------------------------------------------------------------------------
	*/

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
	public function add( $key, $connection_group, $data, $expiration ) {
		$mc = $this->get_connection( $connection_group );
		return $mc->add( $this->normalize_key( $key ), $data, $expiration );
	}

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
	public function replace( $key, $connection_group, $data, $expiration ) {
		$mc = $this->get_connection( $connection_group );
		return $mc->replace( $this->normalize_key( $key ), $data, $expiration );
	}

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
	public function set( $key, $connection_group, $data, $expiration ) {
		$mc = $this->get_connection( $connection_group );
		return $mc->set( $this->normalize_key( $key ), $data, $expiration );
	}

	/**
	 * Retrieve an item.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return array{value: mixed, found: bool}
	 */
	public function get( $key, $connection_group ) {
		$mc = $this->get_connection( $connection_group );
		/** @psalm-suppress MixedAssignment */
		$value = $mc->get( $this->normalize_key( $key ) );

		return [
			'value' => $value,
			'found' => \Memcached::RES_NOTFOUND !== $mc->getResultCode(),
		];
	}

	/**
	 * Retrieve multiple items.
	 *
	 * @param array<string> $keys      List of keys to retrieve.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return array<mixed>|false Will not return anything in the array for unfound keys.
	 * @psalm-suppress MixedAssignment
	 */
	public function get_multiple( $keys, $connection_group ) {
		$mc          = $this->get_connection( $connection_group );
		$mapped_keys = $this->normalize_keys_with_mapping( $keys );

		$results = [];
		if ( count( $mapped_keys ) > 1000 ) {
			// Sending super large multiGets to a memcached server results in
			// extremely inflated read/response buffers which can consume a lot memory perpetually.
			// So instead we'll chunk up and send multiple reasonably-sized requests.
			$chunked_keys = array_chunk( $mapped_keys, 1000, true );

			foreach( $chunked_keys as $chunk_of_keys ) {
				/** @psalm-var array<string, mixed>|false $partial_results */
				$partial_results = $mc->getMulti( array_keys( $chunk_of_keys ) );

				if ( ! is_array( $partial_results ) ) {
					// If any of the lookups fail, we'll bail on the whole thing to be consistent.
					return false;
				}

				$results = array_merge( $results, $partial_results );
			}
		} else {
			/** @psalm-var array<string, mixed>|false $results */
			$results = $mc->getMulti( array_keys( $mapped_keys ) );

			if ( ! is_array( $results ) ) {
				return false;
			}
		}

		$return = [];
		foreach ( $results as $result_key => $result_value ) {
			$original_key            = isset( $mapped_keys[ $result_key ] ) ? $mapped_keys[ $result_key ] : $result_key;
			$return[ $original_key ] = $result_value;
		}

		return $return;
	}

	/**
	 * Delete an item.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return boolean
	 */
	public function delete( $key, $connection_group ) {
		$mc = $this->get_connection( $connection_group );
		return $mc->delete( $this->normalize_key( $key ) );
	}

	/**
	 * Delete multiple items.
	 *
	 * @param string[] $keys           Array of the full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return array<string, boolean>
	 */
	public function delete_multiple( $keys, $connection_group ) {
		$mc          = $this->get_connection( $connection_group );
		$mapped_keys = $this->normalize_keys_with_mapping( $keys );

		/** @psalm-var array<string, true|int> $results */
		$results = $mc->deleteMulti( array_keys( $mapped_keys ) );

		$return = [];
		foreach ( $results as $result_key => $result_value ) {
			$original_key = isset( $mapped_keys[ $result_key ] ) ? $mapped_keys[ $result_key ] : $result_key;

			// deleteMulti() returns true on success, but one of the Memcached::RES_* constants if failed.
			$return[ $original_key ] = true === $result_value;
		}

		return $return;
	}

	/**
	 * Increment numeric item's value.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 * @param int    $offset           The amount by which to increment the item's value.
	 *
	 * @return false|int The new item's value on success or false on failure.
	 */
	public function increment( $key, $connection_group, $offset ) {
		$mc = $this->get_connection( $connection_group );
		return $mc->increment( $this->normalize_key( $key ), $offset );
	}

	/**
	 * Decrement numeric item's value.
	 *
	 * @param string $key              The full key, including group & flush prefixes.
	 * @param string $connection_group The group, used to find the right connection pool.
	 * @param int    $offset           The amount by which to decrement the item's value.
	 *
	 * @return false|int The new item's value on success or false on failure.
	 */
	public function decrement( $key, $connection_group, $offset ) {
		$mc = $this->get_connection( $connection_group );
		return $mc->decrement( $this->normalize_key( $key ), $offset );
	}

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
	public function set_with_redundancy( $key, $data, $expiration, $servers_to_update = null ) {
		$mc = $this->connections['default'];

		foreach ( $this->redundancy_server_keys as $server_string => $server_key ) {
			if ( is_null( $servers_to_update ) || in_array( $server_string, $servers_to_update, true ) ) {
				$mc->setByKey( $server_key, $key, $data, $expiration );
			}
		}
	}

	/**
	 * Get a key across all default memcached servers.
	 *
	 * @param string $key The full key, including group & flush prefixes.
	 *
	 * @psalm-return array<string, mixed> Key is the server's "host:port", value is returned from Memcached.
	 */
	public function get_with_redundancy( $key ) {
		$mc = $this->connections['default'];

		$values = [];
		foreach ( $this->redundancy_server_keys as $server_string => $server_key ) {
			/** @psalm-suppress MixedAssignment */
			$values[ $server_string ] = $mc->getByKey( $server_key, $key );
		}

		return $values;
	}

	/*
	|--------------------------------------------------------------------------
	| Utils.
	|--------------------------------------------------------------------------
	*/

	/**
	 * @param int|string $group
	 * @return \Memcached
	 */
	private function get_connection( $group ) {
		return $this->connections[ (string) $group ] ?? $this->connections['default'];
	}

	/**
	 * Servers and configurations are persisted between requests.
	 * So we only want to add servers when the configuration has changed.
	 *
	 * @param string $name
	 * @psalm-param array<int, array{host: string, port: int, weight: int}> $servers
	 * @return \Memcached
	 */
	private function create_connection_pool( $name, $servers ) {
		$mc = new \Memcached( $name );

		// Servers and configurations are persisted between requests.
		/** @psalm-var array<int,array{host: string, port: int, type: string}> $existing_servers */
		$existing_servers = $mc->getServerList();

		// Check if the servers have changed since they were registered.
		$needs_refresh = count( $existing_servers ) !== count( $servers );
		foreach ( $servers as $index => $server ) {
			$existing_host = $existing_servers[ $index ]['host'] ?? null;
			$existing_port = $existing_servers[ $index ]['port'] ?? null;

			if ( $existing_host !== $server['host'] || $existing_port !== $server['port'] ) {
				$needs_refresh = true;
			}
		}

		if ( $needs_refresh ) {
			$mc->resetServerList();

			$servers_to_add = [];
			foreach ( $servers as $server ) {
				$servers_to_add[] = [ $server['host'], $server['port'], $server['weight'] ];
			}

			$mc->addServers( $servers_to_add );
			$mc->setOptions( $this->get_config_options() );
		}

		return $mc;
	}

	/**
	 * @param string $address
	 * @psalm-return array{host: string, port: int}
	 */
	private function parse_address( string $address ): array {
		$default_port = 11211;

		if ( 'unix://' == substr( $address, 0, 7 ) ) {
			// Note: This slighly differs from the memcache adapater, as memcached wants unix:// stripped off.
			$host = substr( $address, 7 );
			$port = 0;
		} else {
			$items = explode( ':', $address, 2 );
			$host  = $items[0];
			$port  = isset( $items[1] ) ? intval( $items[1] ) : $default_port;
		}

		return [
			'host' => $host,
			'port' => $port,
		];
	}

	private function get_config_options(): array {
		/** @psalm-suppress TypeDoesNotContainType */
		$serializer = \Memcached::HAVE_IGBINARY && extension_loaded( 'igbinary' ) ? \Memcached::SERIALIZER_IGBINARY : \Memcached::SERIALIZER_PHP;

		// TODO: Check memcached.compression_threshold / memcached.compression_factor
		// These are all TBD still.
		return [
			\Memcached::OPT_BINARY_PROTOCOL => false,
			\Memcached::OPT_SERIALIZER      => $serializer,
			\Memcached::OPT_CONNECT_TIMEOUT => 1000,
			\Memcached::OPT_COMPRESSION     => true,
			\Memcached::OPT_TCP_NODELAY     => true,
		];
	}

	/**
	 * We want to find a unique string that, when hashed, will map to each
	 * of the default servers in our pool. This will allow us to
	 * talk with each server individually and set a key with redundancy.
	 *
	 * @psalm-return array<string, string> Key is the server's "host:port", value is the server_key that accesses it.
	 */
	private function get_server_keys_for_redundancy(): array {
		$default_pool = $this->connections['default'];
		$servers      = $default_pool->getServerList();

		$server_keys = [];
		for ( $i = 0; $i < 1000; $i++ ) {
			$test_key = 'redundancy_key_' . $i;

			/** @psalm-var array{host: string, port: int, weight: int}|false $result */
			$result = $default_pool->getServerByKey( $test_key );

			if ( ! $result ) {
				continue;
			}

			$server_string = $result['host'] . ':' . $result['port'];
			if ( ! isset( $server_keys[ $server_string ] ) ) {
				$server_keys[ $server_string ] = $test_key;
			}

			// Keep going until every server is accounted for (capped at 1000 attempts - which is incredibly unlikely unless there are tons of servers).
			if ( count( $server_keys ) === count( $servers ) ) {
				break;
			}
		}

		return $server_keys;
	}

	/**
	 * Memcached can only handle keys with a length of 250 or less.
	 * The Memcache extension automatically truncates. Memcached does not.
	 * Instead of truncation, which can lead to some hidden consequences,
	 * we can hash the string and use a shortened version when interacting
	 * with the Memcached servers.
	 *
	 * @param string $key
	 * @psalm-return string
	 */
	private function normalize_key( $key ) {
		if ( strlen( $key ) <= 250 ) {
			return $key;
		} else {
			return substr( $key, 0, 200 ) . ':truncated:' . md5( $key );
		}
	}

	/**
	 * Reduce key lenths while providing a map of new_key => original_key.
	 *
	 * @param string[] $keys
	 * @psalm-return array<string, string>
	 */
	private function normalize_keys_with_mapping( $keys ) {
		$mapped_keys = [];

		foreach ( $keys as $key ) {
			$mapped_keys[ $this->normalize_key( $key ) ] = $key;
		}

		return $mapped_keys;
	}
}
