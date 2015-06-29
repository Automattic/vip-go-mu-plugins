<?php
//Backcompat for pre this function making it into WordPress
if ( ! function_exists( 'wp_debug_backtrace_summary' ) ) {
	/**
	 * Return a comma separated string of functions that have been called to get to the current point in code.
	 * @link http://core.trac.wordpress.org/ticket/19589
	 * @since 3.4
	 *
	 * @param string $ignore_class A class to ignore all function calls within - useful when you want to just give info about the callee
	 * @param string $skip_frames A number of stack frames to skip - useful for unwinding back to the source of the issue
	 * @param bool $pretty Whether or not you want a comma separated string or raw array returned
	 * @return string|array Either a string containing a reversed comma separated trace or an array of individual calls.
	 */
	function wp_debug_backtrace_summary( $ignore_class = null, $skip_frames = 0, $pretty = true ) {
		$trace  = debug_backtrace( false );
		$caller = array();
		$check_class = ! is_null( $ignore_class );
		$skip_frames++; // skip this function
	
		foreach ( $trace as $call ) {
			if ( $skip_frames > 0 ) {
				$skip_frames--;
			} elseif ( isset( $call['class'] ) ) {
				if ( $check_class && $ignore_class == $call['class'] )
					continue; // Filter out calls
	
				$caller[] = "{$call['class']}{$call['type']}{$call['function']}";
			} else {
				if ( in_array( $call['function'], array( 'do_action', 'apply_filters' ) ) ) {
					$caller[] = "{$call['function']}('{$call['args'][0]}')";
				} elseif ( in_array( $call['function'], array( 'include', 'include_once', 'require', 'require_once' ) ) ) {
					$caller[] = $call['function'] . "('" . str_replace( array( WP_CONTENT_DIR, ABSPATH ) , '', $call['args'][0] ) . "')";
				} else {
					$caller[] = $call['function'];
				}
			}
		}
		if ( $pretty )
			return join( ', ', array_reverse( $caller ) );
		else
			return $caller;
	}
}
?>