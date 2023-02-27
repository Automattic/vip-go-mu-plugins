<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

class VIP_DB_Command extends \DB_Command {
	/**
	 * Wrapper for WP-CLI's db query command to allow for --read-write flag to not get passed into the subcommand.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function query( $args, $assoc_args ) {
		if ( isset( $assoc_args['read-write'] ) ) {
			unset( $assoc_args['read-write'] );
		}

		parent::query( $args, $assoc_args );
	}
}
