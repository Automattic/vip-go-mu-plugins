<?php

namespace Automattic\VIP\Security;

use WP_UnitTestCase;

require_once __DIR__ . '/../../security/machine-user.php';

class Machine_User_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		$this->machine_user = $this->factory->user->create_and_get( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'role'       => WPCOM_VIP_MACHINE_USER_ROLE,
		] );

	}

	public function get_test_data__user_modification_caps() {
		return [
			'edit_user'    => [ 'edit_user' ],
			'remove_user'  => [ 'remove_user' ],
			'delete_user'  => [ 'delete_user' ],
			'promote_user' => [ 'promote_user' ],
		];
	}

	// For testing non-superadmin Administrator users
	public function get_test_data__selective_user_modification_caps() {
		return [
			'remove_user'  => [ 'remove_user' ],
			'promote_user' => [ 'promote_user' ],
		];
	}

	/**
	 * @dataProvider get_test_data__user_modification_caps
	 */
	public function test__machine_user_cannot_modify_self( $test_cap ) {
		$actual_has_cap = $this->machine_user->has_cap( $test_cap, $this->machine_user->ID );

		$this->assertFalse( $actual_has_cap );
	}

	/**
	 * @dataProvider get_test_data__user_modification_caps
	 */
	public function test__non_admin_users_cannot_modify_machine_user( $test_cap ) {
		$test_user = $this->factory->user->create_and_get( [ 'role' => 'editor' ] );

		$actual_has_cap = $test_user->has_cap( $test_cap, $this->machine_user->ID );

		$this->assertFalse( $actual_has_cap );
	}

	/**
	 * @dataProvider get_test_data__user_modification_caps
	 */
	public function test__admin_users_cannot_modify_machine_user( $test_cap ) {
		$test_user = $this->factory->user->create_and_get( [ 'role' => 'administrator' ] );

		$actual_has_cap = $test_user->has_cap( $test_cap, $this->machine_user->ID );

		$this->assertFalse( $actual_has_cap );
	}

	/**
	 * @dataProvider get_test_data__user_modification_caps
	 */
	public function test__superadmin_users_cannot_modify_machine_user( $test_cap ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'No superadmins on single site installs.' );
		}

		$test_user = $this->factory->user->create_and_get( [ 'role' => 'administrator' ] );
		grant_super_admin( $test_user->ID );

		$actual_has_cap = $test_user->has_cap( $test_cap, $this->machine_user->ID );

		$this->assertFalse( $actual_has_cap );
	}

	public function test__non_admin_users_can_still_modify_self() {
		$test_user = $this->factory->user->create_and_get( [ 'role' => 'editor' ] );

		$actual_has_cap = $test_user->has_cap( 'edit_user', $test_user->ID );

		$this->assertTrue( $actual_has_cap );
	}

	public function test__admin_users_can_still_modify_self() {
		$test_user = $this->factory->user->create_and_get( [ 'role' => 'administrator' ] );

		$actual_has_cap = $test_user->has_cap( 'edit_user', $test_user->ID );

		$this->assertTrue( $actual_has_cap );
	}

	public function test__superadmin_users_can_still_modify_self() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'No superadmins on single site installs.' );
		}

		$test_user = $this->factory->user->create_and_get( [ 'role' => 'administrator' ] );
		grant_super_admin( $test_user->ID );

		$actual_has_cap = $test_user->has_cap( 'edit_user', $test_user->ID );

		$this->assertTrue( $actual_has_cap );
	}

	/**
	 * @dataProvider get_test_data__user_modification_caps
	 */
	public function test__non_admin_users_cannot_modify_others( $test_cap ) {
		$test_user    = $this->factory->user->create_and_get( [ 'role' => 'editor' ] );
		$user_to_edit = $this->factory->user->create_and_get( [ 'role' => 'editor' ] );

		$actual_has_cap = $test_user->has_cap( $test_cap, $user_to_edit->ID );

		$this->assertFalse( $actual_has_cap );
	}

	/**
	 * @dataProvider get_test_data__user_modification_caps
	 */
	public function test__admin_users_can_still_modify_others_on_single_site( $test_cap ) {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site test for administrator user; multisite tested separately.' );
		}

		$test_user    = $this->factory->user->create_and_get( [ 'role' => 'administrator' ] );
		$user_to_edit = $this->factory->user->create_and_get( [ 'role' => 'editor' ] );

		$actual_has_cap = $test_user->has_cap( $test_cap, $user_to_edit->ID );

		$this->assertTrue( $actual_has_cap );
	}

	/**
	 * Administrators without super admin have a more restricted set of caps on multisite (no edit or delete).
	 *
	 * @dataProvider get_test_data__selective_user_modification_caps
	 */
	public function test__admin_users_can_still_selectively_modify_others_on_multsite( $test_cap ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite test for administrator user; single site tested separately.' );
		}

		$test_user    = $this->factory->user->create_and_get( [ 'role' => 'administrator' ] );
		$user_to_edit = $this->factory->user->create_and_get( [ 'role' => 'editor' ] );

		$actual_has_cap = $test_user->has_cap( $test_cap, $user_to_edit->ID );

		$this->assertTrue( $actual_has_cap );
	}

	/**
	 * @dataProvider get_test_data__user_modification_caps
	 */
	public function test__superadmin_users_can_still_modify_others( $test_cap ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'No superadmins on single site installs.' );
		}

		$test_user = $this->factory->user->create_and_get( [ 'role' => 'administrator' ] );
		grant_super_admin( $test_user->ID );
		$user_to_edit = $this->factory->user->create_and_get( [ 'role' => 'editor' ] );

		$actual_has_cap = $test_user->has_cap( $test_cap, $user_to_edit->ID );

		$this->assertTrue( $actual_has_cap );
	}
}
