<?php
/**
 * Akismet > 3.0 integration
 *
 * Pretty much stolen from Akismet::auto_check_comment()
 *
 * This implementation is experimental but was doing pretty good during tests.
 *
 * It uses a filter just in case people would like to customize/modify the behavior
 */
add_filter( 'fu_should_process_content_upload', 'fu_akismet_check_submission', 10, 2 );
function fu_akismet_check_submission( $should_process, $layout ) {
	// Akismet is not enabled or not configured, or too old, just return the filter value
	if ( ! class_exists( 'Akismet' ) || !method_exists( 'Akismet', 'get_api_key' ) || ! Akismet::get_api_key() )
		return $should_process;

	$content = array();

	$content['comment_author'] = isset( $_POST['post_author'] ) ? sanitize_text_field( $_POST['post_author'] ) : null;
	$content['comment_content'] = isset( $_POST[ 'post_content' ] ) ? $_POST['post_content'] : null;

	// Permalink of the post with upload form, fallback to wp_get_referer()
	// Fallback is used to
	$content['permalink'] = isset( $_POST['form_post_id'] ) ? get_permalink( $_POST['form_post_id'] ) : wp_get_referer();

	// Set required Akismet values
	$content['user_ip'] = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
	$content['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
	$content['referrer'] = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null;
	$content['blog'] = get_option( 'home' );
	$content['blog_lang']    = get_locale();
	$content['blog_charset'] = get_option( 'blog_charset' );

	// Ignore these keys in POST and SERVER superglobals, add the rest to request
	// This approach is stolen from Akismet::auto_check_comment()
	$ignore = array( 'HTTP_COOKIE', 'HTTP_COOKIE2', 'PHP_AUTH_PW', 'ff', 'fu_nonce' );
	foreach ( $_POST as $key => $value ) {
		if ( !in_array( $key, $ignore ) && is_string( $value ) )
			$content["POST_{$key}"] = $value;
	}

	foreach ( $_SERVER as $key => $value ) {
		if ( !in_array( $key, $ignore ) && is_string( $value ) )
			$content["$key"] = $value;
		else
			$content["$key"] = '';
	}

	// Build a query and make a request to Akismet
	$request = build_query( $content );
	$response = Akismet::http_post( $request, 'comment-check' );

	// It's a spam
	if ( $response[1] == 'true' )
		$should_process = false;

	return $should_process;
}
