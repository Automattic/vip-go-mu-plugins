<?php

class WPCOM_VIP_CLI_Command extends WP_CLI_Command {

	/*
	 *  Clear all of the caches for memory management
	 */
	protected function stop_the_insanity() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

		if ( !is_object( $wp_object_cache ) )
			return;

		$wp_object_cache->group_ops = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();

		if ( is_callable( $wp_object_cache, '__remoteset' ) )
			$wp_object_cache->__remoteset(); // important
	}

	/**
	 * Disable term counting so that terms are not all recounted after every term operation
	 */
	protected function start_bulk_operation() {
		// Disable term count updates for speed
		wp_defer_term_counting( true );
	}

	/**
	 * Re-enable Term counting and trigger a term counting operation to update all term counts
	 */
	protected function end_bulk_operation() {
		// This will also trigger a term count.
		wp_defer_term_counting( false );
	}
}
