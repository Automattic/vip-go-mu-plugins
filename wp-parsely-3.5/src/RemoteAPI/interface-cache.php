<?php
/**
 * Remote API: Cache interface
 *
 * @package Parsely
 * @since   3.2.0
 */

declare(strict_types=1);

namespace Parsely\RemoteAPI;

/**
 * Remote API Cache Interface
 */
interface Cache {
	/**
	 * Retrieves the cache contents from the cache by key and group.
	 *
	 * @since 3.2.0
	 *
	 * @param int|string $key   The key under which the cache contents are stored.
	 * @param string     $group Optional. Where the cache contents are grouped. Default empty.
	 * @param bool       $force Optional. Whether to force an update of the local cache
	 *                          from the persistent cache. Default false.
	 * @param bool       $found Optional. Whether the key was found in the cache (passed by reference).
	 *                          Disambiguates a return of false, a storable value. Default null.
	 * @return mixed|false The cache contents on success, false on failure to retrieve contents.
	 */
	public function get( $key, string $group = '', bool $force = false, bool $found = null );

	/**
	 * Saves the data to the cache.
	 *
	 * @since 3.2.0
	 *
	 * @param int|string $key    The cache key to use for retrieval later.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Enables the same key
	 *                           to be used across groups. Default empty.
	 * @param int        $expire Optional. When to expire the cache contents, in seconds.
	 *                           Default 0 (no expiration).
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $data, string $group = '', int $expire = 0 ): bool;
}
