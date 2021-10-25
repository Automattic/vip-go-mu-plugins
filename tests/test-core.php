<?php
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting

class VIP_Go__Core__Disable_Update_Caps_Test extends WP_UnitTestCase {
	public function setUp(): void {
		wpcom_vip_init_core_restrictions();
		$this->error_reporting = error_reporting();
	}

	public function tearDown(): void {
		error_reporting( $this->error_reporting );
		parent::tearDown();
	}

	public function get_var_standard_env() {
		define( 'VIP_ENV_VAR_MY_VAR', 'VIP_ENV_VAR_MY_VAR' );
	}

	public function get_var_legacy_env() {
		define( 'MY_VAR', 'MY_VAR' );
	}

	// tests the use-case where $key parameter is not found
	public function test_get_default_var() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$val = vip_get_env_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( 'default_value', $val );
	}

	/**
	 * tests the use-case where $key parameter does not have the prefix
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var_legacy_key() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_legacy_env();
		$val = vip_get_env_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( 'MY_VAR', $val );
	}

	/**
	 * tests the use-case where $key parameter is lower case
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var_lower_key() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_standard_env();
		$val = vip_get_env_var( 'vip_env_var_my_var', 'default_value' );
		$this->assertEquals( 'VIP_ENV_VAR_MY_VAR', $val );
	}

	/**
	 * tests the use-case where $key parameter is ''
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var_empty_key() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_standard_env();
		$val = vip_get_env_var( '', 'default_value' );
		$this->assertEquals( 'default_value', $val );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_standard_env();
		$val = vip_get_env_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( 'VIP_ENV_VAR_MY_VAR', $val );
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
