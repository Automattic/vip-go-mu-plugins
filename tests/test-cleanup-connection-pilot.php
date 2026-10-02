<?php

use PHPUnit\Framework\TestCase;

class Cleanup_Connection_Pilot_Test extends TestCase {
	public function scenarios(): array {
		return [
			'local missing'  => [ 'local', 'missing', 0, 0 ],
			'local loaded'   => [ 'local', 'loaded', 0, 0 ],
			'hosted loaded'  => [ 'production', 'loaded', 1, 0 ],
			'hosted missing' => [ 'production', 'missing', 0, 1 ],
			'hosted skipped' => [ 'production', 'skip', 0, 0 ],
		];
	}

	/** @dataProvider scenarios */
	public function test_cleanup_handles_connection_pilot_availability( $environment, $availability, $calls, $warnings ): void {
		// A subprocess ensures the missing-class cases really have no Pilot class.
		$command = [ PHP_BINARY, __DIR__ . '/fixtures/wp-cli/cleanup-connection-pilot.php', $environment, $availability ];
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Isolate missing-class regression cases.
		$process = proc_open( $command, [
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		], $pipes );
		$output  = stream_get_contents( $pipes[1] );
		$error   = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $error );
		$result = json_decode( $output, true );
		$this->assertSame( $calls, $result['calls'] );
		$this->assertCount( $warnings, $result['warnings'] );
	}
}
