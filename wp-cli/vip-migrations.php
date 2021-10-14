<?php

use \WP_CLI\Utils;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class VIP_Go_Migrations_Command extends WPCOM_VIP_CLI_Command {

	/**
	 * Run cleanup on the current site database.
	 *
	 * [--network]
	 * : Cleanup all sites on the network
	 *
	 * [--dry-run]
	 * : Show changes without updating
	 *
	 * [--skip-confirm]
	 * : Skip the confirmation prompt
	 */
	public function cleanup( $args, $assoc_args ) {
		global $wpdb;

		$dry_run      = Utils\get_flag_value( $assoc_args, 'dry-run' );
		$skip_confirm = Utils\get_flag_value( $assoc_args, 'skip-confirm' );
		if ( $dry_run ) {
			WP_CLI::log( 'Performing a dry run, with no database modification.' );
		} elseif ( ! $skip_confirm ) {
			$env = defined( 'VIP_GO_ENV' ) ? VIP_GO_ENV : 'unknown';
			WP_CLI::confirm( sprintf( 'Are you sure you want to run cleanup on the %s environment?', $env ), $assoc_args );
		}

		$network = Utils\get_flag_value( $assoc_args, 'network' );
		if ( $network && ! is_multisite() ) {
			WP_CLI::warning( 'This is not a multisite install. Proceeding as single site.' );
			$network = false;
		}

		if ( $network ) {
			$iterator_args = array(
				'table' => $wpdb->blogs,
				'where' => array(
					'spam'     => 0,
					'deleted'  => 0,
					'archived' => 0,
				),
			);
			$it            = new \WP_CLI\Iterators\Table( $iterator_args );
			foreach ( $it as $blog ) {
				$url = $blog->domain . $blog->path;
				$cmd = "--url={$url} vip migration cleanup --skip-confirm";

				if ( $dry_run ) {
					$cmd .= ' --dry-run';
				}

				WP_CLI::line();
				WP_CLI::line( 'Cleaning: ' . $url );
				WP_CLI::runcommand( $cmd );
			}

			return;
		}

		// Cleanup options
		$options = [
			'jetpack_options',
			'jetpack_private_options',
			'vaultpress',
			'wordpress_api_key',
		];

		foreach ( $options as $option ) {
			WP_CLI::line( 'Deleting option: ' . $option );
			if ( ! $dry_run ) {
				delete_option( $option );
			}
		}

		WP_CLI::line( 'Calling Automattic\VIP\Migration\run_after_data_migration_cleanup()' );

		if ( ! $dry_run ) {
			Automattic\VIP\Migration\run_after_data_migration_cleanup();
		}
	}

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
	 * : Which tables to update (all, blog, global, ms_global, "")
	 * ---
	 * default: ""
	 * options:
	 *   - all
	 *   - blog
	 *   - global
	 *   - ms_global
	 *   - ""
	 */
	public function dbdelta( $args, $assoc_args ) {
		global $wpdb;

		$tables = isset( $args[1] ) ? $args[1] : '';

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
				'where' => array(
					'spam'     => 0,
					'deleted'  => 0,
					'archived' => 0,
				),
			);
			$it            = new \WP_CLI\Iterators\Table( $iterator_args );
			foreach ( $it as $blog ) {
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

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$changes = dbDelta( $tables, ! $dry_run );

		if ( empty( $changes ) ) {
			WP_CLI::success( 'No changes.' );
			return;
		}

		foreach ( $changes as $change ) {
			WP_CLI::line( $change );
		}

		$count = count( $changes );
		// translators: 1 - number of changes
		WP_CLI::success( sprintf( _n( '%s change', '%s changes', $count ), number_format_i18n( $count ) ) );
	}

	/**
	 * Iterate over attachments and check to see if they actually exist.
	 *
	 * Found files are cached in the /tmp directory for quicker re-checks.
	 *
	 * ## OPTIONS
	 *
	 * <csv-filename>
	 * : The CSV file for output.  The CSV file has a header with the following structure: `"url", "status"`
	 *
	 * [--extra-check]
	 * : Checks the attachment's `_wp_attached_image` post meta for an edited image filename with a new URL.  This will be slower as it adds additional SQL queries.
	 *
	 * [--disable-cache]
	 * : Disables the cache by appending a random query argument to each URL.
	 *
	 * [--find-empty-files]
	 * : Logs files that are returned with 0 bytes.  Should probably not be used with `--log-found-files` because they will probably also return a 200.
	 *
	 * [--log-found-files]
	 * : By default, only URLs with a status other than "200" are logged.  This will log found files as well.
	 *
	 * [--start_date=<start_date>]
	 * : The date to start the query from.
	 *
	 * [--end_date=<end_date>]
	 * : The date to end the query with.
	 *
	 * @subcommand validate-attachments
	 */
	public function validate_attachments( $args, $assoc_args ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_init, WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_multi_add_handle, WordPress.WP.AlternativeFunctions.curl_curl_multi_exec
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_getinfo, WordPress.WP.AlternativeFunctions.curl_curl_multi_remove_handle
		$log_found_files = WP_CLI\Utils\get_flag_value( $assoc_args, 'log-found-files', false );
		$output_file     = $args[0];
		$extra_check     = WP_CLI\Utils\get_flag_value( $assoc_args, 'extra-check', false );
		$find_empty      = WP_CLI\Utils\get_flag_value( $assoc_args, 'find-empty-files', false );
		$disable_cache   = WP_CLI\Utils\get_flag_value( $assoc_args, 'disable-cache', false );

		$offset  = 0;
		$limit   = 500;
		$threads = 10;
		$output  = array( array( 'url', 'status' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- should be safe here
		$file_descriptor = fopen( $output_file, 'w' );
		if ( false === $file_descriptor ) {
			WP_CLI::error( sprintf( 'Cannot open file for writing: %s', $output_file ) );
		}

		global $wpdb;

		$date_query = '';
		if ( isset( $assoc_args['start_date'] ) ) {
			$date_query .= $wpdb->prepare( ' AND post_date > %s ', $assoc_args['start_date'] );
		}

		if ( isset( $assoc_args['end_date'] ) ) {
			$date_query .= $wpdb->prepare( ' AND post_date < %s ', $assoc_args['end_date'] );
		}

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' " . $date_query;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$attachment_count = $wpdb->get_row( $count_sql, ARRAY_N )[0];
		WP_CLI::log( 'Saving output to ' . $output_file );

		if ( $log_found_files ) {
			WP_CLI::log( 'Logging found files.' );
		}

		if ( $extra_check ) {
			WP_CLI::log( 'Running Extra URL Checks.  The total may increase as new URLs are found.' );
		}

		if ( $find_empty ) {
			WP_CLI::log( 'Logging Empty Files.' );
		}

		if ( $disable_cache ) {
			WP_CLI::log( 'Disabling Varnish Cache.' );
		}
		
		$progress   = \WP_CLI\Utils\make_progress_bar( 'Checking ' . number_format( $attachment_count ) . ' attachments', $attachment_count );
		$upload_dir = wp_get_upload_dir();

		if ( ! file_exists( get_temp_dir() . '/validate-files-' . md5( get_site_url() ) ) ) {
			$cache = [];
		} else {
			$cache = json_decode( file_get_contents( get_temp_dir() . '/validate-files-' . md5( get_site_url() ) ), ARRAY_N );
		}

		do {
			$extra_count    = 0;
			$attachments    = [];
			$sql            = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' %s LIMIT %d,%d", $date_query ?? false, $offset, $limit );
			$attachment_ids = array_map(
				function( $attachment_ids ) {
					return (int) $attachment_ids[0];
				},
				$wpdb->get_results( $sql, ARRAY_N ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);

			foreach ( $attachment_ids as $attachment_id ) {
				$attachment_url = wp_get_attachment_url( $attachment_id );
				$attachments[]  = $attachment_url;

				if ( $extra_check ) {
					$attached_file = $wpdb->get_results( $wpdb->prepare( 'SELECT meta_value from ' . $wpdb->postmeta . ' WHERE post_id=%d AND meta_key="_wp_attached_file"', $attachment_id ) );
					if ( ! empty( $attached_file ) ) {
						$extra_url = $upload_dir['baseurl'] . '/' . $attached_file[0]->meta_value;
						if ( $extra_url !== $attachment_url ) {
							$attachments[] = $extra_url;
							$extra_count++;
						}
					}
				}
			}

			if ( 0 !== $extra_count ) {
				$attachment_count += $extra_count;
				$progress->reset( $attachment_count );
			}

			$attachments        = array_unique( $attachments ); // Just in case, let's clear out any dupes.
			$attachments_arrays = array_chunk( $attachments, $threads ); // Break the attachments into groups of maxiumum 10 elements.
			$mh                 = curl_multi_init();

			// Loop through each block of 10 attachments.
			foreach ( $attachments_arrays as $attachments_array ) {

				$ch    = array();
				$index = 0;

				foreach ( $attachments_array as $url ) {
					if ( isset( $cache['200'] ) && in_array( $url, $cache['200'], true ) ) {
						continue;
					}

					// By switching the URLs from http:// to https>// we save a request, since it will be redirected to the SSL url
					if ( is_ssl() ) {
						$url = str_replace( 'http://', 'https://', $url );
					}

					$ch[ $index ] = curl_init();
					curl_setopt( $ch[ $index ], CURLOPT_RETURNTRANSFER, true );

					if ( $disable_cache ) {
						curl_setopt( $ch[ $index ], CURLOPT_URL, $url . '?disable-cache=' . wp_rand( 1000000, 9999999 ) );
					} else {
						curl_setopt( $ch[ $index ], CURLOPT_URL, $url );
					}
					curl_setopt( $ch[ $index ], CURLOPT_FOLLOWLOCATION, true );
					curl_setopt( $ch[ $index ], CURLOPT_NOBODY, true );

					curl_multi_add_handle( $mh, $ch[ $index ] );
					$index++;
				}

				// Exec the cURL requests.
				$curl_active = null;

				do {
					curl_multi_exec( $mh, $curl_active );
				} while ( $curl_active > 0 );

				// Process the responses.
				foreach ( $ch as $index => $handle ) {
					$log_request   = false;
					$response_code = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
					$url           = curl_getinfo( $handle, CURLINFO_EFFECTIVE_URL );
					$url           = strtok( $url, '?' );

					curl_multi_remove_handle( $mh, $handle );

					$cache[ $response_code ][] = $url;

					if ( 200 === $response_code ) {
						$log_request = $log_found_files;
					} else {
						$log_request = true;
					}

					if ( $find_empty && curl_getinfo( $handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD ) <= 0 ) {
						$log_request = true;
					}

					if ( $log_request ) {
						$output[] = array(
							$url,
							$response_code,
						);
					}
				}

				$progress->tick( count( $attachments_arrays ), 'Checking ' . number_format( $attachment_count ) . ' attachments' );

			}

			// Pause.
			sleep( 1 );

			$offset              += $limit;
			$attachment_ids_count = count( $attachment_ids );
		} while ( $attachment_ids_count );

		$progress->finish();
		WP_CLI\Utils\write_csv( $file_descriptor, $output );
		fclose( $file_descriptor );

		foreach ( $cache as $response_code => $url_array ) {
			$cache[ $response_code ] = array_unique( $url_array );
			WP_CLI::line( 'Found ' . number_format( count( $cache[ $response_code ] ) ) . ' URLs with a response code of ' . $response_code );

			// We only want to cache 200s.
			if ( 200 != $response_code ) {
				unset( $cache[ $response_code ] );
			}
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- false positive, get_temp_dir() is used
		file_put_contents( get_temp_dir() . '/validate-files-' . md5( get_site_url() ), wp_json_encode( $cache ) );
		// phpcs:enable
	}

	/**
	 * Import user meta attributes from a CSV file.
	 *
	 * The CSV file is headerless with the following structure:
	 *
	 * ```
	 * user_key,meta_key,meta_value stored in JSON
	 * ```
	 *
	 * Example:
	 *
	 * ```
	 * jsmith,user_profile,"{""title"":""editorial assistant"",""bio"":""John Smith is an editorial assistant at BigNewsCo.""}"
	 * ```
	 *
	 * In 99.999% of cases, this CSV file will be generated by a WP.com CLI command: `wp vip-export user-attributes`
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The CSV file to import from.
	 *
	 * [--user_key=<userlogin>]
	 * : The `user_key` is the "key" used to uniquely identify a user, a property of the `WP_User` object.  Can be one of the following: ID, user_nicename, user_email, user_login. Defaults to user_login.
	 *
	 * [--dry-run=<true>]
	 * : Do a "dry run" and no data modification will be done.  Defaults to true.
	 *
	 * ## EXAMPLES
	 *
	 *     # Imports user meta from the example "usermeta.csv" file with the default user key.
	 *     $ wp vip migration import-user-meta usermeta.csv --dry-run=false
	 *
	 *     # Does a "dry run" import from "usermeta.csv" with the "user_email" user key.
	 *     $ wp vip migration import-user-meta usermeta.csv --user_key=user_email
	 *
	 * @subcommand import-user-meta
	 */
	public function import_user_meta( $args, $assoc_args ) {
		$filename = $args[0];
		$user_key = $assoc_args['user_key'] ?? 'user_login';
		$dry_run  = Utils\get_flag_value( $assoc_args, 'dry-run', true );

		// Force a boolean, always default to true.
		$dry_run = filter_var( $dry_run, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? true;

		if ( $dry_run ) {
			WP_CLI::log( 'Performing a dry run, with no database modification.' );
		}

		if ( ! file_exists( $filename ) ) {
			WP_CLI::error( sprintf( 'Missing file: %s', $filename ) );
		}

		foreach ( new \WP_CLI\Iterators\CSV( $filename ) as $user_data ) {
			$user_data                                  = array_values( $user_data ); // Strip useless array keys.
			list( $user_value, $meta_key, $meta_value ) = $user_data;

			$meta_value = json_decode( $meta_value, true );

			switch ( $user_key ) {
				case 'ID':
					$user = get_user_by( 'ID', $user_value );
					break;
				case 'user_nicename':
					$user = get_user_by( 'slug', $user_value );
					break;
				case 'user_email':
					$user = get_user_by( 'email', $user_value );
					break;
				case 'user_login':
					$user = get_user_by( 'login', $user_value );
					break;
				default:
					WP_CLI::warning( 'Error getting user ' . $user_value );
			}

			if ( ! $dry_run ) {
				// Live run
				$add_meta = update_user_meta( $user->ID, $meta_key, $meta_value );
				if ( false !== $add_meta ) {
					WP_CLI::line( 'Meta ' . $meta_key . ' added to user ' . $user_value );
				} else {
					WP_CLI::warning( 'Meta ' . $meta_key . ' NOT added to user ' . $user_value );
				}
			} else {
				// Dry Run
				WP_CLI::line( '[DRY-RUN] Meta ' . $meta_key . ' added to user ' . $user_value );
			}
		}
	}
}

WP_CLI::add_command( 'vip migration', 'VIP_Go_Migrations_Command' );
