<?php

namespace Automattic\VIP\Jetpack\Connection_Pilot;

use WP_CLI;

WP_CLI::add_command( 'jetpack-start', __NAMESPACE__ . '\CLI' );

class CLI {
	/**
	 * Connect sites to Jetpack, Akismet, and VaultPress.

	 * [--network]
	 * : Connect all subsites of this multisite network
	 *
	 * ## EXAMPLES
	 *  wp jetpack-start connect
	 *  wp jetpack-start connect --network
	 *
	 */
	public function connect( $args, $assoc_args ) {
		$network = WP_CLI\Utils\get_flag_value( $assoc_args, 'network', false );

		if ( $network && is_multisite() ) {
			$successful = $this->map_sites( function() {
				return $this->connect_site();
			} );
		} else {
			$successful = $this->connect_site();
		}

		if ( ! $successful ) {
			WP_CLI::warning( 'Attempt completed. Please resolve the issues noted above and try again.' );
		} else {
			WP_CLI::success( 'All done! 🎉' );
		}
	}

	/**
	 * Connects JP services on a site.
	 *
	 * @return bool true if all operations were all successful, false otherwise.
	 */
	private function connect_site() {
		$jp_connection = $this->connect_jetpack();
		if ( ! $jp_connection ) {
			// Don't go further if JP is still disconnected.
			return false;
		}

		if ( ! defined( 'VIP_AKISMET_SKIP_LOAD' ) || true !== VIP_AKISMET_SKIP_LOAD ) {
			$ak_connection = Controls::connect_akismet();
			if ( ! $ak_connection ) {
				WP_CLI::warning( '❌ Could not connect Akismet.' );
			}
		}

		$vp_connection = Controls::connect_vaultpress();
		if ( is_wp_error( $vp_connection ) ) {
			WP_CLI::warning( sprintf( '❌ Could not connect VaultPress. Error (%s): %s', $vp_connection->get_error_code(), $vp_connection->get_error_message() ) );
		}

		return true === $ak_connection && true === $vp_connection;
	}

	/**
	 * Ensure Jetpack is connected.
	 *
	 * @return bool true if there is a successful connection.
	 */
	private function connect_jetpack() {
		$jp_is_connected = Controls::jetpack_is_connected();

		if ( ! is_wp_error( $jp_is_connected ) ) {
			WP_CLI::line( '☑️  Jetpack is already connected.' );
			return true;
		}

		$jp_connection = Controls::connect_site( true, true );
		if ( is_wp_error( $jp_connection ) ) {
			WP_CLI::warning( sprintf( '❌ Could not connect Jetpack. Error (%s): %s', $jp_connection->get_error_code(), $jp_connection->get_error_message() ) );
			return false;
		}

		WP_CLI::line( '✅  Jetpack was connected.' );
		return true;
	}

	/**
	 * Map over subsites in a network and perform the $callback() function on them.
	 *
	 * @param function $callback
	 * @return bool true if operations were all successful, false otherwise.
	 */
	private function map_sites( $callback ) {
		$default_site_args = [
			'public'   => null,
			'archived' => 0,
			'spam'     => 0,
			'deleted'  => 0,
			'fields'   => 'ids',
		];

		$site_count = get_sites( array_merge( $default_site_args, [ 
			'number' => 0, // allows for fetching all sites.
			'count'  => true,
		] ) );
		if ( $site_count > 1000 ) {
			WP_CLI::warning( 'There are more than 1000 active subsites. Connecting only up to 1000 is supported at this time.' );
		}

		// Keep track of starting blog, we'll just switch back once at the end.
		$starting_blog_id = get_current_blog_id();

		$all_success = true;

		$sites = get_sites( array_merge( $default_site_args, [ 'number' => 1000 ] ) );
		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );

			WP_CLI::line( sprintf( 'Starting %s (site %d)', home_url( '/' ), $site_id ) );

			$result = $callback();
			if ( false === $result ) {
				$all_success = false;
			}

			WP_CLI::line( sprintf( 'Done with %s', home_url( '/' ) ) );
			WP_CLI::line( '----------------------' );
		}

		switch_to_blog( $starting_blog_id );

		return $all_success;
	}
}
