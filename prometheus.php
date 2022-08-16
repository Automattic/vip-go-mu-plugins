<?php

use Automattic\VIP\Prometheus\APCu_Collector;
use Automattic\VIP\Prometheus\Cache_Collector;
use Automattic\VIP\Prometheus\Login_Stats_Collector;
use Automattic\VIP\Prometheus\OpCache_Collector;
use Automattic\VIP\Prometheus\Post_Stats_Collector;

// @codeCoverageIgnoreStart -- this file is loaded before tests start
if ( defined( 'ABSPATH' ) ) {
	$files = [
		'/prometheus/index.php',
		'/prometheus-collectors/class-cache-collector.php',
		'/prometheus-collectors/class-apcu-collector.php',
		'/prometheus-collectors/class-opcache-collector.php',
		'/prometheus-collectors/class-login-stats-collector.php',
		'/prometheus-collectors/class-post-stats-collector.php',
	];

	foreach ( $files as $file ) {
		if ( ! file_exists( __DIR__ . $file ) ) {
			return; // Bail early if one of the files doesn't exist.
		} else {
			require_once __DIR__ . $file;
		}
	}

	add_filter( 'vip_prometheus_collectors', function ( array $collectors, string $hook ): array {
		if ( 'vip_mu_plugins_loaded' === $hook ) {
			$collectors[] = new Cache_Collector();
			$collectors[] = new APCu_Collector();
			$collectors[] = new OpCache_Collector();
			$collectors[] = new Login_Stats_Collector();
			$collectors[] = new Post_Stats_Collector();
		}

		return $collectors;
	}, 10, 2 );
}
// @codeCoverageIgnoreEnd
