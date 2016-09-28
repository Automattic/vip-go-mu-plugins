<?php

class WPCOM_Legacy_Redirector_CLI extends WP_CLI_Command {

	/**
 	 * Insert a single redirect
 	 *
 	 * @subcommand insert-redirect
 	 * @synopsis <from_url> <to_url>
 	 */
	function insert_redirect( $args, $assoc_args ) {
		$from_url = esc_url_raw( $args[0] );

		if ( is_numeric( $args[1] ) )
			$to_url = absint( $args[1] );
		else
			$to_url = esc_url_raw( $args[1] );

		$inserted = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url );

		if ( ! $inserted || is_wp_error( $inserted ) ) {
			WP_CLI::warning( sprintf( "Couldn't insert %s -> %s", $from_url, $to_url ) );
		}

		WP_CLI::success( sprintf( "Inserted %s -> %s", $from_url, $to_url ) );
	}

	/**
 	 * Bulk import redirects from URLs stored as meta values for posts.
 	 *
 	 * @subcommand import-from-meta
 	 * @synopsis --meta_key=<name-of-meta-key> [--start=<start-offset>] [--end=<end-offset>] [--skip_dupes=<skip-dupes>] [--dry_run]
 	 */
	function import_from_meta( $args, $assoc_args ) {
		define( 'WP_IMPORTING', true );

		global $wpdb;
		$offset = isset( $assoc_args['start'] ) ? intval( $assoc_args['start'] ) : 0;
		$end_offset = isset( $assoc_args['end'] ) ? intval( $assoc_args['end'] ) : 99999999;;
		$meta_key = isset( $assoc_args['meta_key'] ) ? sanitize_key( $assoc_args['meta_key'] ) : '';
		$skip_dupes = isset( $assoc_args['skip_dupes'] ) ? (bool)intval( $assoc_args['skip_dupes'] ) : false;
		$dry_run = isset( $assoc_args['dry_run'] ) ? true : false;

		if ( true === $dry_run ) {
			WP_CLI::line( "---Dry Run---" );
		} else {
			WP_CLI::line( "---Live Run--" );
		}

		do {
			$redirects = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s ORDER BY post_id ASC LIMIT %d, 1000", $meta_key, $offset ) );
			$i = 0;
			$total = count( $redirects );
			WP_CLI::line( "Found $total entries" );

			foreach ( $redirects as $redirect ) {
				$i++;
				WP_CLI::line( "Adding redirect for {$redirect->post_id} from {$redirect->meta_value}" );
				WP_CLI::line( "-- $i of $total (starting at offset $offset)" );
				
				if ( true === $skip_dupes && 0 !== WPCOM_Legacy_Redirector::get_redirect_post_id( parse_url( $redirect->meta_value, PHP_URL_PATH ) ) ) {
					WP_CLI::line( "Redirect for {$redirect->post_id} from {$redirect->meta_value} already exists. Skipping" );
					continue;
				}

				if ( false === $dry_run ) {
					WPCOM_Legacy_Redirector::insert_legacy_redirect( $redirect->meta_value, $redirect->post_id );
				}

				if ( 0 == $i % 100 ) {
					if ( function_exists( 'stop_the_insanity' ) )
						stop_the_insanity();
					sleep( 1 );
				}
			}
			$offset += 1000;
		} while( $redirects && $offset < $end_offset );
	}

	/**
 	 * Bulk import redirects from a CSV file matching the following structure:
 	 *
 	 * redirect_from_path,(redirect_to_post_id|redirect_to_path|redirect_to_url)
 	 *
 	 * @subcommand import-from-csv
 	 * @synopsis --csv=<path-to-csv>
 	 */
	function import_from_csv( $args, $assoc_args ) {
		define( 'WP_IMPORTING', true );

		if ( empty( $assoc_args['csv'] ) || ! file_exists( $assoc_args['csv'] ) ) {
			WP_CLI::error( "Invalid 'csv' file" );
		}

		global $wpdb;
		if ( ( $handle = fopen( $assoc_args['csv'], "r" ) ) !== FALSE ) {
			while ( ( $data = fgetcsv( $handle, 2000, "," ) ) !== FALSE ) {
				$row++;
				$redirect_from = $data[ 0 ];
				$redirect_to = $data[ 1 ];
				WP_CLI::line( "Adding (CSV) redirect for {$redirect_from} to {$redirect_to}" );
				WP_CLI::line( "-- at $row" );
				WPCOM_Legacy_Redirector::insert_legacy_redirect( $redirect_from, $redirect_to );

				if ( 0 == $row % 100 ) {
					if ( function_exists( 'stop_the_insanity' ) )
						stop_the_insanity();
					sleep( 1 );
				}
			}
			fclose( $handle );
		}
	}

}

WP_CLI::add_command( 'wpcom-legacy-redirector', 'WPCOM_Legacy_Redirector_CLI' );
