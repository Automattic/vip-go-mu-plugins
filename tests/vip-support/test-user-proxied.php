<?php

/**
 * Test support user
 */

namespace Automattic\VIP\Support_User\Tests;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Support_User\Role;
use Automattic\VIP\Support_User\User;
use WP_UnitTestCase;

/**
 * @group vip_support_user
 */
class VIPSupportUserProxiedTest extends WP_UnitTestCase {

	private $original_current_user_id;

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'A8C_PROXIED_REQUEST' ) ) { // phpcs:ignore WordPressVIPMinimum.Constants.RestrictedConstants.UsingRestrictedConstant
			define( 'A8C_PROXIED_REQUEST', true ); // phpcs:ignore WordPressVIPMinimum.Constants.RestrictedConstants.DefiningRestrictedConstant
		}

		$this->original_current_user_id = get_current_user_id();
	}

	public function tearDown(): void {
		parent::tearDown();

		wp_set_current_user( $this->original_current_user_id );
	}

	public function test__superadmin_filter(): void {
		if ( defined( 'A8C_PROXIED_REQUEST' ) && false === A8C_PROXIED_REQUEST ) { // phpcs:ignore WordPressVIPMinimum.Constants.RestrictedConstants.UsingRestrictedConstant
			$this->markTestIncomplete( 'is_proxied_automattician() needs to be made Constant_Mocker friendly and overridden' );
		}

		// Wire up the hooks
		Role::init()->maybe_upgrade_version();

		// Ensure a regular user is not filtered to be a superadmin
		$regular_user_id = $this->factory()->user->create( [
			'user_email'   => 'regular-user@somedomain.com',
			'user_login'   => 'regular-user',
			'display_name' => 'Regular User',
		] );

		wp_set_current_user( $regular_user_id );

		$this->assertNotContains( 'regular-user', get_super_admins() );

		// Ensure a VIP Support user is not filtered to be a superadmin until they are verified and proxied
		$vip_user_id = $this->factory()->user->create( [
			'user_email'   => 'regular-user@automattic.com',
			'user_login'   => 'vip_regular_user',
			'display_name' => 'Regular A11n User',
		] );

		wp_set_current_user( $vip_user_id );
		wp_get_current_user()->set_role( Role::VIP_SUPPORT_ROLE );

		$this->assertFalse( is_proxied_automattician( $vip_user_id ) );
		$this->assertNotContains( 'vip_regular_user', get_super_admins() );

		// Ensure a verified + proxied user is filtered to be a superadmin
		$instance = User::init();

		$instance->mark_user_email_verified( $vip_user_id, 'regular-user@automattic.com' );

		$this->assertTrue( is_proxied_automattician( $vip_user_id ) );
		$this->assertContains( 'vip_regular_user', get_super_admins() );
	}
}
