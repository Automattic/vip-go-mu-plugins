<?php

use PHPUnit\Framework\TestCase;

use function Automattic\Test\Utils\run_php;

class Cleanup_Connection_Pilot_Test extends TestCase {
	public function test_missing_connection_pilot_warns_once_without_failing(): void {
		// A subprocess ensures there really is no Pilot class.
		$result = run_php( [ '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=', __DIR__ . '/fixtures/wp-cli/cleanup-connection-pilot.php' ] );
		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$this->assertSame( '', $result['stdout'] );
		$this->assertSame( 1, substr_count( $result['stderr'], 'Connection Pilot is unavailable' ), $result['stderr'] );
	}
}
