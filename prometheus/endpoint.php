<?php
namespace Automattic\VIP\Prometheus;

use Automattic\VIP\Utils\Context;
use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;

// @codeCoverageIgnoreStart -- this is a standalone endpoint which doesn't run in the context of the WP tests
if ( extension_loaded( 'newrelic' ) ) { // Ensure PHP agent is available
	newrelic_ignore_transaction();
}

require_once __DIR__ . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/lib/utils/class-context.php';

// Someone is trying to be sneaky, we can't have that.
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
if ( defined( 'ABSPATH' ) || ! Context::is_prom_endpoint_request() ) {
	return;
}

$registry = create_registry();

// APCu and OPcache stats describe this pod's php-fpm, which also serves the scrape, so refresh them on every scrape.
require_once dirname( __DIR__ ) . '/prometheus-collectors/class-apcu-collector.php';
require_once dirname( __DIR__ ) . '/prometheus-collectors/class-opcache-collector.php';
foreach ( [ new APCu_Collector(), new OpCache_Collector() ] as $collector ) {
	$collector->initialize( $registry );
	$collector->collect_metrics();
}

// Metrics removed from the collectors keep their stored values in APCu until php-fpm restarts, which mu-plugins
// deploys don't do, so the scrape would keep exporting them frozen. List them here to leave them out.
$retired_metrics = [ 'object_cache_cache_hits_total', 'object_cache_cache_misses_total' ];
$metrics         = array_filter(
	$registry->getMetricFamilySamples(),
	fn ( $family ) => ! in_array( $family->getName(), $retired_metrics, true )
);

header( 'Content-Type: text/plain' );
$renderer = new RenderTextFormat();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is a text/plain endpoint
echo $renderer->render( $metrics );

/**
 * Create a registry for Prometheus metrics.
 */
function create_registry(): RegistryInterface {
	/** @var Adapter $storage */
	if ( extension_loaded( 'apcu' ) && apcu_enabled() ) {
		$storage_backend = APCng::class;
	} else {
		$storage_backend = InMemory::class;
	}

	$storage      = new $storage_backend();
	$safe_adapter = new SafeAdapter( $storage );

	return new CollectorRegistry( $safe_adapter );
}
// @codeCoverageIgnoreEnd
