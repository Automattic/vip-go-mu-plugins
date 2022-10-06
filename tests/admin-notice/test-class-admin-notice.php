<?php

namespace Automattic\VIP\Admin_Notice;

use PHPUnit\Framework\MockObject\MockObject;

require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';
require_once __DIR__ . '/../../admin-notice/conditions/interface-condition.php';

class Admin_Notice_Class_Test extends \PHPUnit\Framework\TestCase {

	public static $super_admin_id;

	public static $user_id;

	public static function setUpBeforeClass(): void {
		$super_admin_id = wp_insert_user([
			'user_login' => 'test_user',
			'user_pass'  => 'test_password',
			'user_email' => 'test@test.com',
			'role'       => 'admin',
		]);
		$super_admin    = get_user_by( 'id', $super_admin_id );
		grant_super_admin( $super_admin_id );
		$super_admin->add_cap( 'delete_users' ); // Fake super admin
		self::$super_admin_id = $super_admin_id;

		$user_id       = wp_insert_user([
			'user_login' => 'foo',
			'user_pass'  => 'bar',
			'user_email' => 'foo@bar.com',
			'role'       => 'subscriber',
		]);
		self::$user_id = $user_id;
	}

	public static function tearDownAfterClass(): void {
		revoke_super_admin( self::$super_admin_id );
		wp_delete_user( self::$super_admin_id );
		wp_delete_user( self::$user_id );
	}

	public function test__display() {
		$message = 'Test Message';
		$notice  = new Admin_Notice( $message );

		$expected_html = "#<div data-vip-admin-notice=\"\" class=\"notice notice-info vip-notice\"><p>$message</p></div>#";
		$this->expectOutputRegex( $expected_html );

		$notice->display();
	}

	public function test__display_dismissible() {
		$message    = 'Test Message';
		$dismiss_id = 'dismiss_id';
		$notice     = new Admin_Notice( $message, [], $dismiss_id );

		$expected_html = "#<div data-vip-admin-notice=\"$dismiss_id\" class=\"notice notice-info vip-notice is-dismissible\"><p>$message</p></div>#";
		$this->expectOutputRegex( $expected_html );

		$notice->display();
	}

	public function should_render_conditions_data() {
		return [
			[ [], true ],
			[ [ false ], false ],
			[ [ true ], true ],
			[ [ true, true ], true ],
			[ [ true, false ], false ],
			[ [ false, true ], false ],
			[ [ false, false ], false ],
		];
	}

	/**
	 * @dataProvider should_render_conditions_data
	 */
	public function test__should_render_conditions( $condition_results, $expected_result ) {
		wp_set_current_user( self::$super_admin_id );

		$conditions = array_map( function ( $result_to_return ) {
			/** @var Condition&MockObject */
			$condition_stub = $this->createMock( Condition::class );
			$condition_stub->method( 'evaluate' )->willReturn( $result_to_return );
			return $condition_stub;
		}, $condition_results);

		$notice = new Admin_Notice( 'foo', $conditions );

		$result = $notice->should_render();

		$this->assertEquals( $expected_result, $result );

		wp_set_current_user( self::$user_id );

		$result = $notice->should_render();

		$this->assertFalse( $result );
	}

	/**
	 * @dataProvider data_cap_condition_exist
	 */
	public function test_cap_condition_exist( array $conditions, bool $xpected ): void {
		$notice = new Admin_Notice( 'notice', $conditions );
		$actual = $notice->cap_condition_exist();
		self::assertSame( $xpected, $actual );
	}

	public function data_cap_condition_exist(): iterable {
		return [
			'no conditions'        => [
				[],
				false,
			],
			'capability condition' => [
				[ new Capability_Condition( 'delete_users' ) ],
				true,
			],
		];
	}
}
