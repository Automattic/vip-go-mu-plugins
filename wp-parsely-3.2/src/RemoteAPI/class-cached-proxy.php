<?php
/**
 * Parsely Related REST API Caching Decorator
 *
 * @package Parsely
 * @since 3.2.0
 */

declare(strict_types=1);

namespace Parsely\RemoteAPI;

/**
 * Caching Decorator for the remote /related endpoint.
 */
class Cached_Proxy implements Proxy {
	private const CACHE_GROUP      = 'wp-parsely';
	private const OBJECT_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * The Proxy instance this will cache.
	 *
	 * @var Proxy
	 */
	private $proxy;

	/**
	 * A wrapped object that's compatible with the Cache Interface.
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param Proxy $proxy The Proxy object to cache.
	 * @param Cache $cache An object cache instance.
	 */
	public function __construct( Proxy $proxy, Cache $cache ) {
		$this->proxy = $proxy;
		$this->cache = $cache;
	}

	/**
	 * Implements caching for the proxy interface.
	 *
	 * @param array<string, mixed> $query The query arguments to send to the remote API.
	 * @return array<string, mixed>|false The response from the remote API, or false if the response is empty.
	 */
	public function get_items( array $query ) {
		$cache_key = 'parsely_api_' . wp_hash( (string) wp_json_encode( $this->proxy ) ) . '_' . wp_hash( (string) wp_json_encode( $query ) );
		$items     = $this->cache->get( $cache_key, self::CACHE_GROUP );

		if ( false === $items ) {
			$items = $this->proxy->get_items( $query );
			$this->cache->set( $cache_key, $items, self::CACHE_GROUP, self::OBJECT_CACHE_TTL );
		}

		return $items;
	}
}
