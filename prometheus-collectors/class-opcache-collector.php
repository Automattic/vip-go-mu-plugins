<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Gauge;
use Prometheus\RegistryInterface;

/**
 * @codeCoverageIgnore
 */
class OpCache_Collector implements CollectorInterface {
	private ?Gauge $cache_used_memory_gauge   = null;
	private ?Gauge $cache_free_memory_gauge   = null;
	private ?Gauge $cache_wasted_memory_gauge = null;

	private ?Gauge $interned_used_memory_gauge = null;
	private ?Gauge $interned_free_memory_gauge = null;
	private ?Gauge $interned_num_strings_gauge = null;

	private ?Gauge $opcache_hits_gauge   = null;
	private ?Gauge $opcache_misses_gauge = null;

	private ?Gauge $opcache_cached_items_gauge    = null;
	private ?Gauge $opcache_max_cached_keys_gauge = null;
	private ?Gauge $opcache_restarts_gauge        = null;

	public function initialize( RegistryInterface $registry ): void {
		if ( $this->is_opcache_available() ) {
			$this->cache_used_memory_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'cache_used_memory',
				'Memory used by the cache'
			);

			$this->cache_free_memory_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'cache_free_memory',
				'Free memory in the cache'
			);

			$this->cache_wasted_memory_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'cache_wasted_memory',
				'Wasted memory in the cache'
			);

			$this->interned_used_memory_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'interned_used_memory',
				'Memory used by the interned strings'
			);

			$this->interned_free_memory_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'interned_free_memory',
				'Free memory in the interned strings buffer'
			);

			$this->interned_num_strings_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'interned_num_strings',
				'Number of interned strings'
			);

			$this->opcache_hits_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'hits',
				'Number of cache hits'
			);

			$this->opcache_misses_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'misses',
				'Number of cache misses'
			);

			$this->opcache_cached_items_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'cached_items',
				'Number of cached scripts',
				[ 'type' ]
			);

			$this->opcache_max_cached_keys_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'max_cached_keys',
				'Maximum number of cache keys'
			);

			$this->opcache_restarts_gauge = $registry->getOrRegisterGauge(
				'opcache',
				'restarts',
				'Number of restarts',
				[ 'type' ]
			);
		}
	}

	public function collect_metrics(): void {
		if ( $this->cache_used_memory_gauge ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions, WordPress.PHP.NoSilencedErrors.Discouraged
			$info = @opcache_get_status( false );
			if ( is_array( $info ) ) {
				$this->cache_used_memory_gauge->set( $info['memory_usage']['used_memory'] );
				$this->cache_free_memory_gauge->set( $info['memory_usage']['free_memory'] );
				$this->cache_wasted_memory_gauge->set( $info['memory_usage']['wasted_memory'] );

				$this->interned_used_memory_gauge->set( $info['interned_strings_usage']['used_memory'] );
				$this->interned_free_memory_gauge->set( $info['interned_strings_usage']['free_memory'] );
				$this->interned_num_strings_gauge->set( $info['interned_strings_usage']['number_of_strings'] );

				$this->opcache_hits_gauge->set( $info['opcache_statistics']['hits'] );
				$this->opcache_misses_gauge->set( $info['opcache_statistics']['misses'] );

				$this->opcache_cached_items_gauge->set( $info['opcache_statistics']['num_cached_scripts'], [ 'scripts' ] );
				$this->opcache_cached_items_gauge->set( $info['opcache_statistics']['num_cached_keys'], [ 'keys' ] );
				$this->opcache_max_cached_keys_gauge->set( $info['opcache_statistics']['max_cached_keys'] );
				$this->opcache_restarts_gauge->set( $info['opcache_statistics']['oom_restarts'], [ 'oom' ] );
				$this->opcache_restarts_gauge->set( $info['opcache_statistics']['manual_restarts'], [ 'manual' ] );
				$this->opcache_restarts_gauge->set( $info['opcache_statistics']['hash_restarts'], [ 'hash' ] );
			}
		}
	}

	public function process_metrics(): void {
		/* Do nothing */
	}

	/**
	 * Check if opcache is installed, enabled and configured to allow this file to call opcache api.
	 */
	private function is_opcache_available(): bool {
		if ( function_exists( 'opcache_get_status' ) && ini_get( 'opcache.enable' ) ) {
			$restricted_to_path = ini_get( 'opcache.restrict_api' );
			if ( ! $restricted_to_path ) {
				return true;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only used in comparison
			$path_translated = $_SERVER['PATH_TRANSLATED'] ?? '';

			// See https://heap.space/xref/PHP-8.0/ext/opcache/zend_accelerator_module.c?r=0571f094#49
			if ( ! isset( $path_translated )
				|| strlen( $path_translated ) < strlen( $restricted_to_path )
				|| strncmp( $path_translated, $restricted_to_path, strlen( $restricted_to_path ) ) !== 0
			) {
				return false;
			}

			return true;
		}

		return false;
	}
}
