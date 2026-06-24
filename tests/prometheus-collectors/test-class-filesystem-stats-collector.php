<?php

namespace Automattic\VIP\Prometheus;

use PHPUnit\Framework\MockObject\MockObject;
use Prometheus\Histogram;
use Prometheus\RegistryInterface;
use WP_UnitTestCase;

require_once __DIR__ . '/../../prometheus-collectors/class-filesystem-stats-collector.php';

class Test_Filesystem_Stats_Collector extends WP_UnitTestCase {

	public function tearDown(): void {
		// Reset the collector's static state so tests are isolated.
		$ref = new \ReflectionClass( Filesystem_Stats_Collector::class );
		foreach ( [ 'upload_bytes', 'request_read_bytes', 'request_read_files' ] as $prop ) {
			$p = $ref->getProperty( $prop );
			$p->setAccessible( true );
			$p->setValue( null, null );
		}
		foreach ( [ 'read_bytes_acc', 'read_files_acc' ] as $prop ) {
			$p = $ref->getProperty( $prop );
			$p->setAccessible( true );
			$p->setValue( null, 0 );
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

	public function test_record_write_observes_size_and_labels(): void {
		[ , $histogram ] = $this->init_with_histogram_spy();

		$histogram->expects( $this->once() )
			->method( 'observe' )
			->with( 2048, [ 'image', 'image/jpeg' ] );

		Filesystem_Stats_Collector::record_write( 2048, 'wp-content/uploads/2026/06/photo.jpg' );
	}

	public function test_record_write_unknown_extension_is_other(): void {
		[ , $histogram ] = $this->init_with_histogram_spy();

		$histogram->expects( $this->once() )
			->method( 'observe' )
			->with( 2048, [ 'other', 'other' ] );

		Filesystem_Stats_Collector::record_write( 2048, 'wp-content/uploads/file.unknownext' );
	}

	public function test_record_write_zero_size_is_skipped(): void {
		[ , $histogram ] = $this->init_with_histogram_spy();

		$histogram->expects( $this->never() )->method( 'observe' );

		Filesystem_Stats_Collector::record_write( 0, 'wp-content/uploads/empty.jpg' );
	}

	public function test_record_write_without_initialize_is_noop(): void {
		// No initialize() this test; tearDown nulled the static handle.
		Filesystem_Stats_Collector::record_write( 1024, 'wp-content/uploads/a.jpg' );
		$this->assertTrue( true ); // Reaching here means no error/exception.
	}

	public function test_record_write_swallows_observe_exception(): void {
		[ , $histogram ] = $this->init_with_histogram_spy();
		$histogram->method( 'observe' )->willThrowException( new \RuntimeException( 'boom' ) );

		// Must not throw.
		Filesystem_Stats_Collector::record_write( 1024, 'wp-content/uploads/a.jpg' );
		$this->assertTrue( true );
	}
}
