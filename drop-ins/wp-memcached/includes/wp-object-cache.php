<?php

use Automattic\Memcached\Adapter_Interface;
use Automattic\Memcached\Memcached_Adapter;
use Automattic\Memcached\Memcache_Adapter;
use Automattic\Memcached\Stats;

// Note that this class is in the global namespace for backwards-compatibility reasons.
#[\AllowDynamicProperties]
class WP_Object_Cache {
	public string $flush_group        = 'WP_Object_Cache';
	public string $global_flush_group = 'WP_Object_Cache_global';
	public string $flush_key          = 'flush_number_v5';

	/**
	 * Keep track of flush numbers.
	 * The array key is the blog prefix and value is the number.
	 * @var array<string,int>
	 */
	public array $flush_number = [];

	public ?int $global_flush_number = null;
	public string $global_prefix     = '';
	public string $blog_prefix       = '';
	public string $key_salt          = '';

	/**
	 * Global cache groups (network-wide rather than site-specific).
	 * @var string[]
	 */
	public array $global_groups = [];

	/**
	 * Non-persistent cache groups (will not write to Memcached servers).
	 * @var string[]
	 */
	public array $no_mc_groups = [];

	public int $default_expiration = 0;
	public int $max_expiration     = 2592000; // 30 days

	private Adapter_Interface $adapter;
	private Stats $stats_helper;

	/** @psalm-var array<string, Memcached|Memcache> */
	public array $mc = [];

	/** @psalm-var array<int, Memcached|Memcache> */
	public array $default_mcs = [];

	/**
	 * @psalm-var array<string, array{value: mixed, found: bool}>
	 */
	public array $cache = [];

	// Stats tracking.
	public array $stats                = [];
	public array $group_ops            = [];
	public int $cache_hits             = 0;
	public int $cache_misses           = 0;
	public float $time_start           = 0;
	public float $time_total           = 0;
	public int $size_total             = 0;
	public float $slow_op_microseconds = 0.005; // 5 ms

	// TODO: Deprecate. These appear to be unused.
	public string $old_flush_key = 'flush_number';
	public bool $cache_enabled   = true;
	public array $stats_callback = [];
	/** @psalm-var array<array{host: string, port: string}> */
	public array $connection_errors = [];

	/**
	 * @global array<string,array<string>>|array<int,string>|null $memcached_servers
	 * @global string $table_prefix
	 * @global int|numeric-string $blog_id
	 *
	 * @param ?Adapter_Interface $adapter Optionally inject the adapter layer, useful for unit testing.
	 * @psalm-suppress UnsupportedReferenceUsage
	 */
	public function __construct( $adapter = null ) {
		global $blog_id, $table_prefix, $memcached_servers;

		$this->global_groups = [ $this->global_flush_group ];

		$is_ms = function_exists( 'is_multisite' ) && is_multisite();

		$this->global_prefix = $is_ms || ( defined( 'CUSTOM_USER_TABLE' ) && defined( 'CUSTOM_USER_META_TABLE' ) ) ? '' : $table_prefix;
		$this->blog_prefix   = (string) ( $is_ms ? $blog_id : $table_prefix );

		$use_memcached = defined( 'AUTOMATTIC_MEMCACHED_USE_MEMCACHED_EXTENSION' ) && AUTOMATTIC_MEMCACHED_USE_MEMCACHED_EXTENSION;
		if ( ! is_null( $adapter ) ) {
			$this->adapter = $adapter;
		} else {
			$servers       = is_array( $memcached_servers ) ? $memcached_servers : [ 'default' => [ '127.0.0.1:11211' ] ];
			$this->adapter = $use_memcached ? new Memcached_Adapter( $servers ) : new Memcache_Adapter( $servers );
		}

		$this->salt_keys( WP_CACHE_KEY_SALT, $use_memcached );

		// Backwards compatability as these have been public properties. Ideally we deprecate and remove in the future.
		$this->mc                = $this->adapter->get_connections();
		$this->default_mcs       = $this->adapter->get_default_connections();
		$this->connection_errors =& $this->adapter->get_connection_errors();

		$this->stats_helper = new Stats( $this->key_salt );

		// Also for backwards compatability since these have been public properties.
		$this->stats                =& $this->stats_helper->stats;
		$this->group_ops            =& $this->stats_helper->group_ops;
		$this->time_total           =& $this->stats_helper->time_total;
		$this->size_total           =& $this->stats_helper->size_total;
		$this->slow_op_microseconds =& $this->stats_helper->slow_op_microseconds;
		$this->cache_hits           =& $this->stats['get'];
		$this->cache_misses         =& $this->stats['add'];
	}

