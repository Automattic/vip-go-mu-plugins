<?php

class VIP_Go_Feed_Cache extends SimplePie_Cache {
	/**
	 * Creates a new SimplePie_Cache object.
	 *
	 * @access public
	 *
	 * @param string $location  URL location (scheme is used to determine handler).
	 * @param string $filename  Unique identifier for cache object.
	 * @param string $extension 'spi' or 'spc'.
	 * @return VIP_Go_Feed_Cache_Transient Feed cache handler object that uses transients and normalizes SimplePie Build number.
	 */
	public function create( $location, $filename, $extension ) {
		return new VIP_Go_Feed_Cache_Transient( $location, $filename, $extension );
	}
}
