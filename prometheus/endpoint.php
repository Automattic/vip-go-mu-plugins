<?php
namespace Automattic\VIP\Prometheus;

use Automattic\VIP\Utils\Context;
use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;

require_once __DIR__ . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/lib/utils/class-context.php';

// Someone is trying to be sneaky, we can't have that.
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
if ( defined( 'ABSPATH' ) || ! Context::is_prom_endpoint_request() ) {
	return;
}

$registry = create_registry();

// @codeCoverageIgnoreStart -- this file is loaded before tests start
header( 'Content-Type: text/plain' );
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
