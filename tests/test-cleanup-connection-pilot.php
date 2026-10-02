<?php

use PHPUnit\Framework\TestCase;

class Cleanup_Connection_Pilot_Test extends TestCase {
	public function test_missing_connection_pilot_warns_once_without_failing(): void {
		// A subprocess ensures there really is no Pilot class.
		$command = [ PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=', __DIR__ . '/fixtures/wp-cli/cleanup-connection-pilot.php' ];
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Isolate the missing-class regression.
		$process = proc_open( $command, [
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		], $pipes );
		$output  = stream_get_contents( $pipes[1] );
		$error   = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $error );
		$this->assertSame( '', $output );
		$this->assertSame( 1, substr_count( $error, 'Connection Pilot is unavailable' ), $error );
	}
}
