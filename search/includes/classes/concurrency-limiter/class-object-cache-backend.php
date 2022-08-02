<?php

namespace Automattic\VIP\Search\ConcurrencyLimiter;

use function Automattic\VIP\Logstash\log2logstash;

require_once __DIR__ . '/backendinterface.php';

class Object_Cache_Backend implements BackendInterface {
	const KEY_NAME   = 'vip_search_concurrent_requests_count';
	const GROUP_NAME = 'vip_search';

	private int $limit;
	private int $ttl;
	private int $increments = 0;

	public function __destruct() {
		if ( $this->increments > 0 ) {
			$value = wp_cache_decr( self::KEY_NAME, $this->increments, self::GROUP_NAME );
			if ( $value < 0 ) {
				$this->reset();
			}
		}
	}

	public function initialize( int $limit, int $ttl ): void {
		$this->limit = $limit;
		$this->ttl   = $ttl;

		$found = false;
		$value = wp_cache_get( self::KEY_NAME, self::GROUP_NAME, false, $found );
		if ( ! $found ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_add( self::KEY_NAME, 0, self::GROUP_NAME, $this->ttl );
		} elseif ( ! is_int( $value ) ) {
			$this->reset();
		}
	}

	public static function is_supported(): bool {
		return true;
	}

	public function inc_value(): bool {
		for ( $attempt = 1; $attempt <= 3; ++$attempt ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_add( self::KEY_NAME, 0, self::GROUP_NAME, $this->ttl );
			$value = wp_cache_incr( self::KEY_NAME, 1, self::GROUP_NAME );
			if ( false !== $value ) {
				++$this->increments;

				if ( $value > $this->limit ) {
					log2logstash( [
						'severity' => 'warning',
						'feature'  => 'search_concurrency_limiter',
						'message'  => 'Reached concurrency limit',
						'extra'    => [
							'counter' => $value,
							'attempt' => $attempt,
						],
					] );
				}
		
				return $value <= $this->limit;
			}
		}

		log2logstash( [
			'severity' => 'warning',
			'feature'  => 'search_concurrency_limiter',
			'message'  => 'Failed to increment the counter',
			'extra'    => [
				'counter'        => wp_cache_get( self::KEY_NAME, self::GROUP_NAME ),
				'counter_remote' => wp_cache_get( self::KEY_NAME, self::GROUP_NAME, true ),
			],
		] );

		return true;
	}

	public function dec_value(): void {
		if ( $this->increments > 0 ) {
			$result = wp_cache_decr( self::KEY_NAME, 1, self::GROUP_NAME );
			if ( false !== $result ) {
				--$this->increments;
				if ( $result < 0 ) {
					$this->reset();
				}
			} else {
				log2logstash( [
					'severity' => 'warning',
					'feature'  => 'search_concurrency_limiter',
					'message'  => 'Failed to decrement the counter',
					'extra'    => [
						'counter'        => wp_cache_get( self::KEY_NAME, self::GROUP_NAME ),
						'counter_remote' => wp_cache_get( self::KEY_NAME, self::GROUP_NAME, true ),
					],
				] );
			}
		}
	}

	public function get_value(): int {
		return wp_cache_get( self::KEY_NAME, self::GROUP_NAME, true ) ?: 0;
	}

	private function reset(): void {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_set( self::KEY_NAME, 0, self::GROUP_NAME, $this->ttl );
	}
}
