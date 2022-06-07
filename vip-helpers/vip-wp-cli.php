<?php // phpcs:disable WordPressVIPMinimum.Classes.RestrictedExtendClasses.wp_cli

class WPCOM_VIP_CLI_Command extends WP_CLI_Command {

	/**
	 *  Clear all of the caches for memory management
	 */
	protected function stop_the_insanity() {
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
