<?php

namespace WorDBless;

trait ClearCacheGroup {

	/**
	 * Clears the cache for the current group
	 *
	 * @return void
	 */
	public function clear_cache_group() {
		global $wp_object_cache;

		if ( ! $this->cache_group ) {
			return;
		}

		if ( ! isset( $wp_object_cache->cache[ $this->cache_group ] ) || ! is_array( $wp_object_cache->cache[ $this->cache_group ] ) ) {
			return;
		}

		foreach ( array_keys( $wp_object_cache->cache[ $this->cache_group ] ) as $key ) {
			wp_cache_delete( $key, $this->cache_group );
		}
	}

}
