<?php

namespace Automattic\VIP\Elasticsearch\Commands;

class HealthCommand_Test extends \WP_UnitTestCase {
	public function setUp() {
		// define( 'WP_CLI', true );

		// require_once __DIR__ . '/../../../../../vip-helpers/vip-wp-cli.php';
		// require_once __DIR__ . '/../../../../../elasticsearch/elasticsearch.php';
		// require_once __DIR__ . '/../../../../../elasticsearch/includes/classes/commands/class-healthcommand.php';
	}

	public function test__vip_search_healthcommand_validate_users_count() {
		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		/*$es = new \Automattic\VIP\Elasticsearch\Elasticsearch();
		$es->init();

		$command = new \Automattic\VIP\Elasticsearch\HealthCommand();

		$command->validate_users_count();*/

		$this->markTestIncomplete(
			'Our test suite is not setup to include the WP CLI plugin at this time'
		);
	}
}
