<?php

// Plugin-specific tweaks go in here

namespace Automattic\VIP\Performance;

// AMP: disable reverse attachment lookups since they usually don't work (querystrings) and are slow
// This is fixed in AMP > v0.4
add_action( 'amp_extract_image_dimensions_callbacks_registered', function() {
	remove_filter( 'amp_extract_image_dimensions', array( 'AMP_Image_Dimension_Extractor', 'extract_from_attachment_metadata' ) );
} );

/**
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
