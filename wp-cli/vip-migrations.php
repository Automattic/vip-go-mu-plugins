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
	 * Removes unnecessary metadata from attachments (like `sizes`).
	 *
	 * @subcommand clean-image-metadata
	 */
	function clean_image_metadata( $args, $assoc_args ) {
		$posts_per_page = 300;
		$offset = 0;

		$total_attachments = wp_count_posts( 'attachment' )->inherit;

		do {
			WP_CLI::line( sprintf( 'Processing offset %s (of total %s)', number_format_i18n( $offset ), number_format_i18n( $total_attachments ) ) );

			$attachment_ids = get_posts( [
				'post_type' => 'attachment',
				'post_status' => 'inherit',
				'fields' => 'ids',
				'orderby' => 'ID',
				'order' => 'DESC',
				'posts_per_page' => $posts_per_page,
				'offset' => $offset,
			] );

			if ( empty( $attachment_ids ) ) {
				break;
			}

			foreach ( $attachment_ids as $attachment_id ) {
				WP_CLI::line( sprintf( '- attachment %d', $attachment_id ) );
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( isset( $metadata['sizes'] ) ) {
					WP_CLI::line( '--> removing `sizes` metadata' );

					$metadata['sizes'] = array();
					wp_update_attachment_metadata( $attachment_id, $metadata );
				}
			}

			$this->stop_the_insanity();
			sleep( 3 );

			$offset += $posts_per_page;
		} while ( true );
	}
}

WP_CLI::add_command( 'vip migration', 'VIP_Go_Migrations_Command' );
