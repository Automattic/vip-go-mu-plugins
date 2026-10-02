<?php

// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions -- Creates and removes task-owned temporary plugin fixtures.

use PHPUnit\Framework\TestCase;

class Jetpack_Loader_Test extends TestCase {
	public function scenarios(): array {
		return [
			'6.7 absent pin and incompatible fallback' => [ '6.7', '', [ 'jetpack' => '7.0' ], null, true ],
			'6.8 absent pin and incompatible fallback' => [ '6.8', '', [ 'jetpack' => '7.0' ], null, true ],
			'compatible default'                       => [
				'6.8',
				'',
				[
					'jetpack-default' => '6.8',
					'jetpack'         => '7.0',
				],
				'default',
				false,
			],
			'skip incompatible default before compatible fallback' => [
				'6.8',
				'',
				[
					'jetpack-default' => '7.0',
					'jetpack'         => '6.6',
				],
				'',
				false,
			],
			'local override priority'                  => [
				'6.8',
				'local',
				[
					'client/jetpack'  => '6.8',
					'jetpack-default' => '6.8',
				],
				'local',
				false,
			],
			'pinned priority'                          => [
				'6.8',
				'pinned',
				[
					'jetpack-pinned'  => '6.8',
					'jetpack-default' => '6.8',
				],
				'pinned',
				false,
			],
			'incompatible PHP'                         => [ '6.8', '', [ 'jetpack-default' => '6.8|99.0' ], null, true ],
			'hosted default keeps existing selection'  => [ '6.8', '', [ 'jetpack-default' => '7.0' ], 'default', false, 'production' ],
			'hosted pin keeps existing selection'      => [ '6.8', 'pinned', [ 'jetpack-pinned' => '7.0' ], 'pinned', false, 'staging' ],
			'explicit skip'                            => [ '6.8', 'skip', [ 'jetpack' => '7.0' ], 'none', false ],
		];
	}

	/** @dataProvider scenarios */
	public function test_loader_preflights_candidates( $wp_version, $mode, $plugins, $loaded, $warns, $environment = 'local' ): void {
		$directory = sys_get_temp_dir() . '/vip-jetpack-test-' . uniqid();
		mkdir( $directory );
		mkdir( $directory . '/vip-jetpack' );
		copy( __DIR__ . '/../jetpack.php', $directory . '/loader.php' );
		file_put_contents( $directory . '/vip-jetpack/vip-jetpack.php', '<?php $GLOBALS["integration_loaded"] = true;' );
		$files       = [ $directory . '/loader.php', $directory . '/vip-jetpack/vip-jetpack.php' ];
		$directories = [ $directory . '/vip-jetpack' ];
		try {
			foreach ( $plugins as $path => $requires ) {
				$parts      = explode( '|', $requires );
				$plugin_dir = $directory . '/' . $path;
				mkdir( $plugin_dir, 0700, true );
				$directories[] = $plugin_dir;
				$files[]       = $plugin_dir . '/jetpack.php';
				file_put_contents( $plugin_dir . '/jetpack.php', "<?php\n/**\n * Requires at least: {$parts[0]}\n * Requires PHP: " . ( $parts[1] ?? '7.4' ) . "\n */\ndefine('JETPACK__VERSION', '$path');\nclass Jetpack {}\n" );
			}
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Loader isolation.
			$process = proc_open( [ PHP_BINARY, __DIR__ . '/fixtures/jetpack/load.php', $directory, $wp_version, $mode, $environment ], [
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			], $pipes );
			$output  = stream_get_contents( $pipes[1] );
			$error   = stream_get_contents( $pipes[2] );
			fclose( $pipes[1] );
			fclose( $pipes[2] );
			$this->assertSame( 0, proc_close( $process ), $error );
			$result = json_decode( $output, true );
			$this->assertSame( $loaded, $result['loaded'], $output );
			$this->assertSame( null !== $loaded && 'none' !== $loaded, $result['integration'] );
			$this->assertSame( $warns, count( $result['warnings'] ) > 0 );
			if ( null === $loaded ) {
				$this->assertNull( $result['plugin'], 'Incompatible plugin must not define constants.' );
				$this->assertStringContainsString( 'compatible Jetpack', $result['warnings'][0] );
			}
		} finally {
			foreach ( $files as $file ) {
				unlink( $file );
			}
			foreach ( array_reverse( $directories ) as $dir ) {
				rmdir( $dir );
			}
			if ( is_dir( $directory . '/client' ) ) {
				rmdir( $directory . '/client' );
			}
			rmdir( $directory );
		}
	}
}