	/*
	|--------------------------------------------------------------------------
	| The main methods used by the cache API.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Optional. When to expire the cache contents, in seconds.
	 *                           Default 0 (no expiration).
	 * @return bool True on success, false on failure or if cache key and group already exist.
	 */
	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		$is_alloptions = 'alloptions' === $key && 'options' === $group;
		$key           = $this->key( $key, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( $this->is_non_persistent_group( $group ) ) {
			if ( isset( $this->cache[ $key ] ) ) {
				return false;
			}

			$this->cache[ $key ] = [
				'value' => $data,
				'found' => false,
			];

			return true;
		}

		if ( isset( $this->cache[ $key ]['value'] ) && false !== $this->cache[ $key ]['value'] ) {
			return false;
		}

		$expire = $this->get_expiration( $expire );
		$size   = $this->get_data_size( $data );

		$this->timer_start();
		$result  = $this->adapter->add( $key, $group, $data, $expire );
		$elapsed = $this->timer_stop();

		$comment = '';
		if ( isset( $this->cache[ $key ] ) ) {
			$comment .= ' [lc already]';
		}

		if ( false === $result ) {
			$comment .= ' [mc already]';
		}

		$this->group_ops_stats( 'add', $key, $group, $size, $elapsed, $comment );

		// Special handling for alloptions on WP < 6.2 (before pre_wp_load_alloptions filter).
		// A) If the add() fails,
		if ( false === $result && $is_alloptions && version_compare( $GLOBALS['wp_version'], '6.2', '<' ) ) {
			// B) And there is still nothing retrieved with a remote get(),
			if ( false === $this->get( 'alloptions', 'options', true ) ) {
				// C) Then we'll keep the fresh value in the runtime cache to help keep performance stable.
				$this->cache[ $key ] = [
					'value' => $data,
					'found' => false,
				];
			}

			return $result;
		}

		if ( $result ) {
			$this->cache[ $key ] = [
				'value' => $data,
				'found' => true,
			];
		} elseif ( isset( $this->cache[ $key ]['value'] ) && false === $this->cache[ $key ]['value'] ) {
			/*
				* Here we unset local cache if remote add failed and local cache value is equal to `false` in order
				* to update the local cache anytime we get a new information from remote server. This way, the next
				* cache get will go to remote server and will fetch recent data.
				*/
			unset( $this->cache[ $key ] );
		}

		return $result;
	}

	/**
	 * Adds multiple values to the cache in one call.
	 *
	 * @param mixed[]  $data   Array of keys and values to be added.
	 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int    $expire Optional. When to expire the cache contents, in seconds.
	 *                       Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false on failure or if cache key and group already exist.
	 */
	public function add_multiple( $data, $group = '', $expire = 0 ) {
		$result = [];

		/** @psalm-suppress MixedAssignment - $value is unknown/mixed */
		foreach ( $data as $key => $value ) {
			$result[ $key ] = $this->add( $key, $value, $group, $expire );
		}

		return $result;
	}

	/**
	 * Replaces the contents in the cache, if contents already exist.
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Optional. When to expire the cache contents, in seconds.
	 *                           Default 0 (no expiration).
	 * @return bool True if contents were replaced, false on failure or if the original value did not exist.
	 */
	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $key, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( $this->is_non_persistent_group( $group ) ) {
			if ( ! isset( $this->cache[ $key ] ) ) {
				return false;
			}

			$this->cache[ $key ]['value'] = $data;
			return true;
		}

		$expire = $this->get_expiration( $expire );
		$size   = $this->get_data_size( $data );

		$this->timer_start();
		$result  = $this->adapter->replace( $key, $group, $data, $expire );
		$elapsed = $this->timer_stop();

		$this->group_ops_stats( 'replace', $key, $group, $size, $elapsed );

