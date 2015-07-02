<?php
/**
 * Post Meta Logging Addon for CampTix
 *
 * This addon logs entries about tickets, attendees, coupons, etc., 
 * into the postmeta table. If you have a lot of data and/or use a 
 * persistent mem-based object caching plugin, you should use database 
 * of file-based logging instead.
 */
class CampTix_Addon_Logging_Meta extends CampTix_Addon {

	/**
	 * Runs during camptix_init, @see CampTix_Addon
	 */
	function camptix_init() {
		add_action( 'camptix_log_raw', array( $this, 'camptix_log_raw' ), 10, 4 );
		add_action( 'camptix_add_meta_boxes', array( $this, 'camptix_add_meta_boxes' ) );
	}

	function camptix_log_raw( $message, $post_id, $data, $section ) {
		$entry = array(
			'url' => home_url(),
			'timestamp' => time(),
			'message' => $message,
			'data' => $data,
			'module' => $section,
		);

		if ( $post_id ) {
			$entry['post_id'] = $post_id;
			$entry['edit_post_link'] = esc_url_raw( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
			$log = get_post_meta( $post_id, 'tix_log', true );
			if ( is_array( $log ) )
				$log[] = $entry;
			else
				$log = array( $entry );

			update_post_meta( $post_id, 'tix_log', $log );
		}
	}

	function camptix_add_meta_boxes() {
		$post_types = array(
			'tix_attendee',
			'tix_ticket',
			'tix_coupon',
			'tix_email',
		);

		foreach ( $post_types as $post_type )
			add_meta_box( 'tix_log', 'CampTix Meta Log', array( $this, 'metabox_log' ), $post_type, 'normal' );
	}

	/**
	 * CampTix Log metabox for various post types.
	 */
	function metabox_log() {
		global $post, $camptix;
		$rows = array();

		// The log is stored in an array of array( 'timestamp' => x, 'message' => y ) format.
		$log = get_post_meta( $post->ID, 'tix_log', true );
		if ( !$log ) $log = array();

		// Add entries as rows.
		foreach ( $log as $entry )
			$rows[] = array( date( 'Y-m-d H:i:s', intval( $entry['timestamp'] ) ), esc_html( $entry['message'] ) );

		if ( count( $rows ) < 1 )
			$rows[] = array( 'No log entries yet.', '' );

		$camptix->table( $rows, 'tix-log-table' );
	}
}

// Register this class as a CampTix Addon.
camptix_register_addon( 'CampTix_Addon_Logging_Meta' );