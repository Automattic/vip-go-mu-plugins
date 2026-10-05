<?php

namespace Automattic\VIP\Search;

// Test-only subprocesses and pipes exercise independent PHP requests.
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open

use PHPUnit\Framework\TestCase;

/**
 * Exercise the real object-cache backend across independent PHP requests.
 */
class Test_Concurrency_Shared_Cache extends TestCase {
	/**
	 * A limit of one must admit only one worker and release both increments.
	 */
	public function test_two_workers_share_atomic_counter(): void {
		if ( ! getenv( 'VIP_TEST_MEMCACHED_SERVER' ) || ! extension_loaded( 'memcached' ) || ! function_exists( 'proc_open' ) ) {
			self::markTestSkipped( 'Requires VIP_TEST_MEMCACHED_SERVER, the memcached extension and proc_open; see docs/testing.md.' );
		}

		$salt    = 'vip-concurrency-' . bin2hex( random_bytes( 16 ) );
		$workers = [];
		try {
			// Seed the production cache namespace before racing the counter operations.
			// This also proves that the shared service is reachable, rather than failing open.
			$workers[] = $this->start_worker( $salt, 'seed' );
			self::assertSame( [ true, 0 ], json_decode( $this->read_worker( $workers[0] ), true ) );
			$this->finish_worker( $workers[0] );
			$workers   = [];
			$workers[] = $this->start_worker( $salt, 'increment' );
			$workers[] = $this->start_worker( $salt, 'increment' );
			foreach ( [ 'ready', 'counter' ] as $phase ) {
				foreach ( $workers as $worker ) {
					self::assertSame( $phase, $this->read_worker( $worker ) );
				}
				foreach ( $workers as $worker ) {
					fwrite( $worker['pipes'][0], "continue\n" );
				}
			}
			$results = array_map( [ $this, 'read_worker' ], $workers );
			foreach ( $workers as $worker ) {
				fwrite( $worker['pipes'][0], "continue\n" );
			}
			foreach ( $workers as &$worker ) {
				$this->finish_worker( $worker );
			}
			unset( $worker );

			$workers[] = $this->start_worker( $salt, 'count' );
			$count     = $this->read_worker( $workers[2] );
			$this->finish_worker( $workers[2] );
			sort( $results );
			self::assertSame( [ 'admitted', 'rejected' ], $results, 'Exactly one worker must be admitted at limit one.' );
			self::assertSame( [ true, 0 ], json_decode( $count, true ), 'The shared counter must exist and return to zero.' );
		} finally {
			foreach ( $workers as $worker ) {
				if ( is_resource( $worker['process'] ) ) {
					proc_terminate( $worker['process'] );
					foreach ( $worker['pipes'] as $pipe ) {
						if ( is_resource( $pipe ) ) {
							fclose( $pipe );
						}
					}
					proc_close( $worker['process'] );
				}
			}
		}
	}

	/**
	 * Start an independent PHP request with the production cache drop-in.
	 *
	 * @param string $salt Unique cache namespace shared only by these workers.
	 * @param string $mode Increment or read the counter.
	 * @return array
	 */
	private function start_worker( string $salt, string $mode ): array {
		$process = proc_open(
			[ PHP_BINARY, __DIR__ . '/../../fixtures/concurrency-cache-worker.php', ABSPATH, $salt, $mode ],
			[ [ 'pipe', 'r' ], [ 'pipe', 'w' ], [ 'pipe', 'w' ] ],
			$pipes
		);
		self::assertIsResource( $process );
		stream_set_timeout( $pipes[1], 15 );
		return [
			'process' => $process,
			'pipes'   => $pipes,
		];
	}

	/**
	 * Receive one bounded worker status, reporting subprocess errors on failure.
	 *
	 * @param array $worker Process and pipes.
	 * @return string
	 */
	private function read_worker( array $worker ): string {
		$line = fgets( $worker['pipes'][1] );
		if ( false === $line ) {
			stream_set_blocking( $worker['pipes'][2], false );
			self::fail( 'Worker failed or timed out: ' . stream_get_contents( $worker['pipes'][2] ) );
		}
		return trim( $line );
	}

	/**
	 * Reap a completed request and require a clean exit.
	 *
	 * @param array $worker Process and pipes, cleared after completion.
	 */
	private function finish_worker( array &$worker ): void {
		fclose( $worker['pipes'][0] );
		fclose( $worker['pipes'][1] );
		$error = stream_get_contents( $worker['pipes'][2] );
		fclose( $worker['pipes'][2] );
		$status            = proc_close( $worker['process'] );
		$worker['process'] = null;
		self::assertSame( 0, $status, $error );
	}
}
