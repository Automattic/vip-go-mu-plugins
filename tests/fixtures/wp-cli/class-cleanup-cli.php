<?php

// The cleanup command only needs registration and completion output from WP-CLI.
class WP_CLI {
	public static function add_command( $name, $command ): void {}
	public static function success( $message ): void {}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- CLI test doubles.
class WPCOM_VIP_CLI_Command {}
