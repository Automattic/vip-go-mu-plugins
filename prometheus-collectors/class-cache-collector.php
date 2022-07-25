<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;
use WP_Object_Cache;

class Cache_Collector implements CollectorInterface {
	private ?Counter $cache_hits_counter   = null;
	private ?Counter $cache_misses_counter = null;
	private ?Counter $operation_counter    = null;

	/**
	 * @global WP_Object_Cache $wpdb
	 */
	public function initialize( RegistryInterface $registry ): void {
		/** @var WP_Object_Cache $wp_object_cache */
		global $wp_object_cache;

		if ( property_exists( $wp_object_cache, 'cache_hits' ) ) {
			$this->cache_hits_counter = $registry->getOrRegisterCounter(
				'object_cache',
				'cache_hits_total',
				'Total number of cache hits',
				[ 'site_id' ]
			);

			$this->cache_misses_counter = $registry->getOrRegisterCounter(
				'object_cache',
				'cache_misses_total',
				'Total number of cache misses',
				[ 'site_id' ]
			);
		}

		if ( property_exists( $wp_object_cache, 'stats' ) && is_array( $wp_object_cache->stats ) ) {
			$registry->getOrRegisterCounter(
				'object_cache',
				'oprations_total',
				'Number of operations',
				[ 'site_id', 'operation' ]
			);
		}

		add_action( 'shutdown', [ $this, 'shutdown' ] );
	}

	public function collect_metrics(): void {
		/* Do nothing */
	}

	/**
	 * @global WP_Object_Cache $wp_object_cache
	 * @codeCoverageIgnore
	 */
	public function shutdown(): void {
		/** @var WP_Object_Cache $wp_object_cache */
		global $wp_object_cache;

		if ( $this->cache_hits_counter ) {
			$this->cache_hits_counter->incBy( $wp_object_cache->cache_hits, [ (string) get_current_blog_id() ] );
			$this->cache_misses_counter->incBy( $wp_object_cache->cache_misses, [ (string) get_current_blog_id() ] );
		}

		if ( $this->operation_counter ) {
			foreach ( $wp_object_cache->stats as $operation => $count ) {
				$this->operation_counter->incBy( $count, [ (string) get_current_blog_id(), (string) $operation ] );
			}
		}
	}
}
