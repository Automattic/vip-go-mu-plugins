<?php

namespace Automattic\VIP\Admin_Notice;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../admin-notice/class-admin-notice-controller.php';
require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';

class Admin_Notice_Controller_Test extends TestCase {

	public static $mock_global_functions;

	public function setUp(): void {
		self::$mock_global_functions = $this->getMockBuilder( self::class )
			->setMethods( [ 'add_user_meta', 'get_user_meta', 'delete_user_meta' ] )
			->getMock();
	}

	public function add_user_meta( int $user_id, string $meta_key, $meta_value, bool $unique = false ) {
	}
	public function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
	}
	public function delete_user_meta( int $user_id, string $meta_key, $meta_value ) {
	}

	public function mayby_clean_stale_dismissed_notices_data() {

		return [
			[ 101, true ],
			[ -1, false ],
		];
	}

	/**
	 * @dataProvider mayby_clean_stale_dismissed_notices_data
	 */
	public function test__mayby_clean_stale_dismissed_notices( $limit_value, $expect_called ) {
		Admin_Notice_Controller::$stale_dismiss_cleanup_value = $limit_value;

		$partially_mocked_controller = $this->getMockBuilder( Admin_Notice_Controller::class )
			->setMethods( [ 'clean_stale_dismissed_notices' ] )
			->getMock();

		$partially_mocked_controller->expects( $expect_called ? $this->once() : $this->never() )
			->method( 'clean_stale_dismissed_notices' );

		$partially_mocked_controller->maybe_clean_stale_dismissed_notices();
	}

	public function clean_stale_dismissed_notices_data() {

		return [
			[ [], [ 'a' ], [] ],
			[ [ 'a' ], [ 'a' ], [] ],
			[ [ 'a', 'b', 'c' ], [ 'a' ], [ 'b', 'c' ] ],
			[ [ 'a' ], [ 'b', 'c' ], [ 'a' ] ],
		];
	}

	/**
	 * @dataProvider clean_stale_dismissed_notices_data
	 */
	public function test__clean_stale_dismissed_notices( $dismissed_notices, $registered_notices, $expected_deletion ) {
		$controller = new Admin_Notice_Controller();

		self::$mock_global_functions->method( 'get_user_meta' )
			->willReturn( $dismissed_notices );

		foreach ( $registered_notices as $identifier ) {
			$controller->add( new Admin_Notice( 'hi', [], $identifier ) );
		}

		self::$mock_global_functions->expects( $this->exactly( count( $expected_deletion ) ) )
			->method( 'delete_user_meta' );

		$controller->clean_stale_dismissed_notices();
	}
}

/**
 * Overwriting global function
 */
function add_user_meta( int $user_id, string $meta_key, $meta_value, bool $unique = false ) {
	return is_null( Admin_Notice_Controller_Test::$mock_global_functions ) ? null : Admin_Notice_Controller_Test::$mock_global_functions->add_user_meta( $user_id, $meta_key, $meta_value, $unique );
}
function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
	return is_null( Admin_Notice_Controller_Test::$mock_global_functions ) ? null : Admin_Notice_Controller_Test::$mock_global_functions->get_user_meta( $user_id, $key, $single );
}
function delete_user_meta( int $user_id, string $meta_key, $meta_value ) {
	return is_null( Admin_Notice_Controller_Test::$mock_global_functions ) ? null : Admin_Notice_Controller_Test::$mock_global_functions->delete_user_meta( $user_id, $meta_key, $meta_value );
}
