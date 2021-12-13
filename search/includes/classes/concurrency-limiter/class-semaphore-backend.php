<?php

namespace Automattic\VIP\Search\ConcurrencyLimiter;

require_once __DIR__ . '/backendinterface.php';

/**
 * Semaphore_Backend is more reliable than APCu_Backend (if the process gets killed, all the acquired locks are automatically released).
 * However, once the semaphore is created, it is impossible to change its `max_acquire` value (in other words, it will not be possible
 * to change the number of allowed connections).
 */
class Semaphore_Backend implements BackendInterface {
	/** @var int */
	private static $instances = 0;

	/** @var resource|false|\SysvSemaphore */
	private $sem;

	/** @var int */
	private $increments = 0;

	public function __destruct() {
		if ( $this->sem && $this->increments > 0 ) {
			while ( $this->increments > 0 ) {
				sem_release( $this->sem );
				--$this->increments;
			}
		}

		--self::$instances;
		if ( defined( 'WP_TESTS_DOMAIN' ) && $this->sem && 0 === self::$instances ) {
			sem_remove( $this->sem );
		}
	}

	public function initialize( int $limit, int $ttl ): void {
		$this->sem = sem_get( ftok( __FILE__, 'e' ), $limit, 0600 );
		++self::$instances;
	}

	public static function is_supported(): bool {
		return function_exists( 'sem_get' );
	}

	public function inc_value(): bool {
		$result = false;
		if ( false !== $this->sem ) {
			$result = sem_acquire( $this->sem, true );
			if ( $result ) {
				++$this->increments;
			}
		}

		return $result;
	}

	public function dec_value(): void {
		if ( false !== $this->sem && $this->increments > 0 ) {
			$result = sem_release( $this->sem );
			if ( $result ) {
				--$this->increments;
			}
		}
	}
}