		if ( $result ) {
			$this->cache[ $key ] = [
				'value' => $data,
				'found' => true,
			];
		} else {
			// Remove from local cache if the replace failed, as it may no longer exist.
			unset( $this->cache[ $key ] );
		}

		return $result;
	}

	/**
	 * Sets the data contents into the cache.
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Optional. How long until the cahce contents will expire (in seconds).
	 *
	 * @return bool True if contents were set, false if failed.
	 */
	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $key, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( $this->is_non_persistent_group( $group ) ) {
			$this->group_ops_stats( 'set_local', $key, $group );

			$this->cache[ $key ] = [
				'value' => $data,
				'found' => false,
			];

			return true;
		}

		$expire = $this->get_expiration( $expire );
		$size   = $this->get_data_size( $data );

		$this->timer_start();
		$result  = $this->adapter->set( $key, $group, $data, $expire );
		$elapsed = $this->timer_stop();

		$this->group_ops_stats( 'set', $key, $group, $size, $elapsed );

		$this->cache[ $key ] = [
			'value' => $data,
			'found' => $result,
		];

		return $result;
	}

	/**
	 * Sets multiple values to the cache in one call.
	 *
	 * @param mixed[] $data Array of key and value to be set.
	 * @param string  $group  Optional. Where the cache contents are grouped. Default empty.
	 * @param int     $expire Optional. When to expire the cache contents, in seconds.
	 *                        Default 0 (no expiration).
	 * @return bool[] Array of return values, grouped by key. Value is true on success, false on failure.
	 */
	public function set_multiple( $data, $group = '', $expire = 0 ) {
		$result = [];

		/** @psalm-suppress MixedAssignment - $value is mixed */
		foreach ( $data as $key => $value ) {
			// TODO: Could try to make Memcached::setMulti() work, though the return structure differs.
			$result[ $key ] = $this->set( $key, $value, $group, $expire );
		}

		return $result;
	}

	/**
	 * Retrieves the cache contents, if it exists.
	 *
	 * @param int|string $key   The key under which the cache contents are stored.
	 * @param string     $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool       $force Optional. Whether to force an update of the local cache
	 *                          from the persistent cache. Default false.
	 * @param bool       $found Optional. Whether the key was found in the cache (passed by reference).
	 *                          Disambiguates a return of false, a storable value. Default null.
	 * @return mixed|false The cache contents on success, false on failure to retrieve contents.
	 */
	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$key = $this->key( $key, $group );

		if ( $force && $this->is_non_persistent_group( $group ) ) {
			// There's nothing to "force" retrieve.
			$force = false;
		}

		if ( isset( $this->cache[ $key ] ) && ! $force ) {
			/** @psalm-suppress MixedAssignment */
			$value = is_object( $this->cache[ $key ]['value'] ) ? clone $this->cache[ $key ]['value'] : $this->cache[ $key ]['value'];
			$found = $this->cache[ $key ]['found'];

			$this->group_ops_stats( 'get_local', $key, $group, null, null, 'local' );

			return $value;
		}

		// For a non-persistant group, if it's not in local cache then it just doesn't exist.
		if ( $this->is_non_persistent_group( $group ) ) {
			$found = false;
			$this->group_ops_stats( 'get_local', $key, $group, null, null, 'not_in_local' );

			return false;
		}

		$this->timer_start();
		/** @psalm-suppress MixedAssignment */
		$result  = $this->adapter->get( $key, $group );
		$elapsed = $this->timer_stop();

		$this->cache[ $key ] = $result;
		$found               = $result['found'];

		if ( $result['found'] ) {
			$this->group_ops_stats( 'get', $key, $group, $this->get_data_size( $result['value'] ), $elapsed, 'memcache' );
		} else {
			$this->group_ops_stats( 'get', $key, $group, null, $elapsed, 'not_in_memcache' );
		}

		return $result['value'];
	}

	/**
	 * Retrieves multiple values from the cache in one call.
	 *
	 * @param array<string|int> $keys Array of keys under which the cache contents are stored.
	 * @param string $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool   $force Optional. Whether to force an update of the local cache
	 *                      from the persistent cache. Default false.
	 * @return mixed[] Array of return values, grouped by key. Each value is either
	 *                 the cache contents on success, or false on failure.
	 */
	public function get_multiple( $keys, $group = 'default', $force = false ) {
		$uncached_keys = [];
		$return        = [];
		$return_cache  = [];

		if ( $force && $this->is_non_persistent_group( $group ) ) {
			// There's nothing to "force" retrieve.
			$force = false;
		}

		// First, fetch what we can from runtime cache.
		foreach ( $keys as $key ) {
			$cache_key = $this->key( $key, $group );

			if ( isset( $this->cache[ $cache_key ] ) && ! $force ) {
				/** @psalm-suppress MixedAssignment */
				$return[ $key ] = is_object( $this->cache[ $cache_key ]['value'] ) ? clone $this->cache[ $cache_key ]['value'] : $this->cache[ $cache_key ]['value'];

				$this->group_ops_stats( 'get_local', $cache_key, $group, null, null, 'local' );
			} elseif ( $this->is_non_persistent_group( $group ) ) {
				$return[ $key ] = false;
				$this->group_ops_stats( 'get_local', $cache_key, $group, null, null, 'not_in_local' );
			} else {
				$uncached_keys[ $key ] = $cache_key;
			}
		}

		if ( ! empty( $uncached_keys ) ) {
			$this->timer_start();
			$values  = $this->adapter->get_multiple( array_values( $uncached_keys ), $group );
			$elapsed = $this->timer_stop();

			$values = false === $values ? [] : $values;
			foreach ( $uncached_keys as $key => $cache_key ) {
				$found = array_key_exists( $cache_key, $values );
				/** @psalm-suppress MixedAssignment */
				$value = $found ? $values[ $cache_key ] : false;

				/** @psalm-suppress MixedAssignment */
				$return[ $key ]             = $value;
				$return_cache[ $cache_key ] = [
					'value' => $value,
					'found' => $found,
				];
			}

			$this->group_ops_stats( 'get_multiple', array_values( $uncached_keys ), $group, $this->get_data_size( array_values( $values ) ), $elapsed );
		}

		$this->cache = array_merge( $this->cache, $return_cache );
		return $return;
	}

	/**
	 * Retrieves multiple values from the cache in one call.
	 *
	 * @param array<string, array<string|int>> $groups  Array of keys, indexed by group.
	 *                                                  Example: $groups['group-name'] = [ 'key1', 'key2' ]
	 *
	 * @return mixed[] Array of return values, grouped by key. Each value is either
	 *                 the cache contents on success, or false on failure.
	 */
	public function get_multi( $groups ) {
		$return = [];

		foreach ( $groups as $group => $keys ) {
			$results = $this->get_multiple( $keys, $group );

			foreach ( $keys as $key ) {
				// This feels like a bug, as the full cache key is not useful to consumers. But alas, should be deprecating this method soon anyway.
				$cache_key = $this->key( $key, $group );

				/** @psalm-suppress MixedAssignment */
				$return[ $cache_key ] = isset( $results[ $key ] ) ? $results[ $key ] : false;
			}
		}

		return $return;
	}

	/**
	 * Removes the contents of the cache key in the group.
	 *
	 * @param int|string $key   What the contents in the cache are called.
	 * @param string     $group Optional. Where the cache contents are grouped. Default 'default'.
	 *
	 * @return bool True on success, false on failure or if the contents were not deleted.
	 */
	public function delete( $key, $group = 'default' ) {
		$key = $this->key( $key, $group );

		if ( $this->is_non_persistent_group( $group ) ) {
			$result = isset( $this->cache[ $key ] );
			unset( $this->cache[ $key ] );

			return $result;
		}

		$this->timer_start();
		$deleted = $this->adapter->delete( $key, $group );
		$elapsed = $this->timer_stop();

		$this->group_ops_stats( 'delete', $key, $group, null, $elapsed );

		// Remove from local cache regardless of the result.
		unset( $this->cache[ $key ] );

		return $deleted;
	}

	/**
	 * Deletes multiple values from the cache in one call.
	 *
	 * @param array<string|int> $keys  Array of keys to be deleted.
	 * @param string $group Optional. Where the cache contents are grouped. Default empty.
	 * @return bool[] Array of return values, grouped by key. Each value is either
	 *                true on success, or false if the contents were not deleted.
	 */
	public function delete_multiple( $keys, $group = '' ) {
		if ( $this->is_non_persistent_group( $group ) ) {
			$return = [];

			foreach ( $keys as $key ) {
				$cache_key = $this->key( $key, $group );

				$deleted = isset( $this->cache[ $cache_key ] );
				unset( $this->cache[ $cache_key ] );

				$return[ $key ] = $deleted;
			}

			return $return;
		}

		$mapped_keys = $this->map_keys( $keys, $group );

		$this->timer_start();
		$results = $this->adapter->delete_multiple( array_keys( $mapped_keys ), $group );
		$elapsed = $this->timer_stop();

		$this->group_ops_stats( 'delete_multiple', array_keys( $mapped_keys ), $group, null, $elapsed );

		$return = [];
		foreach ( $results as $cache_key => $deleted ) {
			$return[ $mapped_keys[ $cache_key ] ] = $deleted;

			// Remove from local cache regardless of the result.
			unset( $this->cache[ $cache_key ] );
		}

		return $return;
	}

	/**
	 * Increments numeric cache item's value.
	 *
	 * @param int|string $key    The cache key to increment.
	 * @param int        $offset Optional. The amount by which to increment the item's value.
	 *                           Default 1.
	 * @param string     $group  Optional. The group the key is in. Default 'default'.
	 * @return int|false The item's new value on success, false on failure.
	 */
	public function incr( $key, $offset = 1, $group = 'default' ) {
		$key = $this->key( $key, $group );

		if ( $this->is_non_persistent_group( $group ) ) {
			if ( ! isset( $this->cache[ $key ] ) || ! is_int( $this->cache[ $key ]['value'] ) ) {
				return false;
			}

			$this->cache[ $key ]['value'] += $offset;
			return $this->cache[ $key ]['value'];
		}

		$this->timer_start();
		$incremented = $this->adapter->increment( $key, $group, $offset );
		$elapsed     = $this->timer_stop();

		$this->group_ops_stats( 'increment', $key, $group, null, $elapsed );

		$this->cache[ $key ] = [
			'value' => $incremented,
			'found' => false !== $incremented,
		];

		return $incremented;
	}

	/**
	 * Decrements numeric cache item's value.
	 *
	 * @param int|string $key    The cache key to decrement.
	 * @param int        $offset Optional. The amount by which to decrement the item's value.
	 *                           Default 1.
	 * @param string     $group  Optional. The group the key is in. Default 'default'.
	 * @return int|false The item's new value on success, false on failure.
	 */
	public function decr( $key, $offset = 1, $group = 'default' ) {
		$key = $this->key( $key, $group );

		if ( $this->is_non_persistent_group( $group ) ) {
			if ( ! isset( $this->cache[ $key ] ) || ! is_int( $this->cache[ $key ]['value'] ) ) {
				return false;
			}

			$new_value = $this->cache[ $key ]['value'] - $offset;
			if ( $new_value < 0 ) {
				$new_value = 0;
			}

			$this->cache[ $key ]['value'] = $new_value;
			return $this->cache[ $key ]['value'];
		}

		$this->timer_start();
		$decremented = $this->adapter->decrement( $key, $group, $offset );
		$elapsed     = $this->timer_stop();

		$this->group_ops_stats( 'decrement', $key, $group, null, $elapsed );

		$this->cache[ $key ] = [
			'value' => $decremented,
			'found' => false !== $decremented,
		];

		return $decremented;
	}

	/**
	 * Clears the object cache of all data.
	 *
	 * Purposely does not use the memcached flush method,
	 * as that acts on the entire memcached server, affecting all sites.
	 * Instead, we rotate the key prefix for the current site,
	 * along with the global key when flushing the main site.
	 *
	 * @return true Always returns true.
	 */
	public function flush() {
		$this->cache = [];

		$flush_number = $this->new_flush_number();

		$this->rotate_site_keys( $flush_number );
		if ( is_main_site() ) {
			$this->rotate_global_keys( $flush_number );
		}

		return true;
	}

	/**
	 * Unsupported: Removes all cache items in a group.
	 *
	 * @param string $_group Name of group to remove from cache.
	 * @return bool Returns false, as there is no support for group flushes.
	 */
	public function flush_group( $_group ) {
		return false;
	}

	/**
	 * Removes all cache items from the in-memory runtime cache.
	 * Also reset the local stat-related tracking for individual operations.
	 *
	 * @return true Always returns true.
	 */
	public function flush_runtime() {
		$this->cache     = [];
		$this->group_ops = [];

		return true;
	}

	/**
	 * Sets the list of global cache groups.
	 *
	 * @param string|string[] $groups List of groups that are global.
	 * @return void
	 */
	public function add_global_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->global_groups = array_unique( array_merge( $this->global_groups, $groups ) );
	}

	/**
	 * Sets the list of non-persistent groups.
	 *
	 * @param string|string[] $groups List of groups that will not be saved to persistent cache.
	 * @return void
	 */
	public function add_non_persistent_groups( $groups ) {
		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->no_mc_groups = array_unique( array_merge( $this->no_mc_groups, $groups ) );
	}

	/**
	 * Switches the internal blog ID.
	 *
	 * This changes the blog ID used to create keys in blog specific groups.
	 *
	 * @param int $blog_id Blog ID.
	 * @return void
	 */
	public function switch_to_blog( $blog_id ) {
		global $table_prefix;

		/** @psalm-suppress RedundantCastGivenDocblockType **/
		$blog_id = (int) $blog_id;

		$this->blog_prefix = (string) ( is_multisite() ? $blog_id : $table_prefix );
	}

	/**
	 * Close the connections.
	 *
	 * @return bool
	 */
	public function close() {
		return $this->adapter->close_connections();
	}


	/*
	|--------------------------------------------------------------------------
	| Internal methods that deal with flush numbers, the pseudo-cache-flushing mechanic.
	| Public methods here may be deprecated & made private in the future.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get the flush number prefix, used for creating the key string.
	 *
	 * @param string|int $group
	 * @return string
	 */
	public function flush_prefix( $group ) {
		if ( $group === $this->flush_group || $group === $this->global_flush_group ) {
			// Never flush the flush numbers.
			$number = '_';
		} elseif ( false !== array_search( $group, $this->global_groups ) ) {
			$number = $this->get_global_flush_number();
		} else {
			$number = $this->get_blog_flush_number();
		}

		return $number . ':';
	}

	/**
	 * Get the global group flush number.
	 *
	 * @return int
	 */
	public function get_global_flush_number() {
		if ( ! isset( $this->global_flush_number ) ) {
			$this->global_flush_number = $this->get_flush_number( $this->global_flush_group );
		}

		return $this->global_flush_number;
	}

	/**
	 * Get the blog's flush number.
	 *
	 * @return int
	 */
	public function get_blog_flush_number() {
		if ( ! isset( $this->flush_number[ $this->blog_prefix ] ) ) {
			$this->flush_number[ $this->blog_prefix ] = $this->get_flush_number( $this->flush_group );
		}

		return $this->flush_number[ $this->blog_prefix ];
	}

	/**
	 * Get the flush number for a specific group.
	 *
	 * @param string $group
	 * @return int
	 */
	public function get_flush_number( $group ) {
		$flush_number = $this->get_max_flush_number( $group );

		if ( empty( $flush_number ) ) {
			// If there was no flush number anywhere, make a new one. This flushes the cache.
			$flush_number = $this->new_flush_number();
			$this->set_flush_number( $flush_number, $group );
		}

		return $flush_number;
	}

	/**
	 * Set the flush number for a specific group.
	 *
	 * @param int $value
	 * @param string $group
	 * @return void
	 */
	public function set_flush_number( $value, $group ) {
		$key    = $this->key( $this->flush_key, $group );
		$expire = 0;
		$size   = 19; // size of the microsecond timestamp serialized

		$this->timer_start();
		$this->adapter->set_with_redundancy( $key, $value, $expire );
		$elapsed = $this->timer_stop();

		$average_time_elapsed = $elapsed / count( $this->default_mcs );
		foreach ( $this->default_mcs as $_default_mc ) {
			$this->group_ops_stats( 'set_flush_number', $key, $group, $size, $average_time_elapsed, 'replication' );
		}
	}

	/**
	 * Get the highest flush number from all default servers, replicating if needed.
	 *
	 * @param string $group
	 * @return int|false
	 */
	public function get_max_flush_number( $group ) {
		$key  = $this->key( $this->flush_key, $group );
		$size = 19; // size of the microsecond timestamp serialized

		$this->timer_start();
		$values  = $this->adapter->get_with_redundancy( $key );
		$elapsed = $this->timer_stop();

		$replication_servers_count = max( count( $this->default_mcs ), 1 );
		$average_time_elapsed      = $elapsed / $replication_servers_count;

		/** @psalm-suppress MixedAssignment */
		foreach ( $values as $result ) {
			if ( false === $result ) {
				$this->group_ops_stats( 'get_flush_number', $key, $group, null, $average_time_elapsed, 'not_in_memcache' );
			} else {
				$this->group_ops_stats( 'get_flush_number', $key, $group, $size, $average_time_elapsed, 'memcache' );
			}
		}

		$values = array_map( 'intval', $values );
		/** @psalm-suppress ArgumentTypeCoercion */
		$max = max( $values );

		if ( $max <= 0 ) {
			return false;
		}

		$servers_to_update = [];
		foreach ( $values as $server_string => $value ) {
			if ( $value < $max ) {
				$servers_to_update[] = $server_string;
			}
		}

		// Replicate to servers not having the max.
		if ( ! empty( $servers_to_update ) ) {
			$expire = 0;

			$this->timer_start();
			$this->adapter->set_with_redundancy( $key, $max, $expire, $servers_to_update );
			$elapsed = $this->timer_stop();

			$average_time_elapsed = $elapsed / count( $servers_to_update );
			foreach ( $servers_to_update as $updated_server ) {
				$this->group_ops_stats( 'set_flush_number', $key, $group, $size, $average_time_elapsed, 'replication_repair' );
			}
		}

		return $max;
	}

	/**
	 * Rotate the flush number for the site/blog.
	 *
	 * @param ?int $flush_number
	 * @return void
	 */
	public function rotate_site_keys( $flush_number = null ) {
		if ( is_null( $flush_number ) ) {
			$flush_number = $this->new_flush_number();
		}

		$this->set_flush_number( $flush_number, $this->flush_group );
		$this->flush_number[ $this->blog_prefix ] = $flush_number;
	}

	/**
	 * Rotate the global flush number.
	 *
	 * @param ?int $flush_number
	 * @return void
	 */
	public function rotate_global_keys( $flush_number = null ) {
		if ( is_null( $flush_number ) ) {
			$flush_number = $this->new_flush_number();
		}

		$this->set_flush_number( $flush_number, $this->global_flush_group );
		$this->global_flush_number = $flush_number;
	}

	/**
	 * Generate new flush number.
	 *
	 * @return int
	 */
	public function new_flush_number(): int {
		return intval( microtime( true ) * 1e6 );
	}

	/*
	|--------------------------------------------------------------------------
	| Utility methods. Internal use only.
	| Public methods here may be deprecated & made private in the future.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Generate the key we'll use to interact with memcached.
	 * Note: APCU requires this to be public right now.
	 *
	 * @param int|string $key
	 * @param int|string $group
	 * @return string
	 */
	public function key( $key, $group ): string {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		$result = sprintf(
			'%s%s%s:%s:%s',
			$this->key_salt,
			$this->flush_prefix( $group ),
			array_search( $group, $this->global_groups ) !== false ? $this->global_prefix : $this->blog_prefix,
			$group,
			$key
		);

		return preg_replace( '/\\s+/', '', $result );
	}

	/**
	 * Map the full cache key to the original key.
	 *
	 * @param array<int|string> $keys
	 * @param string $group
	 * @return array<string, int|string>
	 */
	protected function map_keys( $keys, $group ): array {
		$results = [];

		foreach ( $keys as $key ) {
			$results[ $this->key( $key, $group ) ] = $key;
		}

		return $results;
	}

	/**
	 * Get the memcached instance for the specified group.
	 *
	 * @param int|string $group
	 * @return Memcache|Memcached
	 */
	public function get_mc( $group ) {
		if ( isset( $this->mc[ $group ] ) ) {
			return $this->mc[ $group ];
		}

		return $this->mc['default'];
	}

	/**
	 * Sanitize the expiration time.
	 *
	 * @psalm-param int|numeric-string|float $expire
	 */
	private function get_expiration( $expire ): int {
		$expire = intval( $expire );
		if ( $expire <= 0 || $expire > $this->max_expiration ) {
			$expire = $this->default_expiration;
		}

		return $expire;
	}

	/**
	 * Check if the group is set up for non-persistent cache.
	 *
	 * @param string|int $group
	 * @return bool
	 */
	private function is_non_persistent_group( $group ) {
		return in_array( $group, $this->no_mc_groups, true );
	}

	/**
	 * Estimate the (uncompressed) data size.
	 *
	 * @param mixed $data
	 * @return int
	 * @psalm-return 0|positive-int
	 */
	public function get_data_size( $data ): int {
		if ( is_string( $data ) ) {
			return strlen( $data );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		return strlen( serialize( $data ) );
	}

	/**
	 * Sets the key salt property.
	 *
	 * @param mixed $key_salt
	 * @param bool $add_mc_prefix
	 * @return void
	 */
	public function salt_keys( $key_salt, $add_mc_prefix = false ) {
		$key_salt = is_string( $key_salt ) && strlen( $key_salt ) ? $key_salt : '';
		$key_salt = $add_mc_prefix ? $key_salt . '_mc' : '';

		$this->key_salt = empty( $key_salt ) ? '' : $key_salt . ':';
	}

	public function timer_start(): bool {
		$this->time_start = microtime( true );
		return true;
	}

	public function timer_stop(): float {
		return microtime( true ) - $this->time_start;
	}

	/**
	 * TODO: Deprecate.
	 *
	 * @param string $host
	 * @param string $port
	 */
	public function failure_callback( $host, $port ): void {
		$this->connection_errors[] = array(
			'host' => $host,
			'port' => $port,
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Stat-related tracking & output.
	| A lot of the below should be deprecated/removed in the future.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Echoes the stats of the caching operations that have taken place.
	 * Ideally this should be the only method left public in this section.
	 *
	 * @return void Outputs the info directly, nothing is returned.
	 */
	public function stats() {
		$this->stats_helper->stats();
	}

	/**
	 * Sets the key salt property.
	 *
	 * @param string $op The operation taking place, such as "set" or "get".
	 * @param string|string[] $keys The memcached key/keys involved in the operation.
	 * @param string $group The group the keys are in.
	 * @param ?int $size The size of the data invovled in the operation.
	 * @param ?float $time The time the operation took.
	 * @param string $comment Extra notes about the operation.
	 *
	 * @return void
	 */
	public function group_ops_stats( $op, $keys, $group, $size = null, $time = null, $comment = '' ) {
		$this->stats_helper->group_ops_stats( $op, $keys, $group, $size, $time, $comment );
	}

	/**
	 * Returns the collected raw stats.
	 */
	public function get_stats(): array {
		return $this->stats_helper->get_stats();
	}

	/**
	 * @param string $field The stat field/group being incremented.
	 * @param int $num Amount to increment by.
	 */
	public function increment_stat( $field, $num = 1 ): void {
		$this->stats_helper->increment_stat( $field, $num );
	}

	/**
	 * @param string|string[] $keys
	 * @return string|string[]
	 */
	public function strip_memcached_keys( $keys ) {
		return $this->stats_helper->strip_memcached_keys( $keys );
	}

	public function js_toggle(): void {
		$this->stats_helper->js_toggle();
	}

	/**
	 * @param string $line
	 * @param string $trailing_html
	 * @return string
	 */
	public function colorize_debug_line( $line, $trailing_html = '' ) {
		return $this->stats_helper->colorize_debug_line( $line, $trailing_html );
	}

	/**
	 * @param string|int $index
	 * @param array $arr
	 * @psalm-param array{0: string, 1: string|string[], 2: int|null, 3: float|null, 4: string, 5: string, 6: string|null } $arr
	 *
	 * @return string
	 */
	public function get_group_ops_line( $index, $arr ) {
		return $this->stats_helper->get_group_ops_line( $index, $arr );
	}
}
