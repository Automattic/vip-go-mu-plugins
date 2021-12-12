<?php

namespace Automattic\VIP\Search\ConcurrencyLimiter;

require_once __DIR__ . '/backendinterface.php';

class Object_Cache_Backend implements BackendInterface {
	const KEY_NAME   = 'vip_es_request_count';
	const GROUP_NAME = 'vip_es';

	/** @var int */
	private $limit;

	private $increments = 0;

	public function __destruct() {
		if ( $this->increments > 0 ) {
			wp_cache_decr( self::KEY_NAME, $this->increments, self::GROUP_NAME );
		}
	}

	public function initialize( int $limit, int $ttl ): void {
		$this->limit = $limit;

		$found = false;
		$value = wp_cache_get( self::KEY_NAME, self::GROUP_NAME, false, $found );
		if ( ! $found || ! is_int( $value ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( self::KEY_NAME, 0, self::GROUP_NAME, $ttl );
		}
	}

	public static function is_supported(): bool {
		return true;
	}

	public function inc_value(): bool {
		$value = wp_cache_incr( self::KEY_NAME, 1, self::GROUP_NAME );
		if ( false !== $value ) {
			++$this->increments;
			return $value <= $this->limit;
		}

		return false;
	}

	public function dec_value(): void {
		if ( $this->increments > 0 ) {
			$result = wp_cache_decr( self::KEY_NAME, 1, self::GROUP_NAME );
			if ( false !== $result ) {
				--$this->increments;
			}
		}
	}
}
