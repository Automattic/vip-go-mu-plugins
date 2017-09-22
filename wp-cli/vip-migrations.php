<?php

use \WP_CLI\Utils;

class VIP_Go_Migrations_Command extends WPCOM_VIP_CLI_Command {

	/**
	 * Deletes senstive options
	 *
	 * @subcommand scrub-site
	 */
	public function clear_sensitive_options( $args, $assoc_args ) {
		WP_CLI::log( 'Clearing sensitive options (like Jetpack connection).' );

		// TODO: if called from preprod site with production values, does it disconnect the preprod site?
//		WP_CLI::log( '- Disconnecting Jetpack' );
//		Jetpack::disconnect();

		WP_CLI::log( '- Deleting Options' );
		$options = [
			'jetpack_options',
			'jetpack_private_options',
			'vaultpress',
			'wordpress_api_key',
		];

		foreach ( $options as $option_name ) {
			WP_CLI::log( '-- ' . $option_name );
			delete_option( $option_name );
		}
	}

	/**
	 * Update URLs across a whitelist of table columns
	 *
	 * ## OPTIONS
	 *
	 * --from=<from_url>
	 * : The URL to search for.
	 *
	 * --to=<to_url>
	 * : The URL to replace with.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vip migration search-replace-url 'http://example.com' 'http://example.go-vip.co'
	 *
	 * @subcommand search-replace-url
	 */
	public function search_replace_url( $args, $assoc_args ) {
		$from_url = $assoc_args['from'] ?? false;
		$to_url = $assoc_args['to'] ?? false;

		$from_url_parsed = parse_url( $from_url );
		if ( empty( $from_url ) || ! $from_url_parsed ) {
			WP_CLI::error( sprintf( 'Please provide a valid `--from` URL (current: %s)', $from_url ) );
			return;
		}

		$to_url_parsed = parse_url( $to_url );
		if ( empty( $to_url ) || ! $to_url_parsed ) {
			WP_CLI::error( sprintf( 'Please provide a valid `--to` URL (current: %s)', $to_url ) );
			return;
		}

		$ignored_tables_and_columns = [
			'commentmeta' => [
				'meta_id',
				'comment_id',
			],

			'comments' => [
				'comment_ID',
				'comment_post_ID',
				'comment_author_IP',
				'comment_date',
				'comment_date_gmt',
				'comment_karma',
				'comment_approved',
				'comment_type',
				'comment_parent',
				'user_id',
			],

			'links' => [
				'link_id',
				'link_target',
				'link_visible',
				'link_owner',
				'link_rating',
				'link_updated',
			],

			'options' => [
				'option_id',
				'autoload',
			],

			'postmeta' => [
				'meta_id',
				'post_id',
			],

			'posts' => [
				'ID',
				'post_author',
				'post_date',
				'post_date_gmt',
				'comment_status',
				'ping_status',
				'post_password',
				'to_ping',
				'pinged',
				'post_modified',
				'post_modified_gmt',
				'post_parent',
				'menu_order',
				'post_type',
				'post_mime_type',
				'comment_count',
			],

			'term_taxonomy' => [
				'term_taxonomy_id',
				'term_id',
				'taxonomy',
				'parent',
				'count',
			],

			'wp_term_relationships' => [
				'object_id',
				'term_taxonomy_id',
				'term_order',
			],

			'termmeta' => [
				'meta_id',
				'term_id',
			],

			'terms' => [
				'term_id',
				'slug',
				'term_group',
			],

			'usermeta' => [
				'umeta_id',
				'user_id',
			],

			'users' => [
				'ID',
				'user_login',
				'user_pass',
				'user_nicename',
				'user_email',
				'user_registered',
				'user_activation_key',
				'user_status',
			],

			// TODO: multisite tables https://codex.wordpress.org/Database_Description#Multisite_Table_Details
		];

		$runcommand_args = [
			'exit_error' => false,
		];

		$all_tables = \WP_CLI\Utils\wp_get_table_names( null, [ 'all-tables' ] );

		foreach ( $all_tables as $table ) {
			$table_type = false;
			foreach ( array_keys( $ignored_tables_and_columns ) as $type ) {
				if ( wp_endswith( $table, $type ) ) {
					$table_type = $type;
					break;
				}
			}

			$skip_columns = false;
			if ( isset( $ignored_tables_and_columns[ $table_type ] ) ) {
				$skip_columns = $ignored_tables_and_columns[ $table_type ];
			}

			$command = sprintf(
				'vip search-replace %1$s %2$s %3$s --skip-columns=%4$s --verbose',
				escapeshellarg( $from_url ),
				escapeshellarg( $to_url ),
				escapeshellarg( $table ),
				escapeshellarg( implode( ',', $skip_columns ) )
			);

			WP_CLI::log( 'Running command: ' . $command );
			WP_CLI::runcommand( $command, $runcommand_args );
			WP_CLI::log( '---' );
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
}

WP_CLI::add_command( 'vip migration', 'VIP_Go_Migrations_Command' );
