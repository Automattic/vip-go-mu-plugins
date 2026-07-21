<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Histogram;
use Prometheus\RegistryInterface;

/**
 * Collects VIP File Service I/O metrics, instrumented at the stream wrapper.
 *
 * Upload (write) sizes are observed at event-time via record_write().
 * Reads (ingress) are accumulated per-request via record_read() and drained
 * into histograms by collect_metrics() at shutdown.
 */
class Filesystem_Stats_Collector implements CollectorInterface {

	/** Upload (write) size buckets, in bytes: 256K -> 256M. 8M == warning threshold. */
	private const UPLOAD_SIZE_BUCKETS = [
		262144,
		524288,
		1048576,
		2097152,
		4194304,
		8388608,
		16777216,
		33554432,
		67108864,
		134217728,
		268435456,
	];

	/** Per-request total read bytes buckets: 1M -> 1G. */
	private const READ_BYTES_BUCKETS = [
		1048576,
		4194304,
		16777216,
		67108864,
		134217728,
		268435456,
		536870912,
		1073741824,
	];

	/** Per-request read fetch-count buckets. */
	private const READ_FILES_BUCKETS = [ 1, 2, 5, 10, 25, 50, 100, 250 ];

	private static ?Histogram $upload_bytes       = null;
	private static ?Histogram $request_read_bytes = null;
	private static ?Histogram $request_read_files = null;

	/** Per-request read accumulators. Drained and reset in collect_metrics(); PHP clears static state between requests. */
	private static int $read_bytes_acc = 0;
	private static int $read_files_acc = 0;

	public function initialize( RegistryInterface $registry ): void {
		self::$upload_bytes = $registry->getOrRegisterHistogram(
			'filesystem',
			'upload_bytes',
			'Distribution of file sizes uploaded to the VIP File Service, in bytes.',
			[ 'type', 'mime_type' ],
			self::UPLOAD_SIZE_BUCKETS
		);

		self::$request_read_bytes = $registry->getOrRegisterHistogram(
			'filesystem',
			'request_read_bytes',
			'Total bytes fetched from the VIP File Service per request.',
			[],
			self::READ_BYTES_BUCKETS
		);

		self::$request_read_files = $registry->getOrRegisterHistogram(
			'filesystem',
			'request_read_files',
			'Number of VIP File Service fetches per request.',
			[],
			self::READ_FILES_BUCKETS
		);
	}

	/**
	 * Record a write/upload to the File Service. Called from the stream wrapper.
	 * Must never throw.
	 */
	public static function record_write( int $size, string $path ): void {
		try {
			if ( null === self::$upload_bytes || $size <= 0 ) {
				return;
			}

			[ $type, $mime ] = self::classify_path( $path );
			self::$upload_bytes->observe( $size, [ $type, $mime ] );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Telemetry must never break a file operation.
		}
	}

	/**
	 * Accumulate a remote fetch from the File Service. Called from the stream
	 * wrapper. Per-request totals are drained in collect_metrics(). Must never throw.
	 */
	public static function record_read( int $size ): void {
		if ( $size < 0 ) {
			return;
		}

		self::$read_bytes_acc += $size;
		++self::$read_files_acc;
	}

	public function collect_metrics(): void {
		if ( self::$read_files_acc < 1 ) {
			return;
		}

		if ( null !== self::$request_read_bytes ) {
			self::$request_read_bytes->observe( self::$read_bytes_acc, [] );
		}

		if ( null !== self::$request_read_files ) {
			self::$request_read_files->observe( self::$read_files_acc, [] );
		}

		// Reset so a second collect_metrics() in the same request does not double-count.
		self::$read_bytes_acc = 0;
		self::$read_files_acc = 0;
	}

	public function process_metrics(): void {
		/* Do nothing */
	}

	/**
	 * Map a file path to [ coarse_type, mime_type ], bounded to WP's allowed
	 * MIME set. Unknown/garbage -> [ 'other', 'other' ].
	 */
	private static function classify_path( string $path ): array {
		if ( ! function_exists( 'wp_check_filetype' ) ) {
			return [ 'other', 'other' ];
		}

		$info = wp_check_filetype( $path );
		$mime = is_array( $info ) ? (string) ( $info['type'] ?? '' ) : '';

		$slash = strpos( $mime, '/' );
		if ( '' === $mime || false === $slash ) {
			return [ 'other', 'other' ];
		}

		return [ substr( $mime, 0, $slash ), $mime ];
	}
}
