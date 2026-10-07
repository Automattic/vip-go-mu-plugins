<?php
/**
 * Helper for running PHP code in a separate process.
 *
 * @package Automattic\Test\Utils
 */

namespace Automattic\Test\Utils;

use RuntimeException;

/**
 * Run PHP in a child process and wait for it to exit.
 *
 * For code that can't run twice in the test process, such as files that define constants or
 * functions at load time. Unlike `@runInSeparateProcess`, the child doesn't boot WordPress.
 *
 * @param string[] $args  Arguments for the PHP binary: ini options, then the script and its arguments.
 *                        With no script, PHP runs the code read from $stdin.
 * @param string   $stdin Data written to the child's standard input.
 *
 * @return array{exit: int, stdout: string, stderr: string}
 */
function run_php( array $args, string $stdin = '' ): array {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Runs test fixtures in isolation.
	$process = proc_open( array_merge( [ PHP_BINARY ], $args ), [ [ 'pipe', 'r' ], [ 'pipe', 'w' ], [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Could not start a PHP process' );
	}

	fwrite( $pipes[0], $stdin ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
	fclose( $pipes[0] );
	$stdout = stream_get_contents( $pipes[1] );
	$stderr = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	return [
		'exit'   => proc_close( $process ),
		'stdout' => $stdout,
		'stderr' => $stderr,
	];
}
