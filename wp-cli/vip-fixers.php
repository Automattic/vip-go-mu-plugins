<?php

class VIP_Go_OneTimeFixers_Command extends WPCOM_VIP_CLI_Command {

	/**
	 * Fixes issues with slow saving in wp-admin due to no audio/video media files
	 *
	 * Core Ticket: https://core.trac.wordpress.org/ticket/31071
	 *
	 * eg.: `wp vip-go-one-time-fixers blank-media-fix --allow-root --url=beta.thesun.co.uk`
	 *
	 * @subcommand blank-media-fix
	 */
	public function blank_media_fix( $args, $assoc_args ) {
		if ( ! function_exists( 'wpcom_vip_download_image' ) ) {
			WP_CLI::error( 'This script requires the wpcom_vip_download_image() function, https://vip.wordpress.com/functions/wpcom_vip_download_image/' );
		}

		$audio_file_url = 'https://cldup.com/xmre07YagX.mp3'; // 1sec.mp3
		$video_file_url = 'https://cldup.com/KHsK5yZkvv.avi'; // 1sec.avi

		$args = array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_query'  => array(
				array(
					'key'     => '_vip_blank_media_fix',
					'value'   => 'video',
				),
			),
		);
		$video_query = new WP_Query( $args );

		if ( ! $video_query->post_count ) {
			WP_CLI::log( 'Video fix not found, applying...' );

			$video_file_id = $this->wpcom_vip_download_image( $video_file_url, 0, 'VIP: Fix for slow post saving');
			if ( ! is_wp_error( $video_file_id ) ) {
				$args = array(
					'ID' => $video_file_id,
					'post_date' => '2000-01-01',
					'post_date_gmt' => '2000-01-01',
					'post_modified' => '2000-01-01',
					'post_modified_gmt' => '2000-01-01',
				);
				$updated_video_file_id = wp_update_post( $args, true );

				if ( ! is_wp_error( $updated_video_file_id ) ) {
					WP_CLI::success( 'Video fix applied' );

					$video_meta = update_post_meta( $updated_video_file_id, '_vip_blank_media_fix', 'video' );

					if ( false === $video_meta ) {
						WP_CLI::warning( 'Could not update video _vip_blank_media_fix meta' );
					}
				} else {
					// Video date was not updated
					WP_CLI::error( $updated_video_file_id->get_error_message() );
				}
			} else {
				// Sideload failed
				WP_CLI::error( $video_file_id->get_error_message() );
			}
		} else {
			WP_CLI::warning( 'Blank video fix already exists for this site' );
		}

		$args = array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_query'  => array(
				array(
					'key'     => '_vip_blank_media_fix',
					'value'   => 'audio',
				),
			),
		);
		$audio_query = new WP_Query( $args );

		if ( ! $audio_query->post_count ) {
			WP_CLI::log( 'Audio fix not found, applying...' );

			$audio_file_id = $this->wpcom_vip_download_image( $audio_file_url, 0, 'VIP: Fix for slow post saving');
			if ( ! is_wp_error( $audio_file_id ) ) {
				$args = array(
					'ID' => $audio_file_id,
					'post_date' => '2000-01-01',
					'post_date_gmt' => '2000-01-01',
					'post_modified' => '2000-01-01',
					'post_modified_gmt' => '2000-01-01',
				);
				$updated_audio_file_id = wp_update_post( $args, true );

				if ( ! is_wp_error( $updated_audio_file_id ) ) {
					WP_CLI::success( 'Audio fix applied' );

					$audio_meta = update_post_meta( $updated_audio_file_id, '_vip_blank_media_fix', 'audio' );

					if ( false === $audio_meta ) {
						WP_CLI::warning( 'Could not update audio _vip_blank_media_fix meta' );
					}
				} else {
					// Audio date was not updated
					WP_CLI::error( $updated_audio_file_id->get_error_message() );
				}
			} else {
				// Sideload failed
				WP_CLI::error( $video_file_id->get_error_message() );
			}
		} else {
			WP_CLI::warning( 'Blank video fix already exists for this site' );
		}
	}

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

WP_CLI::add_command( 'vip-go-one-time-fixers', 'VIP_Go_OneTimeFixers_Command' );
