<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Automattic\VIP\Prometheus\APCu_Collector;
use Automattic\VIP\Prometheus\Cache_Collector;
use Automattic\VIP\Prometheus\Login_Stats_Collector;
use Automattic\VIP\Prometheus\OpCache_Collector;
use Automattic\VIP\Prometheus\Post_Stats_Collector;

require_once __DIR__ . '/vendor/autoload.php';

// Someone is trying to be sneaky, we can't have that.
if ( defined( 'ABSPATH' ) ) {
	return;
}

$collectors = [];
$registry   = create_registry();

// @codeCoverageIgnoreStart -- this file is loaded before tests start
$files = [
	'/index.php',
	'/../prometheus-collectors/class-cache-collector.php',
	'/../prometheus-collectors/class-apcu-collector.php',
	'/../prometheus-collectors/class-opcache-collector.php',
	'/../prometheus-collectors/class-login-stats-collector.php',
];

foreach ( $files as $file ) {
	if ( ! file_exists( __DIR__ . $file ) ) {
		return; // Bail early if one of the files doesn't exist.
	} else {
		require_once __DIR__ . $file;
	}
}

$collectors[] = new Cache_Collector();
$collectors[] = new APCu_Collector();
$collectors[] = new OpCache_Collector();
$collectors[] = new Login_Stats_Collector();

array_walk( $collectors, fn ( CollectorInterface $collector ) => $collector->collect_metrics() );

$renderer = new RenderTextFormat();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is a text/plain endpoint
echo $renderer->render( $registry->getMetricFamilySamples() );

// @codeCoverageIgnoreEnd

/**
 * Create a registry for Prometheus metrics.
 */
function create_registry(): RegistryInterface {
	// @codeCoverageIgnoreStart -- APCu may or may not be available during tests
	/** @var Adapter $storage */
	if ( extension_loaded( 'apcu' ) && apcu_enabled() ) {
		$storage_backend = APCng::class;
	} else {
		$storage_backend = InMemory::class;
	}
	// @codeCoverageIgnoreEnd

	$storage = new $storage_backend();

	return new CollectorRegistry( $storage );
}
