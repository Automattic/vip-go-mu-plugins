<?php
/**
 * Test our custom role
 */

namespace Automattic\VIP\Support_User\Tests;

use Automattic\VIP\Support_User\Role;
use Automattic\VIP\Support_User\User;
use WP_UnitTestCase;

/**
 * @group vip_support_role
 */
class VIPSupportRoleTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		delete_option( 'vipsupportrole_version' );

		$this->vip_support_user = User::add( array(
			'user_email' => 'vip-support@automattic.com',
			'user_login' => 'vip-support',
			'user_pass'  => 'password',
		) );

		$this->admin_user = $this->factory->user->create( [
			'user_email' => 'admin@automattic.com',
			'user_login' => 'vip_admin',
			'role'       => 'administrator',
		] );
	}

	public function test_role_existence() {
		Role::init()->maybe_upgrade_version();

		$roles = wp_roles()->roles;

		$this->assertArrayHasKey( Role::VIP_SUPPORT_ROLE, $roles );
		$this->assertArrayHasKey( Role::VIP_SUPPORT_INACTIVE_ROLE, $roles );
	}

	public function test_editable_role__vipsupport() {
		Role::init()->maybe_upgrade_version();

		wp_set_current_user( $this->vip_support_user );

		$roles      = get_editable_roles();
		$role_names = array_keys( $roles );

		$this->assertArrayHasKey( Role::VIP_SUPPORT_ROLE, $roles );
		$this->assertArrayHasKey( Role::VIP_SUPPORT_INACTIVE_ROLE, $roles );

		$first_role = array_shift( $role_names );
		$this->assertTrue( Role::VIP_SUPPORT_INACTIVE_ROLE === $first_role );
	}

	public function test_editable_role__admin() {
		Role::init()->maybe_upgrade_version();

		wp_set_current_user( $this->admin_user );

		$roles      = get_editable_roles();
		$role_names = array_keys( $roles );

		$this->assertArrayNotHasKey( Role::VIP_SUPPORT_ROLE, $roles );
		$this->assertArrayNotHasKey( Role::VIP_SUPPORT_INACTIVE_ROLE, $roles );

		$first_role = array_shift( $role_names );
		$this->assertFalse( Role::VIP_SUPPORT_INACTIVE_ROLE === $first_role );
	}

	public function test__only_run_upgrade_once() {
		// Run initial upgrade.
		Role::init()->maybe_upgrade_version();

		// Remove a role which we'll use to verify our test.
		remove_role( Role::VIP_SUPPORT_ROLE );

		// Attempt to run upgrade again.
		Role::init()->maybe_upgrade_version();

		// Verify that the role was not added again (because the upgrade didn't run).
		$roles = get_editable_roles();
		$this->assertFalse( isset( $roles[ Role::VIP_SUPPORT_ROLE ] ) );
	}
}
