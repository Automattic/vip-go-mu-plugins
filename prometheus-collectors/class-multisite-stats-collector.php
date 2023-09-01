<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Gauge;
use Prometheus\RegistryInterface;

/**
 * @codeCoverageIgnore
 */
class Multisite_Stats_Collector implements CollectorInterface {
	private Gauge $network_site_gauge;

	public function initialize( RegistryInterface $registry ): void {
		if ( is_multisite() ) {
			$this->network_site_gauge = $registry->getOrRegisterGauge(
				'site',
				'count',
				'Number of network sites',
				[ 'status' ]
			);
		}
	}

	public function collect_metrics(): void {
		if ( ! $this->network_site_gauge ) {
			return;
		}

		$sites_count = wp_count_sites();

		if ( ! empty( $sites_count ) ) {
			foreach ( $sites_count as $status => $count ) {
				if ( 'all' !== $status ) {
					$this->network_site_gauge->set( $count, [ $status ] );
				}
			}
		}
	}

	public function process_metrics(): void {
		/* Do nothing */
	}
}
