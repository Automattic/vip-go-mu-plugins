<?php

if ( ! defined( 'WP_CLI' ) ) return;

define( 'WP_IMPORTING', true ); // to prevent potentially expensive actions being triggered on delete

/**
 * Scrub Posts
 * 
 * Borrowed from https://github.com/trepmal/scrub-old-posts
 */
class VIP_Scrub_Posts extends WPCOM_VIP_CLI_Command {

	/**
	 * Scrub posts
	 *
	 * ## OPTIONS
	 *
	 * --date=<date>
	 * : Delete posts older than this date.
	 *
	 * [--post_type=<post_type>]
	 * : Post type. Default: post
	 *
	 * [--posts_per_page=<num>]
	 * : Proccess in batches of <num>. Default: 100
	 *
	 * [--dry-run]
	 * : Dry run. Only tell which images aren't found.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vip-scrub posts --date='-1 month'
	 *     wp vip-scrub posts --date='2015-01-01'
	 */
	function posts( $args, $assoc_args ) {

		$dry_run = isset( $assoc_args['dry-run'] ) ? ! ( 'false' === $assoc_args['dry-run'] ) : true;

		$date = date( 'Y-m-d', strtotime( $assoc_args['date'] ) );

		$posts_per_page = intval( $assoc_args['posts_per_page'] );
		if ( $posts_per_page === 0 ) {
			$posts_per_page = 100;
		}

		$post_type = $assoc_args['post_type'];
		if ( empty( $post_type ) ) {
			$post_type = 'post';
		}

		$gtotal = wp_count_posts( $post_type )->publish;
		$args = array(
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'posts_per_page'         => $posts_per_page,
			'post_type'              => $post_type,
			'date_query'             => array(
				array(
					'before' => $date,
				),
			),
		);

		$scrub_query = new WP_Query( $args );
		$pages = $scrub_query->max_num_pages;
		$total = $scrub_query->found_posts;
		$args['no_found_rows'] = true;

		if ( $total > 0 ) {
			WP_CLI::confirm( sprintf( "Found %d posts (of %d) older than %s. Proceed?", $total, $gtotal, $date ) );
		} else {
			WP_CLI::line( 'No posts found' );
			return;
		}

		$notify = \WP_CLI\Utils\make_progress_bar( sprintf( 'Removing %d post(s)', $total ), $total );

		$count = 0;
		if ( ! $dry_run ) {
			$this->start_bulk_operation();
		}
		for( $i=1; $i<=$pages; $i++ ) {

			if ( $i > 1 ) {
				if ( $dry_run ) {
					$args['paged'] = $i;
				}
				$scrub_query = new WP_Query( $args );
			}


			foreach ( $scrub_query->posts as $post_id ) {
				$count++;
				if ( ! $dry_run ) {
					if ( \cli\Shell::isPiped() ) {
						WP_CLI::line( 'Post Deleted ' . $post_id . ': ' . get_the_title( $post_id ) );
					}
					wp_delete_post( $post_id, true );

					if ( 0 === $count % 100 ) {
						sleep( 2 );
						$this->stop_the_insanity();
					}
				} else {
					if ( \cli\Shell::isPiped() ) {
						WP_CLI::line( '[DRY-RUN] Post Deleted ' . $post_id . ': ' . get_the_title( $post_id ) );
					}
				}


				$notify->tick();
			}

		}
		if ( ! $dry_run ) {
			$this->end_bulk_operation();
		}

		$notify->finish();

	}

}

WP_CLI::add_command( 'vip-scrub', 'VIP_Scrub_Posts' );
