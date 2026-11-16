<?php
/**
 * Prepended to the standalone endpoint by Test_Prometheus: stores a retired and a current metric in APCu,
 * as a request running an older release would have.
 */

require_once dirname( __DIR__, 3 ) . '/prometheus/vendor/autoload.php';

$old_registry = new \Prometheus\CollectorRegistry( new \Prometheus\Storage\APCng() );
$old_registry->getOrRegisterCounter( 'object_cache', 'cache_hits_total', 'Retired', [ 'site_id' ] )->inc( [ '1' ] );
$old_registry->getOrRegisterCounter( 'object_cache', 'oprations_total', 'Current', [ 'site_id', 'operation' ] )->inc( [ '1', 'get' ] );
