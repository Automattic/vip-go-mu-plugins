<?php
/*
 * Security check
 * Exit if file accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/*
 * Playbuzz oEmbed
 * Add playbuzz oEmbed support to WordPress.
 *
 * Check for:
 * 		http://playbuzz.com/*
 * 		https://playbuzz.com/*
 * 		http://www.playbuzz.com/*
 * 		https://www.playbuzz.com/*
 *
 * @since 0.5.0
 */
function playbuzz_oembed_provider_registration() {

	wp_oembed_add_provider( '#https?://(www\.)?playbuzz.com/.*#i', 'https://www.playbuzz.com/api/oembed/', true );

}
add_action( 'init', 'playbuzz_oembed_provider_registration' );
