<?php
/**
 * Concurrency locks
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

/**
 * Lock class
 */
class Lock {
	/**
	 * Set a lock and limit how many concurrent jobs are permitted
	 *
	 * @param string $lock  Lock name.
	 * @param int    $limit Concurrency limit.
	 * @param int    $timeout Timeout in seconds.
	 * @return bool
	 */
	public static function check_lock( $lock, $limit = null, $timeout = null ) {
		// Timeout, should a process die before its lock is freed.
		if ( ! is_numeric( $timeout ) ) {
			$timeout = LOCK_DEFAULT_TIMEOUT_IN_MINUTES * \MINUTE_IN_SECONDS;
		}

		// Check for, and recover from, deadlock.
		if ( self::get_lock_timestamp( $lock ) < time() - $timeout ) {
			self::reset_lock( $lock );
			return true;
		}

		// Default limit for concurrent events.
		if ( ! is_numeric( $limit ) ) {
			$limit = LOCK_DEFAULT_LIMIT;
		}

		// Check if process can run.
		if ( self::get_lock_value( $lock ) >= $limit ) {
			return false;
		} else {
			wp_cache_incr( self::get_key( $lock ) );
			return true;
		}
	}

	/**
	 * When event completes, allow another
	 *
	 * @param string $lock Lock name.
	 * @param int    $expires Lock expiration timestamp.
	 * @return bool
	 */
	public static function free_lock( $lock, $expires = 0 ) {
		if ( self::get_lock_value( $lock ) > 1 ) {
			wp_cache_decr( self::get_key( $lock ) );
		} else {
			wp_cache_set( self::get_key( $lock ), 0, null, $expires );
		}

		wp_cache_set( self::get_key( $lock, 'timestamp' ), time(), null, $expires );

		return true;
	}

	/**
	 * Build cache key
	 *
	 * @param string $lock Lock name.
	 * @param string $type Key type, either lock or timestamp.
	 * @return string|bool
	 */
	private static function get_key( $lock, $type = 'lock' ) {
		switch ( $type ) {
			case 'lock':
				return "a8ccc_lock_{$lock}";
				break;

			case 'timestamp':
				return "a8ccc_lock_ts_{$lock}";
				break;
		}

		return false;
	}

	/**
	 * Ensure lock entries are initially set
	 *
	 * @param string $lock Lock name.
	 * @param int    $expires Lock expiration timestamp.
	 * @return null
	 */
	public static function prime_lock( $lock, $expires = 0 ) {
		wp_cache_add( self::get_key( $lock ), 0, null, $expires );
		wp_cache_add( self::get_key( $lock, 'timestamp' ), time(), null, $expires );

		return null;
	}

	/**
	 * Retrieve a lock from cache
	 *
	 * @param string $lock Lock name.
	 * @return int
	 */
	public static function get_lock_value( $lock ) {
		return (int) wp_cache_get( self::get_key( $lock ), null, true );
	}

	/**
	 * Retrieve a lock's timestamp
	 *
	 * @param string $lock Lock name.
	 * @return int
	 */
	public static function get_lock_timestamp( $lock ) {
		return (int) wp_cache_get( self::get_key( $lock, 'timestamp' ), null, true );
	}

	/**
	 * Clear a lock's current values, in order to free it
	 *
	 * @param string $lock Lock name.
	 * @param int    $expires Lock expiration timestamp.
	 * @return bool
	 */
	public static function reset_lock( $lock, $expires = 0 ) {
		wp_cache_set( self::get_key( $lock ), 0, null, $expires );
		wp_cache_set( self::get_key( $lock, 'timestamp' ), time(), null, $expires );

		return true;
	}
}
