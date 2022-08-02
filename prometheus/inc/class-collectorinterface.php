<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\RegistryInterface;

interface CollectorInterface {
	public function initialize( RegistryInterface $registry ): void;

	/**
	 * Last chance to collect metrics before sending them to the scraper.
	 * 
	 * This can be useful, for, for example, gauges which measure something external wrt the application (e.g., APC cache stats)
	 */
	public function collect_metrics(): void;
}
