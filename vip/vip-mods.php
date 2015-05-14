<?php
// Overrides and modifications to WordPress defaults for performance gains (and profit).

/**
 * Reduce fetch_feed timeout to 3s.  
 *
 * The default timeout for SimplePie is 10s; that is unnecessarily long.
 * Let's keep things sane by lowering the timeout limit.
 * No feeds should be really much slower than 1s anyway.
 * If they are, you should find a different feed...
 */
add_action( 'wp_feed_options', function( $feed ) {
	$feed->set_timeout( 3 );
} );
