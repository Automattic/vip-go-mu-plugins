<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\RegistryInterface;
use WP_Object_Cache;

class Cache_Collector implements CollectorInterface {
	private ?Counter $cache_hits_counter   = null;
	private ?Counter $cache_misses_counter = null;
	private ?Counter $operation_counter    = null;
	private ?Gauge $alloptions_keys_gauge  = null;
	private ?Gauge $size_gauge             = null;

	private string $blog_id;
	/**
	 * @global WP_Object_Cache $wpdb
	 */
	public function initialize( RegistryInterface $registry ): void {
		/** @var WP_Object_Cache $wp_object_cache */
		global $wp_object_cache;

		$this->blog_id = Plugin::get_instance()->get_site_label();

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

		if ( is_callable( [ $wp_object_cache, 'get_stats' ] ) ) {
			$this->alloptions_keys_gauge = $registry->getOrRegisterGauge(
				'object_cache',
				'alloptions_keys_total',
				'Number of keys in alloptions',
				[ 'site_id' ]
			);

			$this->size_gauge = $registry->getOrRegisterGauge(
				'object_cache',
				'size',
				'Cache size',
				[ 'site_id' ]
			);

			$this->operation_counter = $registry->getOrRegisterCounter(
				'object_cache',
				'operations_total',
				'Total number of operations',
				[ 'site_id', 'operation' ]
			);
		}

		add_action( 'shutdown', [ $this, 'shutdown' ] );
	}

	public function collect_metrics(): void {
		/* Do nothing */
	}

	public function process_metrics(): void {
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
			$this->cache_hits_counter->incBy( $wp_object_cache->cache_hits, [ $this->blog_id ] );
			$this->cache_misses_counter->incBy( $wp_object_cache->cache_misses, [ $this->blog_id ] );
		}

		if ( is_callable( [ $wp_object_cache, 'get_stats' ] ) ) {
			$stats = $wp_object_cache->get_stats();
			$this->alloptions_keys_gauge->set( count( wp_cache_get( 'alloptions', 'options' ) ), [ $this->blog_id ] );
			$this->size_gauge->set( $stats['totals']['size'], [ $this->blog_id ] );

			foreach ( $stats['operation_counts'] as $operation => $count ) {
				$this->operation_counter->incBy( $count, [ $this->blog_id, (string) $operation ] );
			}
		}
	}
}
