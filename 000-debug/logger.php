<?php

/**
 * logger.php
 *
 * This file contains helpful debugging functions for use on sandboxes.
 *
 * This file was stolen from WP.com and renamed to `logger.php`
 */

/**
 * l() -- sweet error logging
 *
 * l($something_to_log); // error_log(print_r($something_to_log, true));
 * l(compact('v1','v2'); // log several variables with labels
 * l($thing5, $thing10); // log two things
 * l();                  // log the file:line
 * l(null, $stuff, $ba); // log the file:line, then log two things.
 *
 * The first call of l() will print an extra line containing a random ID & PID
 * and the script name or URL. The ID prefixes every l() log entry thereafter.
 * The extra line and ID will help you to indentify and correlate log entries.
 *
 * Example:
 *  wpsh> l('yo')
 *  wpsh> l('dude')
 * /tmp/php-errors:
 *  [21-Jun-2012 14:45:13] 1566-32201 => /home/wpcom/public_html/bin/wpshell/wpshell.php
 *  [21-Jun-2012 14:45:13] 1566-32201 yo
 *  [21-Jun-2012 14:50:23] 1566-32201 dude
 *
 * l() returns its input so you can safely wrap most kinds of expressions to log them.
 * l($arg1, $arg2) will call l($arg1) and l($arg2) and then return $arg1.
 *
 * A null argument will log the file and line number of the l() call.
 */
function l( $stuff = null, ...$rest ) {
	// Do nothing on production hosts.
	if ( true === WPCOM_IS_VIP_ENV
		&& ( ! defined( 'WPCOM_SANDBOXED' ) || ! WPCOM_SANDBOXED ) ) {
		return $stuff;
	}
	static $pageload;
	// Call l() on each argument
	if ( count( $rest ) > 0 ) {
		l( $stuff );
		foreach ( $rest as $arg ) {
			l( $arg );
		}
		return $stuff;
	}
	if ( ! isset( $pageload ) ) {
		$pageload = substr( md5( wp_rand() ), 0, 4 );
		if ( ! empty( $_SERVER['argv'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$hint = implode( ' ', $_SERVER['argv'] );
		} elseif ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$hint = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		} else {
			$hint = php_sapi_name();
		}
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[%s-%s => %s]', $pageload, getmypid(), $hint ) );
		// phpcs:enable
	}
	$pid = $pageload . '-' . getmypid();
	if ( is_null( $stuff ) ) {
		// Log the file and line number
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		while ( isset( $backtrace[1]['function'] ) && __FUNCTION__ == $backtrace[1]['function'] ) {
			array_shift( $backtrace );
		}
		$log = sprintf( '%s line %d', $backtrace[0]['file'], $backtrace[0]['line'] );
	} elseif ( is_bool( $stuff ) ) {
		$log = $stuff ? 'TRUE' : 'FALSE';
	} elseif ( is_scalar( $stuff ) ) {
		// Strings and numbers can be logged exactly
		$log = $stuff;
	} else {
		// Are we in an output buffer handler?
		// If so, print_r($stuff, true) is fatal so we must avoid that.
		// This is not as slow as it looks: <1ms when !$in_ob_handler.
		// Using json_encode_pretty() all the time is much slower.
		do {
			$in_ob_handler = false;
			$ob_status     = ob_get_status( true );
			if ( ! $ob_status ) {
				break;
			}
			foreach ( $ob_status as $ob ) {
				$obs[] = $ob['name'];
			}
			// This is not perfect: anonymous handlers appear as default.
			if ( array( 'default output handler' ) == $obs ) {
				break;
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
			foreach ( $backtrace as $level ) {
				$caller = '';
				if ( isset( $level['class'] ) ) {
					$caller = $level['class'] . '::';
				}
				$caller .= $level['function'];
				$bts[]   = $caller;
			}
			if ( array_intersect( $obs, $bts ) ) {
				$in_ob_handler = true;
			}
		} while ( false );
		if ( $in_ob_handler ) {
			$log = l_json_encode_pretty( $stuff );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$log = print_r( $stuff, true );
		}
	}
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( sprintf( '[%s] %s', $pid, $log ) );

	return $stuff;
}

// Log only once (suppresses logging on subsequent calls from the same file+line)
function lo( $stuff, ...$rest ) {
	static $callers = array();
	$backtrace      = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );       // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
	$caller         = md5( $backtrace[0]['file'] . $backtrace[0]['line'] );
	if ( isset( $callers[ $caller ] ) ) {
		return $stuff;
	}
	$callers[ $caller ] = true;
	$args               = array_merge( [ $stuff ], $rest );
	return call_user_func_array( 'l', $args );
}

/**
 * Pretty print for JSON
 * 
 * @param mixed $data       Variable to encode as JSON.
 * @return string|false     The JSON-encoded string, or false if it cannot be encoded.
 */
function l_json_encode_pretty( $data ) {
	return wp_json_encode( $data, JSON_PRETTY_PRINT );
}

/**
 * A timer. Call once to start, call again to stop. Returns a float.
 * Calling vip_timer($name) with different names permits simultaneous timers.
 *
 * vip_timer('stuff');
 * do_stuff();
 * $elapsed = vip_timer('stuff');
 */
function vip_timer( $name = '' ) {
	static $times = array();
	if ( ! array_key_exists( $name, $times ) ) {
		$times[ $name ] = microtime( true );
		return null;
	}
	$elapsed = microtime( true ) - $times[ $name ];
	unset( $times[ $name ] );
	return $elapsed;
}

/**
 * A wrapper for vip_timer() which also logs the result with l().
 * Each log entry begins with a tag common to that pageload.
 * You can save a keystroke by calling vip_timer() then vip_timer_l().
 *
 * vip_timer($name);
 * do_stuff();
 * vip_timer_l($name);
 */
function vip_timer_l( $name = '' ) {
	$elapsed = vip_timer( $name );
	if ( null !== $elapsed ) {
		l( sprintf( "%9.6f vip_timer('%s')", $elapsed, $name ) );
	}
	return $elapsed;
}

/**
 * A persistent timer. After the initial call, each call to t()
 * will log the file:line and time elapsed since the initial call.
 */
function t() {
	static $start;
	$now = microtime( true );
	if ( ! isset( $start ) ) {
		$start = $now;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
	$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	while ( isset( $backtrace[1]['function'] ) && __FUNCTION__ == $backtrace[1]['function'] ) {
		array_shift( $backtrace );
	}

	$file    = $backtrace[0]['file'];
	$line    = $backtrace[0]['line'];
	$format  = 't() => %9.6f at %s line %d';
	$elapsed = $now - $start;
	l( sprintf( $format, $elapsed, $file, $line ) );
}
