<?php

namespace Automattic\VIP\Search\ConcurrencyLimiter;

require_once __DIR__ . '/backendinterface.php';

class APCu_Backend implements BackendInterface {
	const KEY_NAME = 'vip_es_request_count';

	/** @var int */
	private $limit;

	/** @var int */
	private $ttl;

	private $increments = 0;

	public function __destruct() {
		if ( $this->increments > 0 ) {
			apcu_dec( self::KEY_NAME, $this->increments, null, $this->ttl );
		}
	}

	public function initialize( int $limit, int $ttl ): void {
		$this->limit = $limit;
		$this->ttl   = $ttl;

		$value = apcu_entry( self::KEY_NAME, '__return_zero', $this->ttl );
		if ( ! is_int( $value ) ) {
			apcu_cas( self::KEY_NAME, $value, 0 );
		}
	}

	public static function is_supported(): bool {
		return function_exists( 'apcu_enabled' ) && apcu_enabled();
	}

	public function inc_value(): bool {
		$success = null;
		$value   = apcu_inc( self::KEY_NAME, 1, $success, $this->ttl );
		if ( $success ) {
			++$this->increments;
			return $value <= $this->limit;
		}

		return false;
	}

	public function dec_value(): void {
		if ( $this->increments > 0 ) {
			$success = null;
			apcu_dec( self::KEY_NAME, 1, $success, $this->ttl );
			if ( $success ) {
				--$this->increments;
			}
		}
	}
}
