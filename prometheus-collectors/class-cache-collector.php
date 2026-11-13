<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;
use WP_Object_Cache;

class Cache_Collector implements CollectorInterface {
	private ?Counter $operation_counter = null;

	private string $blog_id;
	/**
	 * @global WP_Object_Cache $wpdb
	 */
	public function initialize( RegistryInterface $registry ): void {
		/** @var WP_Object_Cache $wp_object_cache */
		global $wp_object_cache;

		$this->blog_id = Plugin::get_instance()->get_site_label();

		if ( property_exists( $wp_object_cache, 'stats' ) && is_array( $wp_object_cache->stats ) ) {
			$this->operation_counter = $registry->getOrRegisterCounter(
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

		if ( $this->operation_counter ) {
			foreach ( $wp_object_cache->stats as $operation => $count ) {
				$this->operation_counter->incBy( $count, [ $this->blog_id, (string) $operation ] );
			}
		}
	}
}
