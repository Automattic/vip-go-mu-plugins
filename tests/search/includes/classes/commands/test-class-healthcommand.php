<?php

namespace Automattic\VIP\Search\Commands;

use WP_UnitTestCase;

class HealthCommand_Test extends WP_UnitTestCase {
	public function setUp(): void {
		// define( 'WP_CLI', true );

		// require_once __DIR__ . '/../../../../../vip-helpers/vip-wp-cli.php';
		// require_once __DIR__ . '/../../../../../search/search.php';
		// require_once __DIR__ . '/../../../../../search/includes/classes/commands/class-healthcommand.php';
	}

	public function test__vip_search_healthcommand_validate_users_count() {
		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		/*$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$command = new \Automattic\VIP\Search\HealthCommand();

		$command->validate_users_count();*/

		$this->markTestIncomplete(
			'Our test suite is not setup to include the WP CLI plugin at this time'
		);
	}
}
