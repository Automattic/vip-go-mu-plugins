<?php
// Require core feed class.
require_once( ABSPATH . WPINC . '/class-feed.php' );

class VIPGO_Feed_Cache extends SimplePie_Cache {
	/**
	 * Creates a new SimplePie_Cache object.
	 *
	 * @access public
	 *
	 * @param string $location  URL location (scheme is used to determine handler).
	 * @param string $filename  Unique identifier for cache object.
	 * @param string $extension 'spi' or 'spc'.
	 * @return VIPGO_Feed_Cache_Transient Feed cache handler object that uses transients and normalizes SimplePie Build number.
	 */
	public function create( $location, $filename, $extension ) {
		return new VIPGO_Feed_Cache_Transient( $location, $filename, $extension );
	}
}

class VIPGO_Feed_Cache_Transient extends WP_Feed_Cache_Transient {
	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param string $location  URL location (scheme is used to determine handler).
	 * @param string $filename  Unique identifier for cache object.
	 * @param string $extension 'spi' or 'spc'.
	 */
	public function __construct($location, $filename, $extension) {
		parent::__construct( $location, $filename, $extension );
	}

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
/**
 * Sets the SimplePie Cache Class to our custom VIP Go Class.
 *
 * @param object &$feed SimplePie feed object, passed by reference.
 * @param mixed  $url   URL of feed to retrieve. If an array of URLs, the feeds are merged.
 *
 * @return null
 */
function vipgo_feed_options( &$feed, $url ) {
	$feed->set_cache_class( 'VIPGO_Feed_Cache' );
}

add_action( 'wp_feed_options', 'vipgo_feed_options', 10, 2 );

