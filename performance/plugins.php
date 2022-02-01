<?php

// Plugin-specific tweaks go in here

namespace Automattic\VIP\Performance;

/**
 * Disable an expensive term-counting query for Co-Authors Plus.
 *
 * By default, the Co-Authors Plus plugin will query in a compatibility mode where it uses an expensive JOIN for post_author in it.
 * This filter disables it and uses a simpler, taxonomy-only query.
 * Run `wp co-authors-plus create-terms-for-posts` to ensure all authors have an author taxonomy attached to them.
 * 
 * @see https://lobby.vip.wordpress.com/2017/11/07/co-authors-plus-global-filter/
 * 
 * @return void
 */
function vip_coauthors_plus_should_query_post_author() {
	if ( class_exists( 'CoAuthors_Plus' ) ) {
		add_filter( 'coauthors_plus_should_query_post_author', '__return_false' );
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\vip_coauthors_plus_should_query_post_author' );
