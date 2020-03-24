<?php
require_once __DIR__ . '../../../elasticpress/elasticpress.php';

// Override query log to remove Authorization header.
function ep_get_query_log() {
	$queries = \ElasticPress\Elasticsearch::factory()->get_query_log();
	foreach ( $queries as $index => $query ) {
		unset( $queries[ $index ]['args']['headers']['Authorization'] );
	}

	return $queries;
}
