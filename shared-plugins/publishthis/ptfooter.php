<?php

add_action( 'wp_footer', 'pt_footer', 1001 );

/**
 *
 *
 * @desc Display blog footer. Show Publishthis logo if needed
 */
function pt_footer() {
	global $publishthis;

	if ( ! is_admin() && ! is_feed() && ! is_robots() && ! is_trackback() ) {

		try {
			
			echo pt_curated_by(), "\n";
			echo "<script type='text/javascript' src='http://curateby.publishthis.com/clients.js'></script>";

		} catch ( Exception $ex ) {

		}

	}
}


/**
 * 
 */
function pt_curated_by() {
	global $__pt_curated_by, $publishthis;;

	$strText = "";

	if( empty($__pt_curated_by) ) {
		$__pt_curated_by = true;

		if ( $publishthis->get_option( 'curatedby' ) ) {
			$strText = $strText . "<script type=\"text/javascript\">var pt_init={};pt_init.display_button=true;</script>";
			$strText = $strText . "<div id=\"pt_curated_by\" class=\"pt_curated_by\"></div>";
		} else {
			$strText = $strText . "<script type=\"text/javascript\">var pt_init={};pt_init.display_button=false;</script>";
		}
	}

	return $strText;
}
