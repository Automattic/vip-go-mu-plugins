<?php
if ( \Automattic\VIP\Search\Search::is_next_ep_constant_defined() ) {
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
