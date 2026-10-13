<?php

namespace Automattic\VIP\Prometheus;

use PHPUnit\Framework\MockObject\MockObject;
use Prometheus\Histogram;
use Prometheus\RegistryInterface;
use WP_UnitTestCase;
use Automattic\VIP\Files\API_Client;
use Automattic\VIP\Files\VIP_Stream_Wrapper_Fixture;

require_once __DIR__ . '/../../prometheus-collectors/class-filesystem-stats-collector.php';
require_once __DIR__ . '/../files/trait-vip-stream-wrapper-fixture.php';

class Test_Filesystem_Stats_Collector extends WP_UnitTestCase {
	use VIP_Stream_Wrapper_Fixture;

	public function tearDown(): void {
		// Reset the collector's static state so tests are isolated. No setAccessible():
		// reflection has unrestricted access since PHP 8.1 and the call is deprecated in 8.5.
		$ref = new \ReflectionClass( Filesystem_Stats_Collector::class );
		foreach ( [ 'upload_bytes', 'request_read_bytes', 'request_read_files' ] as $prop ) {
			$ref->getProperty( $prop )->setValue( null, null );
		}
		foreach ( [ 'read_bytes_acc', 'read_files_acc' ] as $prop ) {
			$ref->getProperty( $prop )->setValue( null, 0 );
		}

		parent::tearDown();
	}

	/**
	 * Initialize the collector with a single mock Histogram returned for every
	 * getOrRegisterHistogram() call. Returns [ collector, histogram ].
	 */
	private function init_with_histogram_spy(): array {
		/** @var MockObject&Histogram $histogram */
		$histogram = $this->getMockBuilder( Histogram::class )
			->disableOriginalConstructor()
			->getMock();

		/** @var MockObject&RegistryInterface $registry */
		$registry = $this->getMockBuilder( RegistryInterface::class )->getMock();
		$registry->method( 'getOrRegisterHistogram' )->willReturn( $histogram );

		$collector = new Filesystem_Stats_Collector();
		$collector->initialize( $registry );

		return [ $collector, $histogram ];
	}

	/**
	 * Uploads observe their size, labelled by file type and mime type; empty writes are skipped.
	 */
	public function get_test_data__record_write(): array {
		return [
			'image'             => [ 2048, 'wp-content/uploads/2026/06/photo.jpg', [ 'image', 'image/jpeg' ] ],
			'unknown extension' => [ 2048, 'wp-content/uploads/file.unknownext', [ 'other', 'other' ] ],
			'zero size'         => [ 0, 'wp-content/uploads/empty.jpg', null ],
		];
	}

	/**
	 * @dataProvider get_test_data__record_write
	 */
	public function test_record_write( int $size, string $path, ?array $expected_labels ): void {
		[ , $histogram ] = $this->init_with_histogram_spy();

		if ( null === $expected_labels ) {
			$histogram->expects( $this->never() )->method( 'observe' );
		} else {
			$histogram->expects( $this->once() )
				->method( 'observe' )
				->with( $size, $expected_labels );
		}

		Filesystem_Stats_Collector::record_write( $size, $path );
	}

	public function test_record_write_without_initialize_is_noop(): void {
		// No initialize() this test; tearDown nulled the static handle.
		$this->expectNotToPerformAssertions(); // Reaching the end means no error/exception.

		Filesystem_Stats_Collector::record_write( 1024, 'wp-content/uploads/a.jpg' );
	}

	public function test_record_write_swallows_observe_exception(): void {
		[ , $histogram ] = $this->init_with_histogram_spy();
		$histogram->expects( $this->once() )
			->method( 'observe' )
			->willThrowException( new \RuntimeException( 'boom' ) );

		// Must not throw.
		Filesystem_Stats_Collector::record_write( 1024, 'wp-content/uploads/a.jpg' );
	}

	/**
	 * Initialize with distinct mock histograms per metric name.
	 * Returns [ collector, [ 'upload' => H, 'read_bytes' => H, 'read_files' => H ] ].
	 */
	private function init_with_named_spies(): array {
		$mk = function () {
			return $this->getMockBuilder( Histogram::class )
				->disableOriginalConstructor()
				->getMock();
		};

		$spies = [
			'upload'     => $mk(),
			'read_bytes' => $mk(),
			'read_files' => $mk(),
		];

		/** @var MockObject&RegistryInterface $registry */
		$registry = $this->getMockBuilder( RegistryInterface::class )->getMock();
		$registry->method( 'getOrRegisterHistogram' )->willReturnCallback(
			function ( $namespace, $name ) use ( $spies ) {
				if ( 'request_read_bytes' === $name ) {
					return $spies['read_bytes'];
				}
				if ( 'request_read_files' === $name ) {
					return $spies['read_files'];
				}
				return $spies['upload'];
			}
		);

		$collector = new Filesystem_Stats_Collector();
		$collector->initialize( $registry );

		return [ $collector, $spies ];
	}

