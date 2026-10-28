#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Assert exact Docker-bound PHPUnit arguments for both shell wrappers. */

const TEST_IMAGE = 'ghcr.io/automattic/vip-container-images/wp-test-runner:latest';
const DOCKER_STUB = <<<'PHP'
#!/usr/bin/env php
<?php
$args = array_slice( $argv, 1 );
file_put_contents( getenv( 'DOCKER_LOG' ), json_encode( $args, JSON_THROW_ON_ERROR ) . PHP_EOL, FILE_APPEND | LOCK_EX );
if ( 'run' === ( $args[0] ?? null ) ) {
	$name = array_search( '--name', $args, true );
	if ( false !== $name ) {
		echo 'owned-' . $args[ $name + 1 ] . PHP_EOL;
	}
}
PHP;

function require_condition( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function run_process( array $command, string $directory, array $environment ): array {
	$process = proc_open( $command, [ [ 'pipe', 'r' ], [ 'pipe', 'w' ], [ 'pipe', 'w' ] ], $pipes, $directory, $environment );
	require_condition( is_resource( $process ), 'Could not start runner.' );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	return [ proc_close( $process ), $output, $error ];
}

function recorded_test_run( string $runner, string $root, string $stubs, string $log, array $arguments ): array {
	$environment = array_merge( getenv(), [
		'PATH'                                    => $stubs . ':' . getenv( 'PATH' ),
		'DOCKER_LOG'                              => $log,
		'DOCKER_OPTIONS'                          => '',
		'CI'                                      => '1',
		'TEST_MEMCACHED'                          => '',
		'NETWORK_NAME_OVERRIDE'                   => '',
		'MYSQL_HOST_OVERRIDE'                     => '',
		'WPVIP_PARSELY_INTEGRATION_TEST_MODE'     => '',
		'WPVIP_PARSELY_INTEGRATION_PLUGIN_VERSION' => '',
	] );
	[ $status, $output, $error ] = run_process( array_merge( [ $runner ], $arguments ), $root, $environment );
	require_condition( 0 === $status, "Unexpected runner status {$status}: {$output} {$error}" );
	$content = file_get_contents( $log );
	require_condition( false !== $content && '' !== trim( $content ), 'Docker recorder was not invoked.' );
	$events = array_map( static fn( string $line ): array => json_decode( $line, true, 512, JSON_THROW_ON_ERROR ), explode( "\n", trim( $content ) ) );
	$matches = [];
	foreach ( $events as $event ) {
		if ( 'run' === ( $event[0] ?? null ) && in_array( TEST_IMAGE, $event, true ) ) {
			$matches[] = $event;
		}
	}
	require_condition( 1 === count( $matches ), 'Expected exactly one downstream PHPUnit run.' );
	return $matches[0];
}

function image_tail( array $event ): array {
	$position = array_search( TEST_IMAGE, $event, true );
	require_condition( false !== $position, 'Missing downstream test image.' );
	return array_slice( $event, $position + 1 );
}

function option_value( array $event, string $option ): string {
	$position = array_search( $option, $event, true );
	require_condition( false !== $position && isset( $event[ $position + 1 ] ), "Missing {$option} Docker option." );
	return $event[ $position + 1 ];
}

function remove_owned_tree( string $directory ): void {
	foreach ( scandir( $directory ) as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$path = $directory . '/' . $entry;
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			remove_owned_tree( $path );
		} else {
			require_condition( unlink( $path ), "Could not remove owned file {$path}." );
		}
	}
	require_condition( rmdir( $directory ), "Could not remove owned directory {$directory}." );
}

function main(): void {
	$root = realpath( getenv( 'VIP_TEST_RUNNER_ROOT' ) ?: dirname( __DIR__ ) );
	require_condition( false !== $root, 'Runner root does not exist.' );
	$temporary = sys_get_temp_dir() . '/vip-runner-argv-' . bin2hex( random_bytes( 12 ) );
	require_condition( mkdir( $temporary, 0700 ), 'Could not create owned temporary directory.' );
	try {
		$stub = $temporary . '/docker';
		require_condition( false !== file_put_contents( $stub, DOCKER_STUB . "\n" ) && chmod( $stub, 0700 ), 'Could not create Docker recorder.' );
		$filter = 'test_example with data set "two words"';
		foreach ( [ $root . '/bin/test.sh', $root . '/drop-ins/wp-memcached/bin/test.sh' ] as $index => $runner ) {
			$default = recorded_test_run( $runner, $root, $temporary, $temporary . "/{$index}-default.jsonl", [] );
			require_condition( [] === image_tail( $default ), 'Default invocation added PHPUnit arguments.' );

			$ordinary = recorded_test_run( $runner, $root, $temporary, $temporary . "/{$index}-ordinary.jsonl", [ '--colors=never' ] );
			require_condition( [ '--colors=never' ] === image_tail( $ordinary ), 'Ordinary argument was changed.' );

			$quoted = recorded_test_run( $runner, $root, $temporary, $temporary . "/{$index}-quoted.jsonl", [
				'--docker-options', '--label audit=1 --cpus 2', '--filter', $filter,
			] );
			require_condition( [ '--filter', $filter ] === image_tail( $quoted ), 'Audited quoted filter was split or changed.' );
			require_condition( 'audit=1' === option_value( $quoted, '--label' ) && '2' === option_value( $quoted, '--cpus' ), 'Explicit Docker options were not split as documented.' );

			$wildcard = $filter . ' * [case]?';
			$literal = recorded_test_run( $runner, $root, $temporary, $temporary . "/{$index}-wildcard.jsonl", [ '--filter', $wildcard ] );
			require_condition( [ '--filter', $wildcard ] === image_tail( $literal ), 'Literal wildcard or quote characters were changed.' );
			echo substr( $runner, strlen( $root ) + 1 ) . ": exact default, ordinary, and quoted filter argv passed\n";
		}
	} finally {
		remove_owned_tree( $temporary );
	}
	require_condition( ! file_exists( $temporary ), 'Owned temporary directory remained.' );
}

try {
	main();
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
