<?php
/**
 * Test our custom role
 */

namespace Automattic\VIP\Support_User\Tests;
use Automattic\VIP\Support_User\Role;
use WP_UnitTestCase;

/**
 * @group vip_support_role
 */
class VIPSupportRoleTest extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		delete_option( 'vipsupportrole_version' );
	}

	public function test_role_existence() {
		Role::init()->maybe_upgrade_version();

		$roles = get_editable_roles();

		$this->assertArrayHasKey( Role::VIP_SUPPORT_ROLE, $roles );
		$this->assertArrayHasKey( Role::VIP_SUPPORT_INACTIVE_ROLE, $roles );
	}

	public function test_role_order() {

		// Arrange
		// Trigger the update method call on admin_init,
		// this sets up the role
		Role::init()->maybe_upgrade_version();

		// Act
		$roles = get_editable_roles();
		$role_names = array_keys( $roles );

		// Assert
		// To show up last, the VIP Support Inactive role will be
		// the first index in the array
		$first_role = array_shift( $role_names );
		$this->assertTrue( Role::VIP_SUPPORT_INACTIVE_ROLE === $first_role );
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
