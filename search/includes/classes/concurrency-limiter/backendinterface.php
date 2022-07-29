<?php

namespace Automattic\VIP\Search\ConcurrencyLimiter;

interface BackendInterface {
	/**
	 * Initializes the backend.
	 *
	 * @param int $limit Number of concurrent connections
	 * @param int $ttl   TTL of the counter for the backends that support it.
	 * @return void
	 */
	public function initialize( int $limit, int $ttl ): void;

	/**
	 * Whether the backend is supported by the execution environment.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool;

	/**
	 * Increments the counter of the concurrent connections.
	 *
	 * @return bool True if the new value is withing the limit, false otherwise
	 */
	public function inc_value(): bool;

	/**
	 * Decrements the counter of the concurrent connections.
	 *
	 * @return void
	 */
	public function dec_value(): void;

	/**
	 * Gets the value of the counter
	 *
	 * @return integer
	 */
	public function get_value(): int;
}
