<?php
/**
 * Logging to a file Addon for CampTix
 *
 * This addon logs entries about tickets, attendees, coupons, etc., 
 * into a file in JSON format. The file is /tmp/camptix.json.log by default
 * but can easily be changed with a filter.
 */
class CampTix_Addon_Logging_File_JSON extends CampTix_Addon {

	private $_logfile = null;

	/**
	 * Runs during camptix_init, @see CampTix_Addon
	 */
	function camptix_init() {
		add_action( 'camptix_log_raw', array( $this, 'camptix_log_raw' ), 10, 4 );
	}

	function camptix_log_raw( $message, $post_id, $data, $section ) {
		// If the log file is not opened yet, open it for writing.
		if ( null === $this->_logfile )
			$this->_logfile = fopen( apply_filters( 'camptix_logfile_json_path', '/tmp/camptix.json.log' ), 'a+' );

		// If there was an error opening the log file, don't do anything else.
		if ( ! $this->_logfile )
			return;

		$entry = array(
			'url' => home_url(),
			'timestamp' => time(),
			'message' => $message,
			'data' => stripslashes_deep( $data ),
			'module' => $section,
		);

		if ( $post_id ) {
			$entry['post_id'] = $post_id;
			$entry['edit_post_link'] = esc_url_raw( add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
		}

		fwrite( $this->_logfile, json_encode( $entry ) . PHP_EOL );
	}

	function __destruct() {
		if ( $this->_logfile )
			fclose( $this->_logfile );
	}
}

// Register this class as a CampTix Addon.
camptix_register_addon( 'CampTix_Addon_Logging_File_JSON' );