<?php

use \WP_CLI\Utils;

class VIP_Go_Migrations_Command extends WPCOM_VIP_CLI_Command {

	/**
	 * Run dbDelta() for the current site.
	 *
	 * [--network]
	 * : Update databases for all sites on a network
	 *
	 * [--dry-run]
	 * : Show changes without updating
	 *
	 * ## OPTIONS
	 *
	 * [<tables>]
	 * : Which tables to update (all, blog, global, ms_global)
	 */
	function dbdelta( $args, $assoc_args ) {
		global $wpdb;

		$network = Utils\get_flag_value( $assoc_args, 'network' );
		if ( $network && ! is_multisite() ) {
			WP_CLI::warning( 'This is not a multisite install. Proceeding as single site.' );
			$network = false;
		}

		$dry_run = Utils\get_flag_value( $assoc_args, 'dry-run' );
		if ( $dry_run ) {
			WP_CLI::log( 'Performing a dry run, with no database modification.' );
		}

		if ( $network ) {
			$iterator_args = array(
				'table' => $wpdb->blogs,
				'where' => array( 'spam' => 0, 'deleted' => 0, 'archived' => 0 ),
			);
			$it = new \WP_CLI\Iterators\Table( $iterator_args );
			foreach( $it as $blog ) {
				$url = $blog->domain . $blog->path;
				$cmd = "--url={$url} vip migration dbdelta";

				// Update global tables if this is the main site
				// otherwise only update the given blog's tables
				if ( is_main_site( $blog->blog_id ) ) {
					$cmd .= ' all';
				} else {
					$cmd .= ' blog';
				}

				if ( $dry_run ) {
					$cmd .= ' --dry-run';
				}

				WP_CLI::line();
				WP_CLI::line( WP_CLI::colorize( '%mUpdating:%n ' ) . $blog->domain . $blog->path );
				WP_CLI::runcommand( $cmd );
			}
			return;
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		if ( in_array( $args[1], array( '', 'all', 'blog', 'global', 'ms_global' ), true ) ) {
			$changes = dbDelta( $args[1], !$dry_run );
		} else {
			$changes = dbDelta( null, !$dry_run );
		}

		if ( empty( $changes ) ) {
			WP_CLI::success( 'No changes.' );
			return;
		}

		foreach( $changes as $change ) {
			WP_CLI::line( $change );
		}

		$count = count( $changes );
		WP_CLI::success( _n( '%s change', '%s changes', $count ), number_format_i18n( $count ) );
	}

	/**
	 * Iterate over attachments and check to see if they actually exist.
	 *
	 * @subcommand validate-attachments
	 * @synopsis <csv-filename> [--log-found-files]
	 */
	public function validate_attachments( $args, $assoc_args ) {
		$log_found_files = WP_CLI\Utils\get_flag_value( $assoc_args, 'log-found-files', false );
		$output_file = $args[0];

		$offset = 0;
		$limit = 500;
		$output = array();

		$attachment_count = array_sum( (array) wp_count_posts( 'attachment' ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Checking ' . number_format( $attachment_count ) . ' attachments', $attachment_count );

		$file_descriptor = fopen( $output_file, 'w' );
		if ( false === $file_descriptor ) {
			WP_CLI::error( sprintf( 'Cannot open file for writing: %s', $filename ) );
		}

		global $wpdb;
		do {
			$sql = $wpdb->prepare( 'SELECT guid FROM ' . $wpdb->posts . ' WHERE post_type = "attachment" LIMIT %d,%d', $offset, $limit );
			$attachments = $wpdb->get_results( $sql );

			foreach ( $attachments as $attachment ) {
				$log_request = false;
				$url = $attachment->guid;

				/*
				 * TODO: Switch over to `curl_multi` to do lookups in parallel
				 * if this turns out to be too slow for large media libraries.
				 */
				$request = wp_remote_head( $url );
				$response_code = wp_remote_retrieve_response_code( $request );
				$response_message = wp_remote_retrieve_response_message( $request );

				if ( 200 === $response_code ) {
					$log_request = $log_found_files;
				} else {
					$log_request = true;
				}

				if ( $log_request ) {
					$output[] = array(
						$url,
						$response_code,
						$response_message,
					);
				}

				$progress->tick();
			}

			// Pause.
			sleep( 1 );

			$offset += $limit;
		} while ( count( $attachments ) );
		$progress->finish();
		WP_CLI\Utils\write_csv( $file_descriptor, $output );
		fclose( $file_descriptor );
	}
	
	/**
	 * Update user IDs after importing.
	 *
	 * ## OPTIONS
	 *
	 * <file> [--user_key=<userlogin>]
	 * : The local or remote CSV file of users to import.
	 *
	 * @subcommand update-user-ids
	 */
	public function update_user_ids( $args, $assoc_args ) {
		WP_CLI::confirm( 'This can really mess up a site if used wrong.  Are you sure?' );

		$filename = $args[0];
		$user_key = $assoc_args['user_key'] ?? 'user_login';

		if ( ! file_exists( $filename ) ) {
			WP_CLI::error( sprintf( "Missing file: %s", $filename ) );
		}

		global $wpdb;

		foreach ( new \WP_CLI\Iterators\CSV( $filename ) as $new_user ) {
			// WordPress _really_ doesn't like changing user IDs.  We have to do this manually via a query.
			$update = $wpdb->prepare( 'UPDATE ' . $wpdb->users . ' SET ID = %d WHERE %s = %s', $new_user['ID'], $user_key, $new_user[ $user_key ] );
			if ( false !== $wpdb->query( $update ) ) {
				WP_CLI::line( 'User ' . $new_user[ $user_key ] . ' ID updated to ' . $new_user['ID'] );
			} else {
				WP_CLI::warning( 'User ' . $new_user[ $user_key ] . ' ID NOT updated to ' . $new_user['ID'] );
			}
		}
		wp_cache_flush();
	}
}

WP_CLI::add_command( 'vip migration', 'VIP_Go_Migrations_Command' );
