<?php

// Disable the bit.ly url generation in development environments, since that post meta can be moved to production in an import,
// which results in incorrect bit.ly urls if they weren't generated in production.
add_filter( 'bitly_enable_url_generation', function( $enabled ) {
	if ( true !== WPCOM_IS_VIP_ENV )
		return false;

	return $enabled;
});
