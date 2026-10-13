<?php

if ( ! class_exists( 'WP_Feed_Cache_Transient' ) ) {
	require_once ABSPATH . WPINC . '/class-wp-feed-cache-transient.php';
}

class VIP_Go_Feed_Cache_Transient extends WP_Feed_Cache_Transient implements SimplePie_Cache_Base {
	/**
	 * Gets the transient.
	 *
	 * This also normalizes the SimplePie Build number.  If the returned build
	 * number differs from what is expected, the cache is considered invalid.
	 * The number can differ if one web container sets the cache and a different
	 * web container reads the cache.  This is because the build number is set
	 * by the filemtime() of the SimplePie source files, and file modified dates
	 * can differ between web containers.
	 *
	 * The transient is read by the parent class so that it always comes from
	 * the same storage core saves it to (`*_site_transient()` since WP 6.9).
	 *
	 * @access public
	 *
	 * @return mixed Transient value.
	 */
	public function load() {
		$transient = parent::load();

		// If we don't have the required data, bail.
		if ( ! isset( $transient['build'] ) ) {
			return $transient;
		}

		$build = self::get_simplepie_build();
		if ( null !== $build ) {
			$transient['build'] = $build;
		}

		return $transient;
	}

	/**
	 * Gets the build number SimplePie validates cached data against.
	 *
	 * SimplePie 1.8+ (WP 6.7+) strictly compares against the integer returned by
	 * SimplePie\Misc::get_build(). Older versions compare against the SIMPLEPIE_BUILD
	 * constant, which is a date string.
	 *
	 * @return int|string|null Build number, or null if it can't be determined.
	 */
	private static function get_simplepie_build() {
		if ( class_exists( 'SimplePie\Misc' ) ) {
			return \SimplePie\Misc::get_build();
		}

		return defined( 'SIMPLEPIE_BUILD' ) ? SIMPLEPIE_BUILD : null;
	}
}
