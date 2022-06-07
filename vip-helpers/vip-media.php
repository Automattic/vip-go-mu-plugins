<?php

/**
 * Downloads an external image and optionally attaches it to a post.
 *
 * Contains most of core's media_sideload_image() but returns an attachment ID instead of HTML.
 *
 * Note: this function does not validate the domain that the image is coming from. Please make sure
 *   to validate this before downloading the image. Should only pull down images from trusted sources.
 *
 * @param string $image_url URL of the image.
 * @param int $post_ID ID of the post it should be attached to.
 * @param string $description Description of the image that should be added on upload.
 * @param array $post_data Array of data to be passed to media_handle_sideload.
 * @return $thumbnail_id id of the thumbnail attachment post id
 */
function wpcom_vip_download_image( $image_url, $post_id = 0, $description = '', $post_data = array() ) {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && strtoupper( $_SERVER['REQUEST_METHOD'] ) === 'GET' ) {
		return new WP_Error( 'invalid-request-method', 'Media sideloading is not supported via GET. Use POST.' );
	}

	if ( ! is_admin() ) {
		return new WP_Error( 'not-in-admin', 'Media sideloading can only be done in when `true === is_admin()`.' );
	}

	if ( $post_id < 0 ) {
		return new WP_Error( 'invalid-post-id', 'Please specify a valid post ID.' );
	}

	if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
		return new WP_Error( 'not-a-url', 'Please specify a valid URL.' );
	}

	$image_url_path  = wp_parse_url( $image_url, PHP_URL_PATH );
	$image_path_info = pathinfo( $image_url_path );

	if ( ! in_array( strtolower( $image_path_info['extension'] ), array( 'jpg', 'jpe', 'jpeg', 'gif', 'png' ) ) ) {
		return new WP_Error( 'not-an-image', 'Specified URL does not have a valid image extension.' );
	}

	// Download file to temp location; short timeout, because we don't have all day.
	$downloaded_url = download_url( $image_url, 30 );

	// We couldn't download and store to a temporary location, so bail.
	if ( is_wp_error( $downloaded_url ) ) {
		return $downloaded_url;
	}

	$file_array['name']     = $image_path_info['basename'];
	$file_array['tmp_name'] = $downloaded_url;

	if ( empty( $description ) ) {
		$description = $image_path_info['filename'];
	}

	// Now, let's sideload it.
	$attachment_id = media_handle_sideload( $file_array, $post_id, $description, $post_data );

	// If error storing permanently, unlink and return the error
	if ( is_wp_error( $attachment_id ) ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
		@unlink( $file_array['tmp_name'] ); // unlink can throw errors if the file isn't there
		return $attachment_id;
	}

	return $attachment_id;
}

/**
 * Set the quality of jpeg images served from files.wordpress.com
 *
 * On files.wordpress.com, we accept quality and strip as query parameters.
 * wpcom_vip_set_image_quality sets these parameters on all jpeg images served
 * from files.wordpress.com
 *
 * @param int $quality The quality of the image out of 100.
 * @param string $strip What data to strip: exif|color|all
 */
function wpcom_vip_set_image_quality( $quality, $strip = false ) {
	add_filter( 'wp_get_attachment_url', function( $attachment_url ) use ( $quality, $strip ) {
		return wpcom_vip_set_image_quality_for_url( $attachment_url, $quality, $strip );
	});

	add_filter( 'the_content', function( $content ) use ( $quality, $strip ) {
		if ( false !== strpos( $content, 'files.wordpress.com' ) ) {
			$content = preg_replace_callback( '#https?://\w+\.files\.wordpress\.com[^\s"\'>]+#', function( $matches ) use ( $quality, $strip ) {
				return wpcom_vip_set_image_quality_for_url( $matches[0], $quality, $strip );
			}, $content );
		}
		return $content;
	});

	// Photon
	add_filter('jetpack_photon_pre_args', function( $args ) use ( $quality, $strip ) {
		$args['quality'] = $quality;
		$args['strip']   = $strip;
		return $args;
	});
}

/**
 * Set the quality of a jpeg image
 *
 * @param int $quality The quality of the image out of 100.
 * @param string $strip What data to strip: exif|color|all
 * @return string A url with proper quality and strip query parameters
 * @see wpcom_vip_set_image_quality
 */
function wpcom_vip_set_image_quality_for_url( $attachment_url, $quality = 100, $strip = false ) {
	$query = array();
	$url   = wp_parse_url( $attachment_url );
	$ext   = pathinfo( $url['path'], PATHINFO_EXTENSION );

	if ( ! in_array( $ext, array( 'jpg', 'jpeg' ) ) ) {
		return $attachment_url;
	}

	if ( isset( $url['query'] ) ) {
		parse_str( $url['query'], $query );
	}

	$query['quality'] = absint( $quality );

	if ( true === $strip ) {
		$query['strip'] = 'all';
	} elseif ( $strip ) {
		$query['strip'] = $strip;
	}

	return add_query_arg( $query, $url['scheme'] . '://' . $url['host'] . $url['path'] );
}
