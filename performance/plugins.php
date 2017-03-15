<?php

// Plugin-specific tweaks go in here

namespace Automattic\VIP\Performance;

// AMP: disable reverse attachment lookups since they usually don't work (querystrings) and are slow
// This is fixed in AMP > v0.4
add_action( 'amp_extract_image_dimensions_callbacks_registered', function() {
	remove_filter( 'amp_extract_image_dimensions', array( 'AMP_Image_Dimension_Extractor', 'extract_from_attachment_metadata' ) );
} );
