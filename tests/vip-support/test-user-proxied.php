<?php

/**
 * Test support user
 */

namespace Automattic\VIP\Support_User\Tests;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Support_User\User;
use WP_UnitTestCase;

/**
 * @group vip_support_user
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class VIPSupportUserProxiedTest extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		// @phpcs:ignore
		define( 'A8C_PROXIED_REQUEST', true );
	}

	public function test__superadmin_filter(): void {

		// Ensure a regular user is not filtered to be a superadmin
		$regular_user_id = $this->factory()->user->create( [
			'user_email'   => 'regular-user@somedomain.com',
			'user_login'   => 'regular-user',
			'display_name' => 'Regular User',
		] );

		$this->assertFalse( is_super_admin( $regular_user_id ) );

		// Ensure a VIP Support user is not filtered to be a superadmin until they are verified and proxied
		$vip_user_id = $this->factory()->user->create( [
			'user_email'   => 'regular-user@automattic.com',
			'user_login'   => 'vip_regular_user',
			'display_name' => 'Regular A11n User',
			'role'         => 'vip_support',
		] );

		$this->assertFalse( is_super_admin( $vip_user_id ) );

		// Ensure a verified + proxied user is filtered to be a superadmin
		$instance = User::init();

		$instance->mark_user_email_verified( $vip_user_id, 'regular-user@automattic.com' );

		$this->assertTrue( is_super_admin( $vip_user_id ) );
	}
}
