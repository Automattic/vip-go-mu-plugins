<?php

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

class VIP_Go__Core__Disable_Update_Caps_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		wpcom_vip_init_core_restrictions();
	}

	public function test__super_admin_should_not_have_update_core_cap() {
		$super_admin = $this->factory->user->create_and_get( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin->ID );

		$this->assertFalse( user_can( $super_admin, 'update_core' ), 'Superadmin user should not have `update_core` cap' );
		// sanity check to make sure other caps didn't break
		$this->assertTrue( user_can( $super_admin, 'manage_options' ), 'Superadmin user missing `manage_options` cap' );
	}

	public function test__administrator_should_not_have_update_core_cap() {
		$admin = $this->factory->user->create_and_get( array( 'role' => 'administrator' ) );

		$this->assertFalse( user_can( $admin, 'update_core' ), 'Admin user should not have `update_core` cap' );
		$this->assertTrue( user_can( $admin, 'manage_options' ), 'Admin user missing `manage_options` cap' );
	}

	public function test__contributor_should_not_have_update_core_cap() {
		$contributor = $this->factory->user->create_and_get( array( 'role' => 'contributor' ) );

		$this->assertFalse( user_can( $contributor, 'update_core' ), 'Contributor user should not have `update_core` cap' );
		$this->assertTrue( user_can( $contributor, 'edit_posts' ), 'Contributor user missing `edit_posts` cap' );
	}
}
