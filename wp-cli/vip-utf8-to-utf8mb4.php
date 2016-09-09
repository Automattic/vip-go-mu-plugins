<?php

class VIP_Go_Convert_utf8_utf8mb4 extends WPCOM_VIP_CLI_Command {

	/**
	 * Convert site using `utf8` to use `utf8mb4`
	 *
	 * @subcommand convert
	 */
	public function maybe_convert( $args, $assoc_args ) {
		WP_CLI::line( 'Hi' );
	}
}

WP_CLI::add_command( 'vip-go-utf8mb4', 'VIP_Go_Convert_utf8_utf8mb4' );
