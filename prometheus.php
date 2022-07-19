<?php

use Automattic\VIP\Prometheus\Cache_Collector;

if ( defined( 'ABSPATH' ) ) {
	if ( file_exists( __DIR__ . '/prometheus/index.php' ) ) {
		require_once __DIR__ . '/prometheus/index.php';
	}

	if ( file_exists( __DIR__ . '/prometheus-collectors/class-cache-collector.php' ) ) {
		require_once __DIR__ . '/prometheus-collectors/class-cache-collector.php';
	}

	add_filter( 'vip_prometheus_collectors', function ( array $collectors ): array {
		$collectors[] = new Cache_Collector();
		return $collectors;
	} );
}
