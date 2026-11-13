<?php

namespace Automattic\VIP\Core\Privacy;

use Automattic\Test\Constant_Mocker;
use Automattic\Test\Utils\Captures_Errors;
use WP_UnitTestCase;
use WPDieException;

require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';

class Privacy_Export_Cleanup_Test extends WP_UnitTestCase {
	use Captures_Errors;

	/** @var float Seconds the stand-in usleep() below has "slept". */
	public static $slept = 0;

	/** @var string */
	private $file_path;

	public function tearDown(): void {
		if ( $this->file_path && is_file( $this->file_path ) ) {
			unlink( $this->file_path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}

		parent::tearDown();
	}

	/**
	 * Create an export request whose archive expired a day ago.
	 */
	private function create_expired_export(): int {
		$request_id      = wp_create_user_request( 'export@example.com', 'export_personal_data' );
		$file_name       = "export-{$request_id}.zip";
		$this->file_path = wp_privacy_exports_dir() . $file_name;

		wp_mkdir_p( wp_privacy_exports_dir() );
		file_put_contents( $this->file_path, 'zip' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		update_post_meta( $request_id, '_export_file_name', $file_name );
		update_post_meta( $request_id, '_vip_export_generated_time', time() - 4 * DAY_IN_SECONDS );
		update_post_meta( $request_id, '_export_data_grouped', [] );

		return $request_id;
	}

	public function test_cleanup_and_regeneration_do_not_overlap(): void {
		$request_id = $this->create_expired_export();

		// Cleanup skips an export while a regeneration holds its lock.
		$lock = _lock_export( $request_id );
		delete_old_export_files();
		$this->assertFileExists( $this->file_path );
		_unlock_export( $request_id, $lock );

		// A regeneration can't take the lock while cleanup deletes the file.
		$regeneration_lock = null;
		add_filter( 'wp_privacy_exports_dir', function ( $dir ) use ( $request_id, &$regeneration_lock ) {
			$regeneration_lock = _lock_export( $request_id );
			return $dir;
		} );
		delete_old_export_files();

		$this->assertFalse( $regeneration_lock );
		$this->assertFileDoesNotExist( $this->file_path );
		$this->assertSame( '', get_post_meta( $request_id, '_vip_export_generated_time', true ) );
	}

	public function test_generation_stops_without_touching_the_archive_when_it_cannot_lock(): void {
		$request_id     = $this->create_expired_export();
		$generated_time = get_post_meta( $request_id, '_vip_export_generated_time', true );

		// A cleanup is deleting this export.
		_lock_export( $request_id );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', [ $this, 'get_wp_die_handler' ] );
		$this->expectOutputRegex( '/"success":false/' );

		try {
			generate_personal_data_export_file( $request_id );
			$this->fail( 'Generation went ahead without the lock.' );
		} catch ( WPDieException $e ) {
			$this->assertFileExists( $this->file_path );
			$this->assertSame( $generated_time, get_post_meta( $request_id, '_vip_export_generated_time', true ) );
		}
	}

	public function test_generation_releases_its_lock_when_it_dies(): void {
		$request_id = $this->create_expired_export();
		add_action( 'wp_privacy_personal_data_export_file_created', static function () {
			throw new \RuntimeException( 'A callback failed.' );
		} );

		try {
			generate_personal_data_export_file( $request_id );
			$this->fail( 'The callback should have thrown.' );
		} catch ( \RuntimeException $e ) {
			// Don't let core close PHPUnit's output buffers.
			remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
			do_action( 'shutdown' );
		}

		$this->assertNotFalse( _lock_export( $request_id ) );
	}

	public function test_unlock_keeps_a_lock_that_expired_and_was_taken_over(): void {
		$first_lock = _lock_export( 1 );
		wp_cache_delete( 1, EXPORT_LOCK_GROUP );
		_lock_export( 1 );

		_unlock_export( 1, $first_lock );

		$this->assertFalse( _lock_export( 1 ) );
	}

	public function data_files_service_delete_failures(): array {
		return [
			'file is already gone' => [ 404, false ],
			'file is still there'  => [ 200, true ],
			'check failed'         => [ 500, true ],
		];
	}

	/**
	 * @dataProvider data_files_service_delete_failures
	 */
	public function test_cleanup_retries_a_failed_files_service_delete_unless_the_file_is_gone( int $file_check_status, bool $retries ): void {
		$request_id = $this->create_expired_export();

		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'FILE_SERVICE_URI', 'https://files.example.com' );
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 1 );
		Constant_Mocker::define( 'FILES_ACCESS_TOKEN', 'token' );

		// A subdirectory multisite subsite's URLs start with the subsite's directory.
		add_filter( 'wp_privacy_exports_url', static function () {
			return 'https://example.com/subsite/wp-content/uploads/sites/2/wp-personal-data-exports/';
		} );

		add_filter( 'pre_http_request', static function ( $response, $args ) use ( $file_check_status ) {
			return [
				'response' => [ 'code' => 'DELETE' === $args['method'] ? 500 : $file_check_status ],
				'body'     => '{}',
			];
		}, 10, 2 );

		$this->capture_errors( __NAMESPACE__ . '\delete_old_export_files' );

		// The timestamp is what makes the next run select this export again.
		$this->assertSame( $retries, metadata_exists( 'post', $request_id, '_vip_export_generated_time' ) );
	}
}

// Stand-ins for the clock `_lock_export()` waits on, so waiting out a held lock doesn't slow the tests down.
function usleep( $microseconds ) {
	Privacy_Export_Cleanup_Test::$slept += $microseconds / 1000000;
}

function microtime( $as_float = false ) {
	return \microtime( $as_float ) + Privacy_Export_Cleanup_Test::$slept;
}
