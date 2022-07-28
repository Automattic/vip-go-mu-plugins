<?php

use PHPUnit\Runner\BeforeFirstTestHook;

class SpeedUp_Isolated_WP_Tests implements BeforeFirstTestHook {
	public function executeBeforeFirstTest(): void {
		require_once __DIR__ . '/bootstrap.php';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
		putenv( 'WP_TESTS_SKIP_INSTALL=1' );
	}
}
