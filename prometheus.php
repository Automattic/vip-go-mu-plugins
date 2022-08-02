<?php

use Automattic\VIP\Prometheus\APCu_Collector;
use Automattic\VIP\Prometheus\Cache_Collector;
use Automattic\VIP\Prometheus\OpCache_Collector;

// @codeCoverageIgnoreStart -- this file is loaded before tests start
if ( defined( 'ABSPATH' ) ) {
	if ( file_exists( __DIR__ . '/prometheus/index.php' ) ) {
		require_once __DIR__ . '/prometheus/index.php';
	}

	if ( file_exists( __DIR__ . '/prometheus-collectors/class-cache-collector.php' ) ) {
		require_once __DIR__ . '/prometheus-collectors/class-cache-collector.php';
		require_once __DIR__ . '/prometheus-collectors/class-apcu-collector.php';
		require_once __DIR__ . '/prometheus-collectors/class-opcache-collector.php';

		add_filter( 'vip_prometheus_collectors', function ( array $collectors, string $hook ): array {
			if ( 'vipgo_mu_plugins_loaded' === $hook ) {
				$collectors[] = new Cache_Collector();
				$collectors[] = new APCu_Collector();
				$collectors[] = new OpCache_Collector();
			}

			return $collectors;
		}, 10, 2 );
	}
}
// @codeCoverageIgnoreEnd
