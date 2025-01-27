<?php

/**
 * Helpers for interacting with alloptions
 *
 * @package wp-cli
 * @subpackage commands/wordpressdotcom
 */

class VIP_Go_Alloptions extends WPCOM_VIP_CLI_Command {
	/**
	 * Get a list of big options listed in biggest size to smallest
	 *
	 * @subcommand find
	 * @synopsis [--big] [--format=<table>]
	 */
	public function find( $args, $assoc_args ) {
		$defaults = array(
			'big'    => false,
			'format' => 'table',
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$options    = array();
		$alloptions = wp_load_alloptions( true );

		foreach ( $alloptions as $name => $val ) {
			$size = mb_strlen( $val );

			// find big options only
			if ( $assoc_args['big'] && $size < 500 ) {
				continue;
			}

			$option = new stdClass();

			$option->name = $name;
			$option->size = $size;

			$options[] = $option;
		}

		// sort by size
		usort( $options, function ( $arr1, $arr2 ) {
			if ( $arr1->size === $arr2->size ) {
				return 0;
			}

			return ( $arr1->size < $arr2->size ) ? -1 : 1;
		});

		$options = array_reverse( $options );

		WP_CLI::line();

		if ( $assoc_args['big'] ) {
			WP_CLI::success( sprintf( 'Big options for %s - Blog ID %d:', home_url(), get_current_blog_id() ) );
		} else {
			WP_CLI::success( sprintf( 'All options for %s - Blog ID %d:', home_url(), get_current_blog_id() ) );
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $options, array( 'name', 'size' ) );

		WP_CLI::line( sprintf( 'Size of serialized alloptions for this blog: %s', size_format( strlen( serialize( $alloptions ) ) ) ) );    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		WP_CLI::line( sprintf( 'Size of serialized alloptions for this blog (compressed): %s', size_format( strlen( gzdeflate( serialize( $alloptions ) ) ), 2 ) ) );    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		WP_CLI::line( 'Note: Compressed size is an approximation of the size when stored in object cache.' );
		WP_CLI::line( "\tuse `wp option get <option_name>` to view a big option" );
		WP_CLI::line( "\tuse `wp option delete <option_name>` to delete a big option" );
		WP_CLI::line( "\tuse `wp option set-autoload <option_name> no` to disable autoload for option" );
	}
}

WP_CLI::add_command( 'vip alloptions', 'VIP_Go_Alloptions' );
