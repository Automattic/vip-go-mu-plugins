<?php

namespace Automattic\VIP\Files;

require_once __DIR__ . '/../vip-helpers/vip-wp-cli.php';

use \WP_CLI;

/**
 * Helper command to manage VIP Go Filesystem
 *
 * @package Automattic\VIP\Files
 */
class VIP_Files_CLI extends \WPCOM_VIP_CLI_Command {

	/**
	 * Update file attachment metadata
	 *
	 * ## EXAMPLES
	 *     wp vip files update-meta
	 *
	 * @subcommand update-filesize
	 */
	public function update_attachment_filesize( $args, $assoc_args ) {
		global $wpdb;

		WP_CLI::line( 'Updating attachment filesize metadata...' );

		$post_args = [
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => null,
		];
		$attachments = get_posts( $post_args );

		if ( $attachments ) {
			foreach( $attachments as $attachment ) {
				$filesize = wp_get_attachment_metadata( $attachment->ID );
			}
		}
	}
}

WP_CLI::add_command( 'vip files', 'VIP_Files_CLI' );
