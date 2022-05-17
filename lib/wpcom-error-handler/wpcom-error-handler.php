<?php
/**
 * Should be loaded via `wp-config.php`, before including `wp-settings.php`
 */
if ( ! defined( 'ABSPATH' ) || defined( 'WPINC' ) ) {
	return;
}

function wpcom_error_shutdown() {
	$last = error_get_last();
	if ( ! $last ) {
		return;
	}

	switch ( $last['type'] ) {
		case E_CORE_ERROR: // we may not be able to capture this one
		case E_COMPILE_ERROR: // or this one
		case E_PARSE: // we can't actually capture this one
		case E_ERROR:
		case E_USER_ERROR:
		case E_RECOVERABLE_ERROR:
			wpcom_custom_error_handler( false, $last['type'], $last['message'], $last['file'], $last['line'] );
			break;
	}
}

/**
 * @param int    $type      The level of the error raised (prior to PHP 8, $type is 0 if the error has been silenced with @)
 * @param string $message   Error message
 * @param string $file      Filename that the error was raised in
 * @param int    $line      Line number where the error was raised
 * @return void
 */
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
		// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
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
			$path  = isset( $call['file'] ) ? str_replace( ABSPATH, '', $call['file'] ) : '';
			$path .= isset( $call['line'] ) ? ':' . $call['line'] : '';
		}

		if ( isset( $call['class'] ) ) {
			$call_type = $call['type'] ?? '???';
			$path     .= " {$call['class']}{$call_type}{$call['function']}()";
		} elseif ( in_array( $call['function'], array( 'do_action', 'apply_filters' ) ) ) {
			if ( is_object( $call['args'][0] ) && ! method_exists( $call['args'][0], '__toString' ) ) {
				$path .= " {$call['function']}(Object)";
			} elseif ( is_array( $call['args'][0] ) ) {
				$path .= " {$call['function']}(Array)";
			} else {
				$path .= " {$call['function']}('{$call['args'][0]}')";
			}
		} elseif ( in_array( $call['function'], array( 'include', 'include_once', 'require', 'require_once' ) ) ) {
			$file  = 0 == $bt_key ? $last_error_file : $call['args'][0];
			$path .= " {$call['function']}('" . str_replace( ABSPATH, '', $file ) . "')";
		} else {
			$path .= " {$call['function']}()";
		}

		$call_path[] = trim( $path );
	}

	return implode( ', ', $call_path );
}

/**
 * Shared Error Handler run as a Custom Error Handler and at Shutdown as an error handler of last resort.
 * When we run at shutdown we must not die as then the pretty printing of the Error doesn't happen which is lame sauce.
 *
 * @param bool   $whether_i_may_die     true if the function is called from the Error Handler and not from the shutdown handler
 * @param int    $type                  The level of the error raised (prior to PHP 8, $type is 0 if the error has been silenced with @)
 * @param string $message               Error message
 * @param string $file                  Filename that the error was raised in
 * @param int    $line                  Line number where the error was raised
 * @return bool                         Whether not to call the normal error handling (the return value is ignored by the callers and thus has no effect)
 */
function wpcom_custom_error_handler( $whether_i_may_die, $type, $message, $file, $line ) {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
	if ( ! is_int( $type ) || ! ( $type & error_reporting() ) ) {
		return true;
	}

	// Capture MemcachePool errors, throw a warning, and provide more debugging data.
	if (
		E_NOTICE === $type &&
		( '/var/www/wp-content/object-cache-stable.php' === $file || '/var/www/wp-content/object-cache-next.php' === $file )
	) {
		$type = E_WARNING;
		foreach ( debug_backtrace() as $trace => $value ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			if ( 'wpcom_error_handler' === $value['function'] && str_starts_with( $value['args'][1], 'MemcachePool::' ) ) {
				$message .= sprintf(
					' (Key: %s, Group: %s, Data Size: %s)',
					$value['args'][4]['id'],
					empty( $value['args'][4]['group'] ) ? 'default' : $value['args'][4]['group'],
					strlen( $value['args'][4]['data'] )
				);
			}
		}
	}

	$die = false;
	switch ( $type ) {
		case E_CORE_ERROR: // we may not be able to capture this one
			$string = 'Core error';
			$die    = true;
			break;
		case E_COMPILE_ERROR: // or this one
			$string = 'Compile error';
			$die    = true;
			break;
		case E_PARSE: // we can't actually capture this one
			$string = 'Parse error';
			$die    = true;
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$string = 'Fatal error';
			$die    = true;
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$string = 'Warning';
			break;
		case E_NOTICE:
		case E_USER_NOTICE:
			$string = 'Notice';
			break;
		case E_STRICT:
			$string = 'Strict Standards';
			break;
		case E_RECOVERABLE_ERROR:
			$string = 'Catchable fatal error';
			$die    = true;
			break;
		case E_DEPRECATED:
		case E_USER_DEPRECATED:
			$string = 'Deprecated';
			break;
		case 0:
			return true;
	}

	// @ error suppression
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- false positive
	if ( 0 === error_reporting() ) {
		$string = '[Suppressed] ' . $string;
	}

	$backtrace = wpcom_get_error_backtrace( $file, $type );

	if ( ! empty( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- this is OK, the variable will be escaped later
		$source = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	} else {
		$source = '$ ' . join( ' ', empty( $GLOBALS['argv'] ) || ! is_array( $GLOBALS['argv'] ) ? [] : $GLOBALS['argv'] );
	}

	$display_errors = ini_get( 'display_errors' );

	if ( $display_errors ) {
		if ( ini_get( 'html_errors' ) ) {
			$display_errors_format = "<br />\n<b>%s</b>: %s in <b>%s</b> on line <b>%d</b><br />\n[%s]<br />\n[%s]<br /><br />\n";
		} else {
			$display_errors_format = "\n%s: %s in %s on line %d [%s] [%s]\n";
		}

		if ( 'stderr' === $display_errors && defined( 'STDERR' ) && is_resource( STDERR ) ) {
			fprintf( STDERR, $display_errors_format, $string, $message, $file, $line, htmlspecialchars( $source ), htmlspecialchars( $backtrace ) );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf( $display_errors_format, $string, $message, $file, $line, htmlspecialchars( $source ), htmlspecialchars( $backtrace ) );
		}
	}

	if ( ini_get( 'log_errors' ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '%s: %s in %s on line %d [%s] [%s]', $string, $message, $file, $line, $source, $backtrace ) );
	}

	// When we run at shutdown we must not die as then the pretty printing of the Error doesn't happen which is lame sauce.
	if ( $die && $whether_i_may_die ) {
		die( 1 );
	}

	return true;
}

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- used only to check for a substring
$php_self = $_SERVER['PHP_SELF'] ?? '';
if ( false === stripos( $php_self, 'phpunit' ) ) {
	// phpcs:ignore WordPress.PHP.IniSet.Risky
	ini_set( 'a8c.enable_backtrace_on_error', 1 );
	register_shutdown_function( 'wpcom_error_shutdown' );
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
	set_error_handler( 'wpcom_error_handler' );
}
