<?php

namespace Automattic\VIP\Admin_Notice;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

require_once __DIR__ . '/../../admin-notice/class-admin-notice-controller.php';
require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';

class Admin_Notice_Controller_Test extends WP_UnitTestCase {

	public static $mock_global_functions;
	private $previous_cleanup_value;

	public function setUp(): void {
		parent::setUp();
		$this->previous_cleanup_value = Admin_Notice_Controller::$stale_dismiss_cleanup_value;
		wp_set_current_user( self::factory()->user->create() );
		self::$mock_global_functions = $this->getMockBuilder( self::class )
			->addMethods( [ 'add_user_meta', 'get_user_meta', 'delete_user_meta' ] )
			->getMock();
	}

	/**
	 * Restore the namespace boundary and cleanup configuration.
	 */
	public function tearDown(): void {
		self::$mock_global_functions                          = null;
		Admin_Notice_Controller::$stale_dismiss_cleanup_value = $this->previous_cleanup_value;
		parent::tearDown();
	}

	/**
	 * Check persisted survivors and isolation between users and metadata keys.
	 */
	public function test_cleanup_preserves_active_dismissals_and_other_users(): void {
		self::$mock_global_functions = null;
		$user_id                     = get_current_user_id();
		$other_id                    = self::factory()->user->create();
		$key                         = Admin_Notice_Controller::DISMISS_USER_META;
		foreach ( [ 'a', 'b', 'c' ] as $identifier ) {
			\add_user_meta( $user_id, $key, $identifier );
			\add_user_meta( $other_id, $key, $identifier );
		}
		\add_user_meta( $user_id, 'unrelated_notice_meta', 'b' );
		$controller = new Admin_Notice_Controller();
		$controller->add( new Admin_Notice( 'Active notice', [], 'a' ) );
		$controller->clean_stale_dismissed_notices();
		$this->assertSame( [ 'a' ], \get_user_meta( $user_id, $key, false ) );
		$this->assertSame( [ 'a', 'b', 'c' ], \get_user_meta( $other_id, $key, false ) );
		$this->assertSame( 'b', \get_user_meta( $user_id, 'unrelated_notice_meta', true ) );
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

		/** @var MockObject&Admin_Notice_Controller */
		$partially_mocked_controller = $this->getMockBuilder( Admin_Notice_Controller::class )
			->onlyMethods( [ 'clean_stale_dismissed_notices' ] )
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

		self::$mock_global_functions->expects( $this->once() )->method( 'get_user_meta' )
			->with( get_current_user_id(), Admin_Notice_Controller::DISMISS_USER_META, false )
			->willReturn( $dismissed_notices );

		foreach ( $registered_notices as $identifier ) {
			$controller->add( new Admin_Notice( 'hi', [], $identifier ) );
		}


		$deleted = [];
		self::$mock_global_functions->expects( $this->exactly( count( $expected_deletion ) ) )
			->method( 'delete_user_meta' )
			->willReturnCallback( static function ( $user_id, $key, $identifier ) use ( &$deleted ) {
				$deleted[] = [ $user_id, $key, $identifier ];
				return true;
			} );

		$controller->clean_stale_dismissed_notices();
		$expected_calls = array_map( static function ( $identifier ) {
			return [ get_current_user_id(), Admin_Notice_Controller::DISMISS_USER_META, $identifier ];
		}, $expected_deletion );
		$this->assertSame( $expected_calls, $deleted );
	}
}

/**
 * Overwriting global function
 */
function add_user_meta( int $user_id, string $meta_key, $meta_value, bool $unique = false ) {
	return is_null( Admin_Notice_Controller_Test::$mock_global_functions ) ? \add_user_meta( $user_id, $meta_key, $meta_value, $unique ) : Admin_Notice_Controller_Test::$mock_global_functions->add_user_meta( $user_id, $meta_key, $meta_value, $unique );
}

function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
	return is_null( Admin_Notice_Controller_Test::$mock_global_functions ) ? \get_user_meta( $user_id, $key, $single ) : Admin_Notice_Controller_Test::$mock_global_functions->get_user_meta( $user_id, $key, $single );
}

function delete_user_meta( int $user_id, string $meta_key, $meta_value ) {
	return is_null( Admin_Notice_Controller_Test::$mock_global_functions ) ? \delete_user_meta( $user_id, $meta_key, $meta_value ) : Admin_Notice_Controller_Test::$mock_global_functions->delete_user_meta( $user_id, $meta_key, $meta_value );
}
