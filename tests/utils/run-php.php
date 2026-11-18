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
	// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Test-only temporary files.
	// The child writes its output to files rather than pipes, so it can't block on a full pipe
	// while this process waits for it.
	$stdout_file = tempnam( get_temp_dir(), 'vip-run-php-' );
	$stderr_file = tempnam( get_temp_dir(), 'vip-run-php-' );

	try {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Runs test fixtures in isolation.
		$process = proc_open(
			array_merge( [ PHP_BINARY ], $args ),
			[ [ 'pipe', 'r' ], [ 'file', $stdout_file, 'w' ], [ 'file', $stderr_file, 'w' ] ],
			$pipes
		);
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Could not start a PHP process' );
		}

		fwrite( $pipes[0], $stdin );
		fclose( $pipes[0] );
		$exit = proc_close( $process );

		return [
			'exit'   => $exit,
			'stdout' => (string) file_get_contents( $stdout_file ),
			'stderr' => (string) file_get_contents( $stderr_file ),
		];
	} finally {
		unlink( $stdout_file );
		unlink( $stderr_file );
	}
	// phpcs:enable
}
