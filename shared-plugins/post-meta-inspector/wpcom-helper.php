<?php
/**
 * Ensure Post Meta Inspector loads when activated from the theme
 */
add_action( 'after_setup_theme', 'Post_Meta_Inspector' );

/**
 * Ignore Akismet keys as they're not relevant
 */
add_filter( 'pmi_ignore_post_meta_key', 'wpcom_filter_pmi_ignore_post_meta_key', 10, 2 );
function wpcom_filter_pmi_ignore_post_meta_key( $ret, $key ) {
	$ignored = array(
			'_akismet_post_spam',
			'_akismet_post_guid',
			'_akismet_post_content',
			'_feedback_akismet_values',
		);
	return (bool) in_array( $key, array( '_edit_last' ) );
}