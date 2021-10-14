<?php

class VIP_Go_Feed_Cache_Transient extends WP_Feed_Cache_Transient {
	/**
	 * Gets the transient.
	 *
	 * This also normalizes the SimplePie Build number.  If the returned build
	 * number differes from what is expected, the cache is considered invalid.
	 * The number can differ if one web container sets the cache and a different
	 * web container reads the cache.  This is because the build number is set
	 * by the filemtime() of the SimplePie source files, and file modified dates
	 * can differ between web containers.
	 *
	 * @access public
	 *
	 * @return mixed Transient value.
	 */
	public function load() {
		$transient = get_transient( $this->name );

		// If we don't have the required data, bail.
		if ( ! defined( 'SIMPLEPIE_BUILD' ) || ! isset( $transient['build'] ) ) {
			return $transient;
		}
		$transient['build'] = SIMPLEPIE_BUILD;
		return $transient;
	}

	/**
	 * Gets mod transient.
	 *
	 * This also normalizes the SimplePie Build number.  If the returned build
	 * number differes from what is expected, the cache is considered invalid.
	 * The number can differ if one web container sets the cache and a different
	 * web container reads the cache.  This is because the build number is set
	 * by the filemtime() of the SimplePie source files, and file modified dates
	 * can differ between web containers.
	 *
	 * @access public
	 *
	 * @return mixed Transient value.
	 */
	public function mtime() {
		$transient = get_transient( $this->mod_name );

		// If we don't have the required data, bail.
		if ( ! defined( 'SIMPLEPIE_BUILD' ) || ! isset( $transient['build'] ) ) {
			return $transient;
		}
		$transient['build'] = SIMPLEPIE_BUILD;
		return $transient;
	}
}
