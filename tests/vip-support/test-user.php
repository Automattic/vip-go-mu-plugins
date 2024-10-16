<?php
/**
 * Test support user
 */

namespace Automattic\VIP\Support_User;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

/**
 * @group vip_support_user
 */
class VIPSupportUserTest extends WP_UnitTestCase {
	private $vip_support_user;

	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();

		$this->vip_support_user = User::add( array(
			'user_email' => 'vip-support@example.test',
			'user_login' => 'vip-support',
			'user_pass'  => 'password',
		) );

		reset_phpmailer_instance();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		reset_phpmailer_instance();
		parent::tearDown();
	}

	public function test_is_a8c_email(): void {

		$a8c_emails = array(
			'vip@matticspace.com',
			'v.ip@matticspace.com',
			'vip+test@matticspace.com',
			'v.ip+test@matticspace.com',
			'some.user@automattic.com',
			'someuser@automattic.com',
			'some.user+test@automattic.com',
			'someuser+test@automattic.com',
			'some.user@a8c.com',
			'someuser@a8c.com',
			'some.user+test@a8c.com',
			'someuser+test@a8c.com',
		);

		$user_instance = User::init();

		foreach ( $a8c_emails as $a8c_email ) {
			$this->assertTrue( $user_instance::is_a8c_email( $a8c_email ) );
		}

		$non_a8c_emails = array(
			'someone@example.com',
			'someone.else@example.com',
			'automattic.com@example.invalid',
			'someone@automattic',
			'matticspace.com@example.com',
			'a8c.com@example.com',
			'automattic@bbc.co.uk',
			'a8c@bbc.co.uk',
		);

		foreach ( $non_a8c_emails as $non_a8c_email ) {
			$this->assertFalse( $user_instance::is_a8c_email( $non_a8c_email ) );
		}
	}

	public function provider_valid_vip_support_email_aliases(): array {
		return [
			[
				[
					'vip-support+test@automattic.com',
					'vip-support+some_username@automattic.com',
					'vip-support+areallylongusernameusedhere123@automattic.com',
				],
			],
		];
	}

	public function provider_invalid_vip_support_email_aliases(): array {
		return [
			[
				[
					'someone@example.com',
					'someone@automattic',
					'someone@automattic.com',
					'vip+test@example.com',
					'vip-support+test@example.com',
					'vip-support@example.com',
				],
			],
		];
	}

	public function test_is_allowed_email_with_no_config(): void {
		$instance = User::init();

		$this->assertTrue( $instance->is_allowed_email( 'admin@automattic.com' ) );
	}

	public function test_is_allowed_email_with_config(): void {
		Constant_Mocker::define( 'VIP_SUPPORT_USER_ALLOWED_EMAILS', array( 'admin@automattic.com' ) );

		$instance = User::init();

		$this->assertTrue( $instance->is_allowed_email( 'admin@automattic.com' ) );
		$this->assertFalse( $instance->is_allowed_email( 'foo@automattic.com' ) );
	}

	public function test_is_verified_automattician(): void {
		$user_id = $this->factory()->user->create( [
			'user_email' => 'admin@automattic.com',
			'user_login' => 'vip_admin',
		] );

		$instance = User::init();

		$instance->mark_user_email_verified( $user_id, 'admin@automattic.com' );

		$this->assertTrue( $instance->is_verified_automattician( $user_id ) );
	}

	public function test_is_verified_automattician_for_disallowed_user(): void {
		Constant_Mocker::define( 'VIP_SUPPORT_USER_ALLOWED_EMAILS', array( 'admin@automattic.com' ) );

		$user_id = $this->factory()->user->create( [
			'user_email' => 'foo@automattic.com',
			'user_login' => 'vip_foo',
		] );

		$instance = User::init();

		$instance->mark_user_email_verified( $user_id, 'foo@automattic.com' );

		$this->assertFalse( $instance->is_verified_automattician( $user_id ) );
	}

	/**
	 * Test that cron callback is registered properly
	 */
	public function test_cron_cleanup_has_callback(): void {
		$this->assertEquals( 10, has_action( User::CRON_ACTION ) );
	}

	public function test__has_vip_support_meta__yep(): void {
		$is_vip_support_user = User::has_vip_support_meta( $this->vip_support_user );
		$this->assertTrue( $is_vip_support_user );
	}

	public function test__has_vip_support_meta__nope(): void {
		$user = $this->factory()->user->create( array( 'user_login' => 'not-vip-support' ) );

		$is_vip_support_user = User::has_vip_support_meta( $user );
		$this->assertFalse( $is_vip_support_user );
	}

	public function test__add__update_email_for_existing_user_with_different_login(): void {
		$existing_user_id = $this->factory()->user->create( [
			'user_email' => 'existing123@automattic.com',
			'user_login' => 'existing-user-123',
		] );

		$new_user_id = User::add( [
			'user_email' => 'existing123@automattic.com',
			'user_login' => 'new-vip-support-user-123',
			'user_pass'  => 'password',
		] );

		$this->vip_support_user = $new_user_id;

		$this->assertNotEquals( $existing_user_id, $new_user_id, 'Existing and new IDs are the same which should not happen' );

		$existing_user_obj = get_userdata( $existing_user_id );
		$this->assertEquals( 'existing123+old@automattic.com', $existing_user_obj->user_email, 'Email for existing user was not updated to avoid conflict' );

		$new_user_obj = get_userdata( $new_user_id );
		$this->assertEquals( 'existing123@automattic.com', $new_user_obj->user_email, 'Email for new user was not correctly set.' );
	}

	public function test__add__update_account_for_existing_user_with_same_login(): void {
		$existing_user_id = $this->factory()->user->create( [
			'user_email'   => 'existing456@automattic.com',
			'user_login'   => 'vip-support-user-456',
			'display_name' => 'Existing User',
		] );

		$new_user_id = User::add( [
			'user_email'   => 'existing456+test@automattic.com',
			'user_login'   => 'vip-support-user-456',
			'display_name' => 'New User',
			'user_pass'    => 'password',
		] );

		$this->vip_support_user = $new_user_id;

		$this->assertEquals( $existing_user_id, $new_user_id, 'Existing and new IDs are not the same. Existing account was not updated.' );

		$new_user_obj = get_userdata( $new_user_id );
		$this->assertEquals( 'existing456+test@automattic.com', $new_user_obj->user_email, 'Email for user was not updated.' );
		$this->assertEquals( 'New User', $new_user_obj->display_name, 'Display name for new user was not updated.' );
	}

	public function test__remove__existing_vip_support_user(): void {
		$new_user_id = User::add( [
			'user_email' => 'existing1234@automattic.com',
			'user_login' => 'new-vip-support-user-123',
			'user_pass'  => 'password',
		] );

		User::remove( 'existing1234@automattic.com' );

		$removed_user_obj = get_userdata( $new_user_id );

		$this->assertFalse( $removed_user_obj, 'User was not deleted' );
	}

	public function test__superadmin_filter(): void {
		// Ensure a regular user is not filtered to be a superadmin
		$regular_user_id = $this->factory()->user->create( [
			'user_email'   => 'regular-user@somedomain.com',
			'user_login'   => 'regular-user',
			'display_name' => 'Regular User',
		] );

		$this->assertFalse( is_super_admin( $regular_user_id ) );

		// Ensure a regular user is not filtered to be a superadmin, without being verified + proxied
		$vip_user_id = $this->factory()->user->create( [
			'user_email'   => 'regular-user@automattic.com',
			'user_login'   => 'vip_regular_user',
			'display_name' => 'Regular A11n User',
		] );

		$this->assertFalse( is_super_admin( $vip_user_id ) );
	}
}
