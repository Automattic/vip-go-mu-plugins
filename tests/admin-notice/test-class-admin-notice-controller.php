<?php

namespace Automattic\VIP\Admin_Notice;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTest_Factory;
use WP_UnitTestCase;

require_once __DIR__ . '/../../admin-notice/class-admin-notice-controller.php';
require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';

class Admin_Notice_Controller_Test extends WP_UnitTestCase {

	private static $user_id;
	private $previous_cleanup_value;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$user_id = $factory->user->create();
	}

	public function setUp(): void {
		parent::setUp();
		$this->previous_cleanup_value = Admin_Notice_Controller::$stale_dismiss_cleanup_value;
		wp_set_current_user( self::$user_id );
	}

	/**
	 * Restore the cleanup configuration.
	 */
	public function tearDown(): void {
		Admin_Notice_Controller::$stale_dismiss_cleanup_value = $this->previous_cleanup_value;
		parent::tearDown();
	}

	/**
	 * Check persisted survivors and isolation between users and metadata keys.
	 */
	public function test_cleanup_preserves_active_dismissals_and_other_users(): void {
		$user_id  = get_current_user_id();
		$other_id = self::factory()->user->create();
		$key      = Admin_Notice_Controller::DISMISS_USER_META;
		foreach ( [ 'a', 'b', 'c' ] as $identifier ) {
			add_user_meta( $user_id, $key, $identifier );
			add_user_meta( $other_id, $key, $identifier );
		}
		add_user_meta( $user_id, 'unrelated_notice_meta', 'b' );
		$controller = new Admin_Notice_Controller();
		$controller->add( new Admin_Notice( 'Active notice', [], 'a' ) );
		$controller->clean_stale_dismissed_notices();
		$this->assertSame( [ 'a' ], get_user_meta( $user_id, $key, false ) );
		$this->assertSame( [ 'a', 'b', 'c' ], get_user_meta( $other_id, $key, false ) );
		$this->assertSame( 'b', get_user_meta( $user_id, 'unrelated_notice_meta', true ) );
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
		// [ dismissed identifiers, registered identifiers, dismissed identifiers left after cleanup ]
		return [
			'nothing dismissed'                     => [ [], [ 'a' ], [] ],
			'dismissed notice still registered'     => [ [ 'a' ], [ 'a' ], [ 'a' ] ],
			'dismissed notice no longer registered' => [ [ 'a' ], [ 'b', 'c' ], [] ],
		];
	}

	/**
	 * @dataProvider clean_stale_dismissed_notices_data
	 */
	public function test__clean_stale_dismissed_notices( $dismissed_notices, $registered_notices, $expected_remaining ) {
		foreach ( $dismissed_notices as $identifier ) {
			add_user_meta( self::$user_id, Admin_Notice_Controller::DISMISS_USER_META, $identifier );
		}

		$controller = new Admin_Notice_Controller();
		foreach ( $registered_notices as $identifier ) {
			$controller->add( new Admin_Notice( 'hi', [], $identifier ) );
		}

		$controller->clean_stale_dismissed_notices();

		$this->assertSame( $expected_remaining, get_user_meta( self::$user_id, Admin_Notice_Controller::DISMISS_USER_META, false ) );
	}
}
