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
			WP_CLI::error( 'This is not a multisite install.' );
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
	 * @synopsis --output=<csv-filename> [--log-found]
	 */
	public function validate_attachments( $args, $assoc_args ) {
		$attachment_count = array_sum( (array) wp_count_posts( 'attachment' ) );

		if ( isset( $args['log-found'] ) ) {
			$log_found = true;
		} else {
			$log_found = false;
		}

		$output_file = $assoc_args['output'];

		$posts_per_page = 500;
		$paged = 1;
		$count = 0;
		$output = array();

		$progress = \WP_CLI\Utils\make_progress_bar( 'Checking ' . number_format( $attachment_count ) . ' attachments', $attachment_count );
		$file_descriptor = fopen( $output_file, 'w' );
		do {
			$attachments = get_posts( array(
				'post_type' => 'attachment',
				'posts_per_page' => $posts_per_page,
				'paged' => $paged,
			) );

			foreach ( $attachments as $attachment ) {
				$url = $attachment->guid;
				$request = wp_remote_head( $url );
				if ( 200 !== $request['response']['code'] ) {
					$output[] = array(
						$url,
						$request['response']['code'],
						$request['response']['message'],
					);
				} else {
					if ( $log_found ) {
						$output[] = array(
							$url,
							$request['response']['code'],
							$request['response']['message'],
						);
					}
				}

				$progress->tick();
				$count++;
			}

			// Pause.
			sleep( 1 );

			// Free up memory.
			$this->stop_the_insanity();
			$paged++;
		} while ( count( $attachments ) );
		$progress->finish();
		WP_CLI\Utils\write_csv( $file_descriptor, $output );
		fclose( $file_descriptor );
	}
}

WP_CLI::add_command( 'vip migration', 'VIP_Go_Migrations_Command' );
