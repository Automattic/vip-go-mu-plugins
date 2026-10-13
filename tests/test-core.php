<?php

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

class VIP_Go__Core__Disable_Update_Caps_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		wpcom_vip_init_core_restrictions();
	}

	/**
	 * On single-site grant_super_admin() is a no-op and the administrator role grants `update_core`;
	 * on multisite a super admin passes every non-`do_not_allow` cap. Either way only VIP's filter denies it.
	 */
	public function test__super_admin_should_not_have_update_core_cap() {
		$super_admin = $this->factory()->user->create_and_get( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin->ID );

		$this->assertFalse( user_can( $super_admin, 'update_core' ), 'Superadmin user should not have `update_core` cap' );
		// sanity check to make sure other caps didn't break
		$this->assertTrue( user_can( $super_admin, 'manage_options' ), 'Superadmin user missing `manage_options` cap' );
	}
}
