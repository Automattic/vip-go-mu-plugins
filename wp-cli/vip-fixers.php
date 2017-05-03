<?php

class VIP_Go_OneTimeFixers_Command extends WPCOM_VIP_CLI_Command {

	private function wpcom_vip_download_image( $image_url, $post_id = 0, $description = '' ) {
		if ( $post_id < 0 ) {
			return new WP_Error( 'invalid-post-id', 'Please specify a valid post ID.' );
		}

		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'not-a-url', 'Please specify a valid URL.' );
		}

		$image_url_path = parse_url( $image_url, PHP_URL_PATH );
		$image_path_info = pathinfo( $image_url_path );

		if ( ! in_array( strtolower( $image_path_info['extension'] ), array( 'avi', 'mp3', 'jpg', 'jpe', 'jpeg', 'gif', 'png' ) ) ) {
			return new WP_Error( 'not-an-image', 'Specified URL does not have a valid media extension.' );
		}

		// Download file to temp location; short timeout, because we don't have all day.
		$downloaded_url = download_url( $image_url, 30 );

		// We couldn't download and store to a temporary location, so bail.
		if ( is_wp_error( $downloaded_url ) ) {
			return $downloaded_url;
		}

		$file_array['name'] = $image_path_info['basename'];
		$file_array['tmp_name'] = $downloaded_url;

		if ( empty( $description ) ) {
			$description = $image_path_info['filename'];
		}

		// Now, let's sideload it.
		$attachment_id = media_handle_sideload( $file_array, $post_id, $description );

		// If error storing permanently, unlink and return the error
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $file_array['tmp_name'] ); // unlink can throw errors if the file isn't there
			return $attachment_id;
		}

		return $attachment_id;
	}
}

WP_CLI::add_command( 'vip fixers', 'VIP_Go_OneTimeFixers_Command' );
