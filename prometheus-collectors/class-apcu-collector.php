<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Gauge;
use Prometheus\RegistryInterface;

/**
 * @codeCoverageIgnore
 */
class APCu_Collector implements CollectorInterface {
	private ?Gauge $num_slots_gauge    = null;
	private ?Gauge $cache_hits_gauge   = null;
	private ?Gauge $cache_misses_gauge = null;
	private ?Gauge $inserts_gauge      = null;
	private ?Gauge $entries_gauge      = null;
	private ?Gauge $expunges_gauge     = null;
	private ?Gauge $memory_gauge       = null;

	public function initialize( RegistryInterface $registry ): void {
		if ( extension_loaded( 'apcu' ) && apcu_enabled() ) {
			$this->num_slots_gauge = $registry->getOrRegisterGauge(
				'apcu',
				'num_slots',
				'Number of slots in the APCu cache'
			);

			// Register cache hits and misses as gauges because it is impossible to get the delta between two operations
			$this->cache_hits_gauge = $registry->getOrRegisterGauge(
				'apcu',
				'cache_hits',
				'Total number of cache hits'
			);

			$this->cache_misses_gauge = $registry->getOrRegisterGauge(
				'apcu',
				'cache_misses',
				'Total number of cache misses'
			);

			$this->inserts_gauge = $registry->getOrRegisterGauge(
				'apcu',
				'inserts',
				'Total number of inserts'
			);

			$this->entries_gauge = $registry->getOrRegisterGauge(
				'apcu',
				'entries',
				'Number of entries in the cache'
			);

			$this->expunges_gauge = $registry->getOrRegisterGauge(
				'apcu',
				'expunges',
				'Number of expunges'
			);

			$this->memory_gauge = $registry->getOrRegisterGauge(
				'apcu',
				'cache_size',
				'Memory taken by all cache entries'
			);
		}
	}

	public function collect_metrics(): void {
		if ( $this->num_slots_gauge ) {
			$info = apcu_cache_info( true );
			if ( is_array( $info ) ) {
				$this->num_slots_gauge->set( $info['num_slots'] );
				$this->cache_hits_gauge->set( $info['num_hits'] );
				$this->cache_misses_gauge->set( $info['num_misses'] );

				if ( isset( $info['num_inserts'] ) ) {
					$this->inserts_gauge->set( $info['num_inserts'] );
				}

				if ( isset( $info['num_entries'] ) ) {
					$this->entries_gauge->set( $info['num_entries'] );
				}

				if ( isset( $info['expunges'] ) ) {
					$this->expunges_gauge->set( $info['expunges'] );
				}

				if ( isset( $info['mem_size'] ) ) {
					$this->memory_gauge->set( $info['mem_size'] );
				}
			}
		}
	}
}
