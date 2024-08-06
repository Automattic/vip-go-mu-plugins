# Prometheus Metrics

MU-Plugins supports collecting and shipping internal metrics to Prometheus. 

This system is intended for use by WPVIP to monitor the operation of sites and is not suitable for customer use.

## Stats Endpoint

Prometheus metrics are exposed at `/.vip-prom-metrics`.

## Collectors

Stat collection is implemented as Collectors (see the `prometheus-collectors` directory). Each Collector implements `CollectorInterface` and is responsible for collecting the relevant metrics.

For real-time metrics, like recording specific actions, implement a method in the Collector that is called directly or via WP-hook.

For "aggregated" metrics collected on an interval (like post counts), implement `collect_metrics()`, which will be run on cron.

### Registering Collectors

Collectors must be registered via the `vip_prometheus_collectors` hook:

```
add_filter( 'vip_prometheus_collectors', function ( $collectors ) {
	$collectors['my_collector_name'] = new Collector();

	return $collectors;
} );
```
