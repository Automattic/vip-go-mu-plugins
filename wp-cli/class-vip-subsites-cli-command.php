<?php

class VIP_Subsites_CLI_Command extends \WPCOM_VIP_CLI_Command {

	/**
	 * Update wp_blogs with new subsite domain and path
	 *
	 * ## OPTIONS
	 *
	 * <blog-id>
	 * : Which blog id we're updating the domain for
	 *
	 * <domain>
	 * : New domain for subsite
	 *
	 * [--subsite-path=<path>]
	 * : Subsite path, e.g. for subsite www.example.com/subsite1 use subsite1 as path
	 *
	 * ## EXAMPLES
	 *     wp vip subsites update_wp_blogs 2 www.example.com subsite1
	 *
	 * @subcommand update-wp-blogs <blog-id> <domain>
	 * @sypnosis [--subsite-path=<path>]
	 */
	public function update_wp_blogs( $args, $assoc_args ) {
		global $wpdb;

		WP_CLI::line( 'Updating wp_blogs...' );

		$path = WP_CLI\Utils\get_flag_value( $assoc_args, 'subsite-path', '/' );

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		if ( '/' !== substr( $path, strlen( $path ) - 1, 1 ) ) {
			$path = $path . '/';
		}

		$blog_id = (int) sanitize_key( $args[0] );
		$domain  = $args[1];

		WP_CLI::line( '' );
		WP_CLI::line( 'ARGUMENTS' );
		WP_CLI::line( '* blog ID: ' . $blog_id );
		WP_CLI::line( '* domain: ' . $domain );
		WP_CLI::line( '* path: ' . $path );
		WP_CLI::line( '* new subsite URL: ' . $domain . $path );
		WP_CLI::line( '' );

		$date_now = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$blogs_update = $wpdb->update(
			'wp_blogs',
			array(
				'domain'       => $domain,
				'path'         => $path,
				'last_updated' => $date_now->format( 'Y-m-d H:i:s' ),
			),
			array( 'blog_id' => $blog_id )
		);

		if ( $blogs_update ) {
			WP_CLI::success( 'WP blogs update complete!' );
		} elseif ( $wpdb->last_error ) {
			WP_CLI::error( $wpdb->last_error );
		} else {
			WP_CLI::warning( 'No changes detected' );
		}
	}
}

if ( is_multisite() ) {
	WP_CLI::add_command( 'vip subsites', '\VIP_Subsites_CLI_Command' );
}
