<?php
class Publishthis_Log {

	/**
	 *
	 *
	 * @desc Add a log entry
	 * @param unknown $message Log Message
	 * @param unknown $level   Used backward compatibility
	 */
	function addWithLevel( $message, $level ) {
		global $publishthis;

		$localMessages = get_transient( "pt_local_messages" );

		if ( ! is_array( $localMessages ) ) {
			$localMessages = array ();
		}

		if ( count( $localMessages ) > 30 ) {
			// keep it at a limited size
			array_shift( $localMessages );
		}

		if ( $publishthis->debug() ) {

			// we only log messages to local messages if we are debugging
			array_unshift( $localMessages, current_time( 'mysql' ) . "::" . $message );
			set_transient( "pt_local_messages", $localMessages, 60 * 60 * 2 );
			return;
		}

		if ( $publishthis->error() ) {
			return;
		}

	}

	/**
	 *
	 *
	 * @desc Add a log entry.
	 */
	function add( $message ) {
		$this->addWithLevel( $message, "1" );
	}
}
