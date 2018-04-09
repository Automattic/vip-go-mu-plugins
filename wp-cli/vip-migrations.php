<?php

use \WP_CLI\Utils;

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
	 * [--yes]
	 * : Skip the confirmation prompt
	 */
	function cleanup( $args, $assoc_args ) {
		global $wpdb;

		$dry_run = Utils\get_flag_value( $assoc_args, 'dry-run' );
		if ( $dry_run ) {
			WP_CLI::log( 'Performing a dry run, with no database modification.' );
		} else {
			$env = defined( 'VIP_GO_ENV' ) ? VIP_GO_ENV : 'unknown';
			WP_CLI::confirm( sprintf( 'Are you sure you want to run cleanup on the %s environment?', $env ) , $assoc_args );
		}

		$network = Utils\get_flag_value( $assoc_args, 'network' );
		if ( $network && ! is_multisite() ) {
			WP_CLI::warning( 'This is not a multisite install. Proceeding as single site.' );
			$network = false;
		}

		if ( $network ) {
			$iterator_args = array(
				'table' => $wpdb->blogs,
				'where' => array( 'spam' => 0, 'deleted' => 0, 'archived' => 0 ),
			);
			$it = new \WP_CLI\Iterators\Table( $iterator_args );
			foreach ( $it as $blog ) {
				$url = $blog->domain . $blog->path;
				$cmd = "--url={$url} vip migration cleanup";

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
	function dbdelta( $args, $assoc_args ) {
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
				'where' => array( 'spam' => 0, 'deleted' => 0, 'archived' => 0 ),
			);
			$it = new \WP_CLI\Iterators\Table( $iterator_args );
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

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$changes = dbDelta( $tables, ! $dry_run );

		if ( empty( $changes ) ) {
			WP_CLI::success( 'No changes.' );
			return;
		}

		foreach ( $changes as $change ) {
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
	function import_user_meta( $args, $assoc_args ) {
		$filename = $args[0];
		$user_key = $assoc_args['user_key'] ?? 'user_login';
		$dry_run = Utils\get_flag_value( $assoc_args, 'dry-run', true );

		// Force a boolean, always default to true.
		$dry_run = filter_var( $dry_run, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? true;

		if ( $dry_run ) {
			WP_CLI::log( 'Performing a dry run, with no database modification.' );
		}

		if ( ! file_exists( $filename ) ) {
			WP_CLI::error( sprintf( 'Missing file: %s', $filename ) );
		}

		foreach ( new \WP_CLI\Iterators\CSV( $filename ) as $user_data ) {
			$user_data = array_values( $user_data ); // Strip useless array keys.
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

	/**
	 * Import theme options, sidebars options and widgets options from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The JSON file with all the settings.
	 *
	 * [--dry-run=<true>]
	 * : Do a "dry run" and no data modification will be done.  Defaults to true.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     # Copies theme options from the JSON file.
	 *     $ wp vip migration import-theme-options file.json
	 *
	 *
	 * @subcommand import-theme-options
	 */
	function import_theme_options($args, $assoc_args) {
		$dry_run = Utils\get_flag_value( $assoc_args, 'dry-run', true );
		$filename = $args[0];

		// Force a boolean, always default to true.
		$dry_run = filter_var( $dry_run, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ?? true;
		if ( $dry_run ) {
			WP_CLI::warning( 'Performing a dry run, with no database modification.' );
		}

		$current_theme = get_option( 'stylesheet' );

		if ( ! file_exists( $filename ) || ( $json = file_get_contents( $filename ) ) === false ) {
			WP_CLI::error( "The provided file does not exist or couldn't be open." );
		}

		WP_CLI::log( "$filename is a valid file. Parsing the content..." );
		$options = json_decode( $json, true );

		if( ! $options || ! ( is_array( $options['theme'] ) && is_array( $options['sidebar'] ) && is_array( $options['widgets'] ) ) ) {
			WP_CLI::error( "The file $filename is not a valid JSON file." );
		}

		WP_CLI::line( "ATTENTION! This operation is not reversible. It will override all the current existent widgets, sidebar, and
active theme options. Please make sure the following data is correct before proceeding. \n" );

		$widget_names = wp_list_pluck( $options['widgets'], 'name' );

		$sidebar_display = "";
		foreach( $options['sidebar'] as $sidebar_name => $sidebar_widgets ) {
			$sidebar_display .= WP_CLI::colorize( " %9* $sidebar_name%n: " );
			$sidebar_display .= implode( $sidebar_widgets, ", ") . "\n";
		}

		WP_CLI::log( WP_CLI::colorize(
			"%9Current Site:%n " . get_home_url( ) . "\n" .
			"%9Current Theme:%n " . $current_theme . "\n" .
			"%9Widgets being replaced/created:%n " . implode( $widget_names,", " ) . "\n" .
			"%9Sidebar and Widget Positions:%n\n" . $sidebar_display
		) );

		WP_CLI::confirm( "Are you sure you want to continue?" , $assoc_args );

		/* Updates Theme Options */
		WP_CLI::line( "\n * Updating Theme options (`theme_mods_$current_theme`)" );
		if ( $dry_run ) {
			WP_CLI::line( "[DRY-RUN] Should update `theme_mods_$current_theme`" );
		} else {
			$update = update_option( "theme_mods_$current_theme", $options['theme'] );

			if ( ! $update ) {
				WP_CLI::warning( "The option `theme_mods_$current_theme` wasn't updated. Perhaps there is nothing to update (the current theme might be already with the original theme options). Continuing." );
			} else {
				WP_CLI::success( "Option `theme_mods_$current_theme` has been updated." );
			}
		}

		/* Updates Sidebar Options */
		WP_CLI::line( " * Updating Sidebar Options" );
		if ( $dry_run ) {
			WP_CLI::line( "[DRY-RUN] Should update `sidebars_options`" );
		} else {
			wp_set_sidebars_widgets( $options['sidebar'] );
			WP_CLI::success( "Option `sidebars_options` has been updated." );
		}

		/* Updates Widgets Options */
		WP_CLI::line(" * Updating widgets Options");
		foreach( $options['widgets'] as $widget ) {
			$widget_name = $widget['name'];
			$widget_value = $widget['value'];

			if ( $dry_run ) {
				WP_CLI::line( "[DRY-RUN] Should update `$widget_name`" );
			} else {
				$update = update_option( $widget_name, $widget_value );

				if ( ! $update ) {
					WP_CLI::warning( "The option `$widget_name` wasn't updated. Perhaps there is nothing to update. Continuing." );
					continue;
				}

				WP_CLI::success( "Option `$widget_name` has been updated" );
			}
		}

		// Flushing immediately is required to be sure that the options will not be poisoned by a bad cache
		WP_CLI::line( "Flushing cache." );
		wp_cache_flush();

		WP_CLI::success( "Done!" );
	}
}

WP_CLI::add_command( 'vip migration', 'VIP_Go_Migrations_Command' );
