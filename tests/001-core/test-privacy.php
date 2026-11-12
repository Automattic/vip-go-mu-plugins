<?php

namespace Automattic\VIP\Core\Privacy;

use WP_UnitTestCase;

class Privacy_Export_Cleanup_Test extends WP_UnitTestCase {
	private int $request_id;
	private string $file_path;

	public function setUp(): void {
		parent::setUp();

		$file_name        = 'wp-personal-data-file-export-at-example-com-test.zip';
		$this->request_id = wp_create_user_request( 'export@example.com', 'export_personal_data' );
		$this->file_path  = wp_privacy_exports_dir() . $file_name;

		wp_mkdir_p( wp_privacy_exports_dir() );
		file_put_contents( $this->file_path, 'zip' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		update_post_meta( $this->request_id, '_export_file_name', $file_name );
		update_post_meta( $this->request_id, '_vip_export_generated_time', time() - 4 * DAY_IN_SECONDS );
	}

	public function tearDown(): void {
		if ( is_file( $this->file_path ) ) {
			unlink( $this->file_path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}

		parent::tearDown();
	}

	public function test_cleanup_skips_export_being_regenerated(): void {
		// A regeneration holds the lock until its new archive is uploaded.
		_lock_export( $this->request_id );
		delete_old_export_files();

		$this->assertFileExists( $this->file_path );

		_unlock_export( $this->request_id );
		delete_old_export_files();

		$this->assertFileDoesNotExist( $this->file_path );
		$this->assertSame( '', get_post_meta( $this->request_id, '_vip_export_generated_time', true ) );
	}

	public function test_regeneration_waits_for_cleanup_delete(): void {
		$regeneration_locked = null;

		// Try to start a regeneration after the cleanup re-read the timestamp, before it deletes the file.
		$regenerate = function ( $value, $object_id, $meta_key ) use ( &$regeneration_locked ) {
			if ( $this->request_id === $object_id && '_export_file_name' === $meta_key ) {
				$regeneration_locked = _lock_export( $this->request_id );
			}

			return $value;
		};

		add_filter( 'get_post_metadata', $regenerate, 10, 3 );
		delete_old_export_files();
		remove_filter( 'get_post_metadata', $regenerate, 10 );

		$this->assertFalse( $regeneration_locked );
		$this->assertFileDoesNotExist( $this->file_path );
		$this->assertTrue( _lock_export( $this->request_id ) );
	}
}
