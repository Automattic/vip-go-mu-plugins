<?php

namespace Automattic\VIP\Search;

// Local test-only HTTP workers and owned /tmp barriers.
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open, WordPress.PHP.NoSilencedErrors.Discouraged

use PHPUnit\Framework\TestCase;

/**
 * Exercise real APCu storage from independently scheduled PHP server workers.
 */
class Test_Concurrency_APCu_Shared extends TestCase {
	public function test_atomic_admission_and_non_atomic_challenge(): void {
		if ( ! extension_loaded( 'apcu' ) || ! function_exists( 'proc_open' ) || ! function_exists( 'posix_kill' ) || ! is_executable( '/usr/bin/setsid' ) ) {
			self::markTestSkipped( 'Requires APCu, proc_open, POSIX process control, setsid and PHP CLI server workers.' );
		}

		$baseline = $this->race( 'baseline' );
		self::assertNotSame( $baseline['results'][0]['pid'], $baseline['results'][1]['pid'], 'Actors must be independent server workers.' );
		self::assertSame( [ true, false ], $baseline['admissions'], 'Exactly one actor may enter at limit one.' );
		self::assertSame( 0, $baseline['count'], 'Both increments must eventually be released.' );

		$mutant = $this->race( 'mutant' );
		self::assertSame( [ true, true ], $mutant['admissions'], 'The non-atomic fetch/modify/store challenge must violate the admission assertion.' );
	}

	private function race( string $mode ): array {
		$dir = sys_get_temp_dir() . '/vip-apcu-' . bin2hex( random_bytes( 12 ) );
		mkdir( $dir, 0700 );
		$socket       = null;
		$server       = null;
		$server_pid   = null;
		$server_group = null;
		$clients      = [];
		try {
			$socket = stream_socket_server( 'tcp://127.0.0.1:0', $errno, $error );
			self::assertIsResource( $socket, $error );
			$address = stream_socket_get_name( $socket, false );
			fclose( $socket );
			$socket = null;
			$server = proc_open(
				[ '/usr/bin/setsid', PHP_BINARY, '-d', 'apc.enable_cli=1', '-S', $address, __DIR__ . '/../../fixtures/concurrency-apcu-server.php' ],
				[ [ 'file', '/dev/null', 'r' ], [ 'file', $dir . '/server.log', 'w' ], [ 'file', $dir . '/server.log', 'a' ] ],
				$pipes,
				null,
				[
					'PHP_CLI_SERVER_WORKERS' => '2',
					'APCU_TEST_DIR'          => $dir,
					'APCU_TEST_MODE'         => $mode,
				]
			);
			self::assertIsResource( $server );
			$server_pid = proc_get_status( $server )['pid'];
			$base       = 'http://' . $address;
			$deadline   = microtime( true ) + 10;
			do {
				$health = @file_get_contents( $base . '/health', false, stream_context_create( [ 'http' => [ 'timeout' => 0.2 ] ] ) );
				if ( 'ready' === $health ) {
					break;
				}
				usleep( 10000 );
			} while ( microtime( true ) < $deadline );
			self::assertSame( 'ready', $health, 'Shared APCu server must start with APCu enabled.' );
			$server_group = posix_getpgid( $server_pid );
			self::assertSame( $server_pid, $server_group, 'The owned server must have its own process group.' );
			self::assertSame( 'seeded', file_get_contents( $base . '/seed', false, stream_context_create( [ 'http' => [ 'timeout' => 2 ] ] ) ) );

			foreach ( [ 1, 2 ] as $id ) {
				$clients[] = proc_open( [ PHP_BINARY, '-r', 'echo file_get_contents($argv[1]);', $base . '/actor?id=' . $id ], [ [ 'file', '/dev/null', 'r' ], [ 'file', $dir . '/client-' . $id . '.out', 'w' ], [ 'file', $dir . '/client-' . $id . '.err', 'w' ] ], $client_pipes );
				self::assertIsResource( $clients[ $id - 1 ] );
			}
			$this->await_files( $dir, [ 'ready-1', 'ready-2' ] );
			file_put_contents( $dir . '/go', '1' );
			if ( 'mutant' === $mode ) {
				$this->await_files( $dir, [ 'fetched-1', 'fetched-2' ] );
				file_put_contents( $dir . '/racego', '1' );
			}
			$this->await_files( $dir, [ 'result-1', 'result-2' ] );
			$results = [ json_decode( file_get_contents( $dir . '/result-1' ), true ), json_decode( file_get_contents( $dir . '/result-2' ), true ) ];
			self::assertSame( [ $dir, $dir ], array_column( $results, 'marker' ), 'Both independent actors must read the same APCu marker.' );
			file_put_contents( $dir . '/done', '1' );
			foreach ( $clients as $client ) {
				self::assertSame( 0, proc_close( $client ), file_get_contents( $dir . '/server.log' ) . file_get_contents( $dir . '/client-' . ( array_search( $client, $clients, true ) + 1 ) . '.err' ) );
			}
			$clients            = [];
			[ $count, $marker ] = json_decode( file_get_contents( $base . '/count', false, stream_context_create( [ 'http' => [ 'timeout' => 2 ] ] ) ), true );
			self::assertSame( $dir, $marker, 'The final reader must share the workers\' APCu storage.' );
			$admissions = array_column( $results, 'admitted' );
			rsort( $admissions );
			return compact( 'results', 'admissions', 'count' );
		} finally {
			foreach ( [ 'go', 'racego', 'done' ] as $name ) {
				file_put_contents( $dir . '/' . $name, '1' );
			}
			foreach ( $clients as $client ) {
				if ( is_resource( $client ) ) {
					proc_terminate( $client );
					proc_close( $client );
				}
			}
			if ( is_resource( $socket ) ) {
				fclose( $socket );
			}
			if ( is_resource( $server ) ) {
				$status      = proc_get_status( $server );
				$owned_group = is_int( $server_group ) ? $server_group : ( $status['running'] && is_int( $server_pid ) ? posix_getpgid( $server_pid ) : null );
				if ( is_int( $server_pid ) && $server_pid === $owned_group && posix_getpgrp() !== $owned_group ) {
					posix_kill( -$owned_group, SIGTERM );
				} elseif ( $status['running'] ) {
					proc_terminate( $server );
				}
				proc_close( $server );
			}
			foreach ( glob( $dir . '/*' ) as $file ) {
				unlink( $file );
			}
			rmdir( $dir );
		}
	}

	private function await_files( string $dir, array $files ): void {
		$deadline = microtime( true ) + 15;
		do {
			$present = array_map( static fn( $file ) => file_exists( $dir . '/' . $file ), $files );
			if ( ! in_array( false, $present, true ) ) {
				return;
			}
			usleep( 10000 );
		} while ( microtime( true ) < $deadline );
		self::fail( 'Timed out waiting for APCu worker barrier: ' . implode( ', ', $files ) );
	}
}
