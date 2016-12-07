<?php

class VIP_Go_Core_Updates_Test extends WP_UnitTestCase {
	public function test__super_admin_should_not_have_update_core_cap() {
		$super_admin = $this->factory->user->create_and_get( array( 'role' => 'contributor' ) );
		grant_super_admin( $super_admin->ID );

		$this->assertFalse( user_can( $super_admin, 'update_core' ), 'Superadmin user should not have `update_core` cap' ); 
	}

	public function test__administrator_should_not_have_update_core_cap() {
		$admin = $this->factory->user->create_and_get( array( 'role' => 'contributor' ) );

		$this->assertFalse( user_can( $admin, 'update_core' ), 'Admin user should not have `update_core` cap' ); 
	}
}
