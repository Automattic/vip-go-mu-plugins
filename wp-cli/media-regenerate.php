<?php
/**
 * Helper for dealing with the media regenerate command
 */
namespace Automattic\VIP\Commands;

use WP_CLI;

$runner     = WP_CLI::get_runner();
$assoc_args = $runner->assoc_args;

$media_regenerate_before_invoke = function() use ( &$runner, &$assoc_args ) {
	// If skip-delete is not set or not true
	if ( ! isset( $assoc_args['skip-delete'] ) || 'true' !== $assoc_args['skip-delete'] ) {
		// add skip-delete to the assoc_args array
		$assoc_args['skip-delete'] = 'true';

		WP_CLI::line( 'Forcing --skip-delete flag as it\'s required for VIP File Service' );
		WP_CLI::line( 'Re-running...' );

		// Run the command with the forced skip-delete argument
		$runner->run_command( $runner->arguments, $assoc_args );

		// Exit the run loop to prevent continuing to the invoked command
		exit;
	}
};

WP_CLI::add_hook( 'before_invoke:media regenerate', $media_regenerate_before_invoke );
