<?php

class VIP_Suspend_Cache_Invalidation {

	private $was_suspended = false;

	private $limit = 2000; //That's what used to work on WPCOM

	public function __construct() {
		// Disable {$taxonomy}_relationships cache invalidation
		// when saving categories, as some have a very large number of posts,
		// which results in N serial wp_cache_delete() calls
		add_action( 'edited_term_taxonomy', array( $this, 'edited_term_taxonomy_action' ), 10, 2 );

		// Re-enable cache invalidation once the cache clearing code has been bypassed
		add_action( 'edit_term', array( $this, 'edit_term_action' ) );

		add_action( 'wpcom_vip_clean_tax_relations_cache', array( $this, 'cron_action' ), 10, 2 );
	}

	/**
	 * WP Cron even for purging object cache in batches
	 */
	public function cron_action( $tt_id, $taxonomy ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$objects = $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $tt_id ) );

		$tax_object = get_taxonomy( $taxonomy );

		foreach ( $tax_object->object_type as $object_type ) {
			clean_object_term_cache( $objects, $object_type );
		}
	}

	/**
	 * Used to suspend cache invalidation, if a term is being edited
	 */
	public function edited_term_taxonomy_action( $tt_id, $taxonomy ) {
		
		global $wpdb, $_wp_suspend_cache_invalidation;

		$this->was_suspended = $_wp_suspend_cache_invalidation;

		// If cache is already disabled, then we don't need to do so again
		if ( $this->was_suspended ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$objects = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $tt_id ) );

		//no action needed if there is less that $limimt number of objects to purge
		if ( intval( $objects ) < $this->limit ) {
			return;
		}

		// Make sure we're in `wp_update_term` - the only place we want this to happen
		$backtrace = wp_debug_backtrace_summary( null, null, false );   // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary

		if ( ! in_array( 'wp_update_term', $backtrace ) ) {
			return;
		}

		wp_suspend_cache_invalidation( true );

		if ( false === apply_filters( 'wpcom_vip_disable_term_cache_invalidation', false, $tt_id, $taxonomy ) ) {
			//schedule WP Cron event for purging the cache
			wp_schedule_single_event( time(), 'wpcom_vip_clean_tax_relations_cache', array( $tt_id, $taxonomy ) );
		}

	}

	/**
	 * Restores cache invalidation, after the slow term relationship cache invalidation
	 * has been skipped
	 */
	public function edit_term_action() {

		// `edit_term` is only called from inside `wp_update_term`, so the backtrace
		// check is not required
		//let's restore the cache invalidation to previous value
		wp_suspend_cache_invalidation( $this->was_suspended );
	}
}

return new VIP_Suspend_Cache_Invalidation();
