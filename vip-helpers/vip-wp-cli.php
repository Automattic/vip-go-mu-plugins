<?php // phpcs:disable WordPressVIPMinimum.Classes.RestrictedExtendClasses.wp_cli

class WPCOM_VIP_CLI_Command extends WP_CLI_Command {

	/**
	 *  Clear all of the caches for memory management
	 *
	 * @deprecated Use vip_inmemory_cleanup() instead
	 */
	protected function stop_the_insanity() {
		_deprecated_function( __METHOD__, '2.0.0', 'vip_inmemory_cleanup' );
		$this->vip_inmemory_cleanup();
	}

	/**
	 * Clear in-memory local object cache (global $wp_object_cache) without affecting memcache
	 * and reset in-memory database query log.
	 */
	protected function vip_inmemory_cleanup() {
		vip_reset_db_query_log();
		vip_reset_local_object_cache();
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
