<?php

namespace Automattic\VIP\Core\Privacy;

use WP_UnitTestCase;

class Privacy_Export_Cleanup_Test extends WP_UnitTestCase {
	public function test_cleanup_and_regeneration_do_not_overlap(): void {
		$request_id = wp_create_user_request( 'export@example.com', 'export_personal_data' );
		$file_path  = wp_privacy_exports_dir() . 'export.zip';

		wp_mkdir_p( wp_privacy_exports_dir() );
		file_put_contents( $file_path, 'zip' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		update_post_meta( $request_id, '_export_file_name', 'export.zip' );
		update_post_meta( $request_id, '_vip_export_generated_time', time() - 4 * DAY_IN_SECONDS );

		// Cleanup skips an export while a regeneration holds its lock.
		_lock_export( $request_id );
		delete_old_export_files();
		$this->assertFileExists( $file_path );
		_unlock_export( $request_id );

		// A regeneration can't take the lock while cleanup deletes the file.
		$regeneration_locked = null;
		add_filter( 'wp_privacy_exports_dir', function ( $dir ) use ( $request_id, &$regeneration_locked ) {
			$regeneration_locked = _lock_export( $request_id );
			return $dir;
		} );
		delete_old_export_files();

		$this->assertFalse( $regeneration_locked );
		$this->assertFileDoesNotExist( $file_path );
		$this->assertSame( '', get_post_meta( $request_id, '_vip_export_generated_time', true ) );
	}
}
