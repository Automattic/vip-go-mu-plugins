<?php
if ( method_exists( '\Automattic\VIP\Search\Search', 'should_load_new_ep' ) && \Automattic\VIP\Search\Search::should_load_new_ep() ) {
	require_once __DIR__ . '/../../elasticpress-next/elasticpress.php';
} else {
	require_once __DIR__ . '/../../elasticpress/elasticpress.php';
}

// Override query log to remove Authorization header.
function ep_get_query_log() {
	$queries = \ElasticPress\Elasticsearch::factory()->get_query_log();
	foreach ( $queries as $index => $query ) {
		unset( $queries[ $index ]['args']['headers']['Authorization'] );
	}

	return $queries;
}
