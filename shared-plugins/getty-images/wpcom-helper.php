<?php

/**
 * Always load the Omniture Javascript from the CDN
 * Since it's minified and nearly impossible to review
 * we don't want it to have access to admin cookies.
 */
add_filter( 'getty_images_s_code_js_url', function() {
	return '//s0.wp.com/wp-content/themes/vip/plugins/getty-images/js/s_code.js';
} );
