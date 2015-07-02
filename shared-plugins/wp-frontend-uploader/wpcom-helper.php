<?php
/**
 * WordPress.com does languages differently
 */
add_filter( 'fu_wplang', 'wpcom_fu_wplang' );
function wpcom_fu_wplang( $lang ) {

	if ( function_exists( 'get_blog_lang_code' ) )
		$lang = str_replace( '-', '_', get_blog_lang_code() );
	return $lang;
}