<?php

/**
 * CLI commands to handle useful functions related to backfilling short URLs for posts
 */

WP_CLI::add_command( 'bitly', 'Bitly_Command' );

class Bitly_Command extends WP_CLI_Command {
	/**
	 * Subcommand to delete all bitly urls
	 *
	 * NOTE - Doesn't delete the bitly url for the site, and doesn't flip the bitly_processed option back to 0 to allow backfilling
	 * to restart.
	 *
	 * @subcommand delete-bitly-urls [--post_types=<post_types>] [--post_status=<post_status>] --[published_before=<published_before>]
	 */
	public function delete_bitly_urls( $args, $assoc_args ) {
		$defaults = array(
			'post_types' 	=> 'post,page',
			'post_status' 	=> 'any',
		);

		$args = wp_parse_args( $assoc_args, $defaults );

		// Must process in batches for large sites
		$batch_size = 500;

		$query_args = array(
			'post_type' 		=> explode( ',', $args['post_types'] ),
			'post_status' 		=> $args['post_status'],
			'meta_key'			=> 'bitly_url',
			'posts_per_page'	=> $batch_size,
			'order_by'			=> 'ID',
			'order'				=> 'ASC',
			'paged'				=> 0,
			'cache_results'		=> false,
			'update_meta_cache'	=> false,
			'update_term_cache' => false
		);

		if ( $args['published_before'] ) {
			$query_args['date_query'] = array(
				array(
					'before' => $args['published_before']
				)
			);
		}

		$query = new WP_Query( $query_args );

		WP_CLI::line( sprintf( 'Found %d posts to update', $query->found_posts ) );

		while( $query->post_count ) {
			foreach ( $query->posts as $post ) {
				WP_CLI::line( sprintf( 'Deleting bitly url for post %d', $post->ID ) );
				
				delete_post_meta( $post->ID, 'bitly_url' );
			}

			sleep( 2 );

			$this->stop_the_insanity();

			$query = new WP_Query( $query_args );
		}

		WP_CLI::success( 'All done!' );
	}
	
	/**
	 * Runs the backfill from the command line.
	 * You can optionally specify a new limit.
	 * 
	 * @subcommand run-backfill
	 * @synopsis [--limit=<limit>]
	 */
	public function run_backfill( $args, $assoc_args ) {
		$defaults = array();

		$args = wp_parse_args( $assoc_args, $defaults );

		// If a limit was passed in, use it
		$hourly_limit = ( is_numeric( $args['limit'] ) ) ? $args['limit'] : null;
		
		// Run the backfill
		$timestamp_start = microtime( true );

		WP_CLI::line( "Starting backfill of bit.ly short URLs" );

		bitly_process_posts( $hourly_limit );

		WP_CLI::success( "Finished backfill of bit.ly short URLs in " . number_format( (microtime( true ) - $timestamp_start), 2 ) . " seconds" );
	}

	/**
	 * Counts how many posts don't have a bitly url yet.
	 * 
	 * @subcommand count-posts-missing-url
	 */
	public function count_posts_missing_url( $args, $assoc_args ) {
		global $wpdb;

		$post_type_sql = '';

		// get the post types that are supported
		$post_types = bitly_get_post_types();

		// build the sql for querying post types
		if( count( $post_types ) ) {

			foreach( $post_types as $post_type ) {
				$sanitized_post_types[] = $wpdb->prepare( '%s', $post_type );
			}

			$post_type_sql = sprintf( '%s IN ( %s )', "$wpdb->posts.post_type", implode( ',', $sanitized_post_types ) );
		}

		// Only do the query if there's post_type sql
		if( empty( $post_type_sql ) )
			WP_CLI::error( 'No supported post types found' );

		$query = "
			SELECT COUNT(*)
			FROM $wpdb->posts
			LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id AND $wpdb->postmeta.meta_key = 'bitly_url' ) 
			WHERE 1=1
			AND ( $post_type_sql )
			AND ( $wpdb->posts.post_status = 'publish' )
			AND ( $wpdb->postmeta.post_id IS NULL )
			GROUP BY $wpdb->posts.ID
		";
					
		// Get the posts
		$count = $wpdb->query( $query );

		WP_CLI::success( sprintf( 'Found %d posts without a bit.ly url', $count ) );
	}

	/**
	 * Clear all of the caches for memory management
	 */
	private function stop_the_insanity() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

		if ( !is_object( $wp_object_cache ) )
			return;

		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();

		if( is_callable( $wp_object_cache, '__remoteset' ) )
			$wp_object_cache->__remoteset(); // important
	}
}