	public function test_reads_accumulate_and_drain_once(): void {
		[ $collector, $spies ] = $this->init_with_named_spies();

		Filesystem_Stats_Collector::record_read( 100 );
		Filesystem_Stats_Collector::record_read( 50 );

		$spies['read_bytes']->expects( $this->once() )->method( 'observe' )->with( 150, [] );
		$spies['read_files']->expects( $this->once() )->method( 'observe' )->with( 2, [] );

		$collector->collect_metrics();
	}

	public function test_collect_metrics_observes_nothing_without_reads(): void {
		[ $collector, $spies ] = $this->init_with_named_spies();

		$spies['read_bytes']->expects( $this->never() )->method( 'observe' );
		$spies['read_files']->expects( $this->never() )->method( 'observe' );

		$collector->collect_metrics();
	}

	public function test_collect_metrics_does_not_double_drain(): void {
		[ $collector, $spies ] = $this->init_with_named_spies();

		Filesystem_Stats_Collector::record_read( 100 );

		// observe must fire exactly once across two collect_metrics() calls.
		$spies['read_bytes']->expects( $this->once() )->method( 'observe' )->with( 100, [] );
		$spies['read_files']->expects( $this->once() )->method( 'observe' )->with( 1, [] );

		$collector->collect_metrics();
		$collector->collect_metrics();
	}

	public function test_collector_is_registered_via_filter(): void {
		$collectors = apply_filters( 'vip_prometheus_collectors', [] );

		$this->assertArrayHasKey( 'filesystem', $collectors );
		$this->assertInstanceOf( Filesystem_Stats_Collector::class, $collectors['filesystem'] );
	}

	public function test_stream_flush_records_upload(): void {
		[ , $histogram ] = $this->init_with_histogram_spy();

		$histogram->expects( $this->once() )
			->method( 'observe' )
			->with( 4, [ 'image', 'image/jpeg' ] );

		/** @var MockObject&API_Client $client */
		$client = $this->createMock( API_Client::class );
		// New file: open fetches, gets file-not-found, creates empty resource.
		$client->method( 'get_file' )->willReturn( new \WP_Error( 'file-not-found', 'nope' ) );
		// Close flushes: upload succeeds (returns a truthy filename, not WP_Error).

		$client->expects( $this->once() )->method( 'upload_file' )
			->willReturnCallback( function ( $source, $destination ) {
				$this->assertSame( 'wp-content/uploads/x.jpg', $destination );
				$this->assertFileExists( $source );
				// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local staged upload.
				$this->assertSame( 'data', file_get_contents( $source ) );
				return '/wp-content/uploads/x.jpg';
			} );

		$this->register_vip_stream_wrapper( $client );

		// 4 bytes, written through the vip:// wrapper to exercise stream_flush().
		$this->assertSame( 4, file_put_contents( 'vip://wp-content/uploads/x.jpg', 'data' ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
	}

	public function test_stream_open_read_accumulates_and_drains(): void {
		[ $collector, $spies ] = $this->init_with_named_spies();

		// A real temp file with 100 bytes to be "fetched" from the service.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam
		$tmp = tempnam( sys_get_temp_dir(), 'vipfs' );
		file_put_contents( $tmp, str_repeat( 'x', 100 ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		/** @var MockObject&API_Client $client */
		$client = $this->createMock( API_Client::class );
		$client->method( 'get_file' )->willReturn( $tmp );

		$this->register_vip_stream_wrapper( $client );

		$contents = file_get_contents( 'vip://wp-content/uploads/y.jpg' ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile
		$this->assertSame( 100, strlen( $contents ) );

		$spies['read_bytes']->expects( $this->once() )->method( 'observe' )->with( 100, [] );
		$spies['read_files']->expects( $this->once() )->method( 'observe' )->with( 1, [] );

		$collector->collect_metrics();

		unlink( $tmp ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
	}
}
