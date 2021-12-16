<?php
/**
 * Helper for dealing with the media regenerate command
 */
namespace Automattic\VIP\Commands;

use WP_CLI\Utils;

$media_regenerate_before_invoke = function( $args, $assoc_args ) {
	$skip_delete = Utils\get_flag_value( $assoc_args, 'skip-delete' );

	if ( true !== $skip_delete ) {
		WP_CLI::error( 'VIP sites require the --skip-delete flag when running "media regenerate"' );
	}
};

WP_CLI::add_hook( 'before_invoke:media regenerate', $media_regenerate_before_invoke );
