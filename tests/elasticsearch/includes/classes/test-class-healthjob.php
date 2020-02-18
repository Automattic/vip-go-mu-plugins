<?php

namespace Automattic\VIP\Elasticsearch;

class HealthJob_Test extends \WP_UnitTestCase {
	public function setUp() {
		require_once __DIR__ . '/../../../../elasticsearch/elasticsearch.php';
		require_once __DIR__ . '/../../../../elasticsearch/includes/classes/class-health-job.php';
	}

	public function test__vip_search_healthjob_check_health() {
		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$job = new \Automattic\VIP\Elasticsearch\HealthJob();

		$job->check_health();
	}
}
