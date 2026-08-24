<?php

namespace Automattic\Memcached;

/**
 * Utilizes the PHP Memcache extension.
 * @see https://www.php.net/manual/en/book.memcache.php
 */
class Memcache_Adapter implements Adapter_Interface {
	/** @psalm-var array<string, \Memcache> */
	private array $connections = [];

	/** @psalm-var array<string, \Memcache> */
	private array $default_connections = [];

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
			$this->connections[ $bucket ] = new \Memcache();

			foreach ( $addresses as $address ) {
				$parsed_address = $this->parse_address( $address );
				$config         = $this->get_config_options();

				$this->connections[ $bucket ]->addServer(
					$parsed_address['host'],
					$parsed_address['port'],
					$config['persistent'],
					$config['weight'],
					$config['timeout'],
					$config['retry_interval'],
					$config['status'],
					$config['failure_callback'],
				);

				$this->connections[ $bucket ]->setCompressThreshold( $config['compress_threshold'], $config['min_compress_savings'] );

				// Prepare individual connections to servers in the default bucket for flush_number redundancy.
				if ( 'default' === $bucket ) {
					$memcache = new \Memcache();

					$memcache->addServer(
						$parsed_address['host'],
						$parsed_address['port'],
						$config['persistent'],
						$config['weight'],
						$config['timeout'],
						$config['retry_interval'],
						$config['status'],
						$config['failure_callback'],
					);

					$this->default_connections[ $parsed_address['host'] . ':' . $parsed_address['port'] ] = $memcache;
				}
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Connection-related adapter methods.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get a list of all connection pools, indexed by group name.
	 *
	 * @psalm-return array<string, \Memcache>
	 */
	public function get_connections() {
		return $this->connections;
	}

	/**
	 * Get a connection for each individual default server.
	 *
	 * @psalm-return array<int, \Memcache>
	 */
	public function get_default_connections() {
		return array_values( $this->default_connections );
	}

	/**
	 * Get list of servers with connection errors.
	 *
	 * @psalm-return array<array{host: string, port: string}>
	 */
	public function &get_connection_errors() {
		return $this->connection_errors;
	}

	/**
	 * Close the memcached connections.
	 * Note that Memcache::close() doesn't close persistent connections, but does free up some memory.
	 *
	 * @return bool
	 */
	public function close_connections() {
		// TODO: Should probably "close" $default_connections too?
		foreach ( $this->connections as $connection ) {
			$connection->close();
		}

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
		/** @psalm-suppress InvalidArgument -- the 3rd arg can be false */
		return $mc->add( $key, $data, false, $expiration );
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
		/** @psalm-suppress InvalidArgument -- the 3rd arg can be false */
		return $mc->replace( $key, $data, false, $expiration );
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
		/** @psalm-suppress InvalidArgument -- the 3rd arg can be false */
		return $mc->set( $key, $data, false, $expiration );
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

		$flags = false;
		/** @psalm-suppress InvalidArgument $flags can be false */
		$value = $mc->get( $key, $flags );

		/** @psalm-suppress RedundantCondition -- $flags is passed by reference so it may change*/
		return [
			'value' => $value,
			'found' => false !== $flags,
		];
	}

	/**
	 * Retrieve multiple items.
	 *
	 * @param array<string> $keys      List of keys to retrieve.
	 * @param string $connection_group The group, used to find the right connection pool.
	 *
	 * @return array<mixed>|false
	 */
	public function get_multiple( $keys, $connection_group ) {
		$mc = $this->get_connection( $connection_group );

		$return = [];
		foreach ( $keys as $key ) {
			$flags = false;
			/** @psalm-suppress InvalidArgument $flags can be false */
			$value = $mc->get( $key, $flags );

			/** @psalm-suppress RedundantCondition -- $flags is passed by reference so it may change*/
			if ( false !== $flags ) {
				// Only return if we found the value, similar to Memcached::getMulti()
				$return[ $key ] = $value;
			}
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
		return $mc->delete( $key );
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
		$mc = $this->get_connection( $connection_group );

		$return = [];
		foreach ( $keys as $key ) {
			$return[ $key ] = $mc->delete( $key );
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
		return $mc->increment( $key, $offset );
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
		return $mc->decrement( $key, $offset );
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
		foreach ( $this->default_connections as $server_string => $mc ) {
			if ( is_null( $servers_to_update ) || in_array( $server_string, $servers_to_update, true ) ) {
				$mc->set( $key, $data, $expiration );
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
		$values = [];

		foreach ( $this->default_connections as $server_string => $mc ) {
			/** @psalm-suppress MixedAssignment */
			$values[ $server_string ] = $mc->get( $key );
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
	 * @return \Memcache
	 */
	private function get_connection( $group ) {
		return $this->connections[ (string) $group ] ?? $this->connections['default'];
	}

	/**
	 * @param string $address
	 * @psalm-return array{host: string, port: int}
	 */
	private function parse_address( string $address ): array {
		$default_port = ini_get( 'memcache.default_port' ) ? ini_get( 'memcache.default_port' ) : '11211';

		if ( 'unix://' == substr( $address, 0, 7 ) ) {
			$host = $address;
			$port = 0;
		} else {
			$items = explode( ':', $address, 2 );
			$host  = $items[0];
			$port  = isset( $items[1] ) ? intval( $items[1] ) : intval( $default_port );
		}

		return [
			'host' => $host,
			'port' => $port,
		];
	}

	/**
	 * @param string $host
	 * @param string $port
	 */
	public function failure_callback( $host, $port ): void {
		$this->connection_errors[] = [
			'host' => $host,
			'port' => $port,
		];
	}

	/**
	 * @psalm-return array{
	 *   persistent: bool,
	 *   weight: int,
	 *   timeout: int,
	 *   retry_interval: int,
	 *   status: bool,
	 *   compress_threshold: int,
	 *   min_compress_savings:float,
	 *   failure_callback: callable,
	 * }
	 */
	private function get_config_options(): array {
		return [
			'persistent'           => true,
			'weight'               => 1,
			'timeout'              => 1,
			'retry_interval'       => 15,
			'status'               => true,
			'compress_threshold'   => 20000,
			'min_compress_savings' => 0.2,
			'failure_callback'     => [ $this, 'failure_callback' ],
		];
	}
}
