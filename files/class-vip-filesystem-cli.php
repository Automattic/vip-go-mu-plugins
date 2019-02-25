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
	 * @subcommand update-meta
	 */
	public function update_attachment_meta( $args, $assoc_args ) {

		WP_CLI::line( 'Updating attachment metadata...' );
	}
}

WP_CLI::add_command( 'vip files', 'VIP_Files_CLI' );
