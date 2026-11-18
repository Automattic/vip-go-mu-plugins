#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Check runner resource ownership without starting Docker or PHPUnit. */

const DOCKER_STUB = <<<'PHP'
#!/usr/bin/env php
<?php
$args = array_slice( $argv, 1 );
file_put_contents( getenv( 'DOCKER_LOG' ), json_encode( $args, JSON_THROW_ON_ERROR ) . PHP_EOL, FILE_APPEND | LOCK_EX );
if ( 'run' === ( $args[0] ?? null ) ) {
	$name = array_search( '--name', $args, true );
	if ( false !== $name ) {
		echo 'owned-' . $args[ $name + 1 ] . PHP_EOL;
	} elseif ( '1' === getenv( 'FAIL_TEST_RUN' ) ) {
		exit( 7 );
	}
}
PHP;

const DATE_STUB = <<<'PHP'
#!/usr/bin/env php
<?php
if ( '+%s' === ( $argv[1] ?? null ) ) {
	echo "1700000000\n";
} elseif ( '+%s000' === ( $argv[1] ?? null ) ) {
	echo "1700000000000\n";
} else {
	exit( 2 );
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

function recorded_events( string $log ): array {
	$content = file_get_contents( $log );
	require_condition( false !== $content && '' !== trim( $content ), 'Docker recorder was not invoked.' );
	return array_map( static fn( string $line ): array => json_decode( $line, true, 512, JSON_THROW_ON_ERROR ), explode( "\n", trim( $content ) ) );
}

function run_runner( string $runner, string $root, string $stubs, string $log, bool $fail = false, bool $overrides = false ): array {
	$environment = array_merge( getenv(), [
		'PATH'                                    => $stubs . ':' . getenv( 'PATH' ),
		'DOCKER_LOG'                              => $log,
		'DOCKER_OPTIONS'                          => '',
		'FAIL_TEST_RUN'                           => $fail ? '1' : '0',
		'CI'                                      => '1',
		'TEST_MEMCACHED'                          => '',
		'NETWORK_NAME_OVERRIDE'                   => '',
		'MYSQL_HOST_OVERRIDE'                     => '',
		'WPVIP_PARSELY_INTEGRATION_TEST_MODE'     => '',
		'WPVIP_PARSELY_INTEGRATION_PLUGIN_VERSION' => '',
	] );
	$command = [ $runner ];
	if ( $runner === $root . '/bin/test.sh' ) {
		$command[] = '--memcached';
	}
	if ( $overrides ) {
		array_push( $command, '--network', 'caller-network', '--dbhost', 'caller-db' );
	}
	[ $status, $output, $error ] = run_process( $command, $root, $environment );
	require_condition( ( $fail ? 7 : 0 ) === $status, "Unexpected runner status {$status}: {$output} {$error}" );
	return recorded_events( $log );
}

function event_argument( array $event, string $option ): string {
	$position = array_search( $option, $event, true );
	require_condition( false !== $position && isset( $event[ $position + 1 ] ), "Missing {$option} in Docker argv." );
	return $event[ $position + 1 ];
}

function check_events( array $events, bool $overrides, int $cache_count ): array {
	$created = [];
	$removed = [];
	$named   = [];
	$deleted = [];
	$tests   = [];
	foreach ( $events as $event ) {
		if ( [ 'network', 'create' ] === array_slice( $event, 0, 2 ) ) {
			$created[] = $event[2];
		} elseif ( [ 'network', 'rm' ] === array_slice( $event, 0, 2 ) ) {
			$removed[] = $event[2];
		} elseif ( [ 'rm', '-f' ] === array_slice( $event, 0, 2 ) ) {
			$deleted[] = $event[2];
		} elseif ( 'run' === ( $event[0] ?? null ) ) {
			if ( in_array( '--name', $event, true ) ) {
				$named[] = $event;
			} else {
				$tests[] = $event;
			}
		}
	}
	require_condition( 1 === count( $tests ), 'Expected one downstream test run.' );
	require_condition( $cache_count + ( $overrides ? 0 : 1 ) === count( $named ), 'Unexpected owned container count.' );
	$names = array_map( static fn( array $event ): string => event_argument( $event, '--name' ), $named );
	require_condition( count( $names ) === count( array_unique( $names ) ), 'Owned container names collided within one run.' );
	$expected_deleted = array_map( static fn( string $name ): string => 'owned-' . $name, $names );
	sort( $expected_deleted );
	sort( $deleted );
	require_condition( $expected_deleted === $deleted, 'Cleanup removed an unowned container or missed an owned one.' );
	if ( $overrides ) {
		require_condition( [] === $created && [] === $removed, 'Caller-owned network was changed.' );
		require_condition( ! in_array( 'caller-db', $deleted, true ), 'Caller-owned database was removed.' );
		$network = 'caller-network';
		$suffix  = substr( $names[0], strpos( $names[0], '-' ) + 1 );
	} else {
		require_condition( 1 === count( $created ) && $created === $removed, 'Owned network was not removed exactly once.' );
		$network = $created[0];
		require_condition( str_starts_with( $network, 'tests-' ), 'Unexpected owned network name.' );
		$suffix = substr( $network, strlen( 'tests-' ) );
	}
	foreach ( array_merge( $named, $tests ) as $event ) {
		require_condition( $network === event_argument( $event, '--network' ), 'Docker call used a different network.' );
	}
	foreach ( $names as $name ) {
		require_condition( str_ends_with( $name, '-' . $suffix ), 'Owned containers did not share one run identity.' );
	}
	return [ $created, $names ];
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
	$temporary = sys_get_temp_dir() . '/vip-runner-ownership-' . bin2hex( random_bytes( 12 ) );
	require_condition( mkdir( $temporary, 0700 ), 'Could not create owned temporary directory.' );
	try {
		foreach ( [ 'docker' => DOCKER_STUB, 'date' => DATE_STUB ] as $name => $source ) {
			$path = $temporary . '/' . $name;
			require_condition( false !== file_put_contents( $path, $source . "\n" ) && chmod( $path, 0700 ), "Could not create {$name} stub." );
		}
		foreach ( [ $root . '/bin/test.sh' => 1, $root . '/drop-ins/wp-memcached/bin/test.sh' => 2 ] as $runner => $cache_count ) {
			$label     = 1 === $cache_count ? 'root' : 'memcached';
			$directory = $temporary . '/' . $label;
			require_condition( mkdir( $directory, 0700 ), 'Could not create log directory.' );
			$first  = check_events( run_runner( $runner, $root, $temporary, $directory . '/first.jsonl' ), false, $cache_count );
			$second = check_events( run_runner( $runner, $root, $temporary, $directory . '/second.jsonl' ), false, $cache_count );
			require_condition( $first[0] !== $second[0] && [] === array_intersect( $first[1], $second[1] ), 'Same-clock runs shared a resource identity.' );
			check_events( run_runner( $runner, $root, $temporary, $directory . '/failed.jsonl', true ), false, $cache_count );
			check_events( run_runner( $runner, $root, $temporary, $directory . '/override.jsonl', false, true ), true, $cache_count );
			echo substr( $runner, strlen( $root ) + 1 ) . ": same-clock identities and owned cleanup passed\n";
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
