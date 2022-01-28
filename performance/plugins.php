<?php

// Plugin-specific tweaks go in here

namespace Automattic\VIP\Performance;

// AMP: disable reverse attachment lookups since they usually don't work (querystrings) and are slow
// This is fixed in AMP > v0.4
add_action( 'amp_extract_image_dimensions_callbacks_registered', function() {
	remove_filter( 'amp_extract_image_dimensions', array( 'AMP_Image_Dimension_Extractor', 'extract_from_attachment_metadata' ) );
} );

// CAP: By default, co-authors will query in a sort of compatibility mode.
// Making the SQL queries work for posts with the old author style (as in the built-in post author
// or by using the co-authors taxonomy.
add_action( 'plugins_loaded', __NAMESPACE__ . '\vip_coauthors_plus_should_query_post_author' );
function vip_coauthors_plus_should_query_post_author() {
	if ( class_exists( 'CoAuthors_Plus' ) ) {
		add_filter( 'coauthors_plus_should_query_post_author', '__return_false' );
	}
}
