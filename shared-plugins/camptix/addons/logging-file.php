<?php
/**
 * Logging to a file Addon for CampTix
 *
 * This addon logs entries about tickets, attendees, coupons, etc., 
 * into a file in plain text. The file is /tmp/camptix.log by default
 * but can easily be changed with a filter.
 */
class CampTix_Addon_Logging_File extends CampTix_Addon {

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
			$this->_logfile = fopen( apply_filters( 'camptix_logfile_path', '/tmp/camptix.log' ), 'a+' );

		// If there was an error opening the log file, don't do anything else.
		if ( ! $this->_logfile )
			return;

		$url = parse_url( home_url() );
		$message = sprintf( '[%s] %s/%s: %s', date( 'Y-m-d H:i:s' ), $url['host'], $section . ( $post_id ? '/' . $post_id : '' ), $message );
		fwrite( $this->_logfile, $message . PHP_EOL );
	}

	function __destruct() {
		if ( $this->_logfile )
			fclose( $this->_logfile );
	}
}

// Register this class as a CampTix Addon.
camptix_register_addon( 'CampTix_Addon_Logging_File' );