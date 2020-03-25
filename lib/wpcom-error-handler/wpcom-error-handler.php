<?php
/**
 * Should be loaded via `wp-config.php`, before including `wp-settings.php`
 */
if ( ! defined( 'ABSPATH' ) || defined( 'WPINC' ) ) {
	return;
}

function wpcom_error_shutdown() {
	if ( ! $last = error_get_last() )
		return;

	switch ( $last['type'] ) {
		case E_CORE_ERROR : // we may not be able to capture this one
		case E_COMPILE_ERROR : // or this one
		case E_PARSE : // we can't actually capture this one
		case E_ERROR :
		case E_USER_ERROR :
		case E_RECOVERABLE_ERROR :
			wpcom_custom_error_handler( false, $last['type'], $last['message'], $last['file'], $last['line'] );
			break;
	}
}

function wpcom_error_handler( $type, $message, $file, $line ) {
	wpcom_custom_error_handler( true, $type, $message, $file, $line );
}

function wpcom_get_error_backtrace( $last_error_file, $last_error_type, $for_irc = false ) {
	global $a8c_debug_backtrace;

	if ( ! empty( $a8c_debug_backtrace ) ) {
		$backtrace = $a8c_debug_backtrace; // Fatal errors tracking from the a8c.so PHP module
	} elseif ( in_array( $last_error_type, array( E_ERROR, E_USER_ERROR ), 1 ) ) {
		return ''; // The standard debug backtrace is useless for Fatal Errors
	} else {
		$backtrace = debug_backtrace( 0 );
	}

	$call_path = array();
	foreach ( $backtrace as $bt_key => $call ) {
		if ( ! isset( $call['args'] ) ) {
			$call['args'] = array( '' );
		}

		if ( in_array( $call['function'], array( __FUNCTION__, 'wpcom_custom_error_handler', 'wpcom_error_handler', 'wpcom_error_shutdown' ) ) ) {
			continue;
		}

		$path = '';
		if ( ! $for_irc ) {
			$path = isset( $call['file'] ) ? str_replace( ABSPATH, '', $call['file'] ) : '';
			$path .= isset( $call['line'] ) ? ':' . $call['line'] : '';
		}

		if ( isset( $call['class'] ) ) {
			$call_type = $call['type'] ?? '???';
			$path .= " {$call['class']}{$call_type}{$call['function']}()";
		} elseif ( in_array( $call['function'], array( 'do_action', 'apply_filters' ) ) ) {
			if ( is_object( $call['args'][0] ) && ! method_exists( $call['args'][0], '__toString' ) ) {
				$path .= " {$call['function']}(Object)";
			} elseif ( is_array( $call['args'][0] ) ) {
				$path .= " {$call['function']}(Array)";
			} else {
				$path .= " {$call['function']}('{$call['args'][0]}')";
			}
		} elseif ( in_array( $call['function'], array( 'include', 'include_once', 'require', 'require_once' ) ) ) {
			$file = 0 == $bt_key ? $last_error_file : $call['args'][0];
			$path .= " {$call['function']}('" . str_replace( ABSPATH, '', $file ) . "')";
		} else {
			$path .= " {$call['function']}()";
		}

		$call_path[] = trim( $path );
	}

	return implode( ', ', $call_path );
}

/*
 * Shared Error Handler run as a Custom Error Handler and at Shutdown as an error handler of last resort.
 * When we run at shutdown we must not die as then the pretty printing of the Error doesn't happen which is lame sauce.
 */
function wpcom_custom_error_handler( $whether_i_may_die, $type, $message, $file, $line ) {
	if ( ! is_numeric( $type ) || ! ( $type & error_reporting() ) ) {
		return true;
	}

	$die = false;
	switch ( $type ) {
		case E_CORE_ERROR : // we may not be able to capture this one
			$string = 'Core error';
			$die = true;
			break;
		case E_COMPILE_ERROR : // or this one
			$string = 'Compile error';
			$die = true;
			break;
		case E_PARSE : // we can't actually capture this one
			$string = 'Parse error';
			$die = true;
			break;
		case E_ERROR :
		case E_USER_ERROR :
			$string = 'Fatal error';
			$die = true;
			break;
		case E_WARNING :
		case E_USER_WARNING :
			$string = 'Warning';
			break;
		case E_NOTICE :
		case E_USER_NOTICE :
			$string = 'Notice';
			break;
		case E_STRICT :
			$string = 'Strict Standards';
			break;
		case E_RECOVERABLE_ERROR :
			$string = 'Catchable fatal error';
			$die = true;
			break;
		case E_DEPRECATED :
		case E_USER_DEPRECATED :
			$string = 'Deprecated';
			break;
		case 0 :
			return true;
	}

	// @ error suppression
	if ( 0 == error_reporting() ) {
		$string = '[Suppressed] ' . $string;
	}

	$backtrace = wpcom_get_error_backtrace( $file, $type );

	if ( !empty( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
		$source = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	} else {
		$source = '$ ' . @join( ' ', $GLOBALS['argv'] );
	}

	$display_errors = ini_get( 'display_errors' );

	if ( $display_errors ) {
		if ( ini_get( 'html_errors' ) ) {
			$display_errors_format = "<br />\n<b>%s</b>: %s in <b>%s</b> on line <b>%d</b><br />\n[%s]<br />\n[%s]<br /><br />\n";
		} else {
			$display_errors_format = "\n%s: %s in %s on line %d [%s] [%s]\n";
		}

		if ( 'stderr' === $display_errors && defined( 'STDERR' ) && is_resource( STDERR ) ) {
			fwrite( STDERR, sprintf( $display_errors_format, $string, $message, $file, $line, htmlspecialchars( $source ), htmlspecialchars( $backtrace ) ) );
		} else {
			printf( $display_errors_format, $string, $message, $file, $line, htmlspecialchars( $source ), htmlspecialchars( $backtrace ) );
		}
	}

	if ( ini_get( 'log_errors' ) ) {
		error_log( sprintf( '%s: %s in %s on line %d [%s] [%s]', $string, $message, $file, $line, $source, $backtrace ) );
	}

	if ( $die ) {
		// When we run at shutdown we must not die as then the pretty printing of the Error doesn't happen which is lame sauce.
		if ( $whether_i_may_die ) {
			die( 1 );
		}
	}

	return true;
}

if ( false === stripos( $_SERVER[ 'PHP_SELF' ],'phpunit' ) ) {
	ini_set( 'a8c.enable_backtrace_on_error', 1 );
	register_shutdown_function( 'wpcom_error_shutdown' );
	set_error_handler( 'wpcom_error_handler' );
}
