<?php
/**
 * Increase the maximum alloted redirects
 */
add_filter( 'srm_max_redirects', 'wpcom_srm_max_redirects' );
function wpcom_srm_max_redirects() {
	return 300;
}