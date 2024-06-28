<?php
use Automattic\VIP\Feature;
use Automattic\VIP\Prometheus\APCu_Collector;
use Automattic\VIP\Prometheus\Cache_Collector;
use Automattic\VIP\Prometheus\Login_Stats_Collector;
use Automattic\VIP\Prometheus\OpCache_Collector;
use Automattic\VIP\Prometheus\Post_Stats_Collector;
use Automattic\VIP\Prometheus\User_Stats_Collector;
use Automattic\VIP\Prometheus\Error_Stats_Collector;
use Automattic\VIP\Prometheus\Potential_Multi_Dataset_Queries_Collector;
use Automattic\VIP\Prometheus\Multisite_Stats_Collector;
// @codeCoverageIgnoreStart -- this file is loaded before tests start
if ( defined( 'ABSPATH' ) ) {
	require_once __DIR__ . '/prometheus/index.php';

	$files = [
		'/prometheus-collectors/class-cache-collector.php',
		'/prometheus-collectors/class-apcu-collector.php',
		'/prometheus-collectors/class-opcache-collector.php',
		'/prometheus-collectors/class-login-stats-collector.php',
		'/prometheus-collectors/class-error-stats-collector.php',
		'/prometheus-collectors/class-multisite-stats-collector.php',
	];

	$should_enable_user_collector = Feature::is_enabled( 'prom-user-collection' );

	if ( $should_enable_user_collector ) {
		$files[] = '/prometheus-collectors/class-user-stats-collector.php';
	}


	if ( true === defined( 'VIP_POTENTIAL_MULTI_DATASET_QUERIES_COLLECTOR_ENABLED' ) && true === constant( 'VIP_POTENTIAL_MULTI_DATASET_QUERIES_COLLECTOR_ENABLED' ) ) {
		$files[] = '/prometheus-collectors/class-potential-multi-dataset-queries-collector.php';
	}

	foreach ( $files as $file ) {
		if ( file_exists( __DIR__ . $file ) ) {
			require_once __DIR__ . $file;
		}
	}

	add_filter( 'vip_prometheus_collectors', function ( array $collectors ): array {
		$to_init = [
			'cache'                           => Cache_Collector::class,
			'apcu'                            => APCu_Collector::class,
			'opcache'                         => OpCache_Collector::class,
			'login'                           => Login_Stats_Collector::class,
			'error'                           => Error_Stats_Collector::class,
			'user'                            => User_Stats_Collector::class,
			'potential_multi_dataset_queries' => Potential_Multi_Dataset_Queries_Collector::class,
			'multisite'                       => Multisite_Stats_Collector::class,
		];

		foreach ( $to_init as $slug => $class ) {
			if ( class_exists( $class ) && ! isset( $collectors[ $slug ] ) ) {
				$collectors[ $slug ] = new $class();
			}
		}

		return $collectors;
	} );
}
// @codeCoverageIgnoreEnd
