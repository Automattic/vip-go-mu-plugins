<?php

/**
 * @group vip_support_role
 */
class VIPSupportRoleTest extends WP_UnitTestCase {

	function test_role_order() {

		// Arrange
		// Trigger the update method call on admin_init,
		// this sets up the role
		WPCOM_VIP_Support_Role::init()->action_admin_init();

		// Act
		$roles = get_editable_roles();
		$role_names = array_keys( $roles );

		// Assert
		// To show up last, the VIP Support role will be
		// the first index in the array
		$first_role = array_shift( $role_names );
		$this->assertTrue( WPCOM_VIP_Support_Role::VIP_SUPPORT_ROLE === $first_role );
	}
}
