<?php

namespace Automattic\VIP\Security;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Security\User_Last_Seen;
use WP_UnitTestCase;

require_once __DIR__ . '/../../security/user-last-seen.php';

class User_Last_Seen_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		Constant_Mocker::clear();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test__should_not_load_actions_and_filters_when_env_vars_are_not_defined() {
		Constant_Mocker::undefine( 'VIP_SECURITY_INACTIVE_USERS_ACTION' );

		remove_all_filters( 'determine_current_user' );
		remove_all_filters( 'authenticate' );

		$last_seen = new User_Last_Seen();
		$last_seen->init();

		$this->assertFalse( has_filter( 'determine_current_user' ) );
		$this->assertFalse( has_filter( 'authenticate' ) );
	}

	public function test__should_not_load_actions_and_filters_when_env_vars_is_set_to_no_action() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'NO_ACTION' );

		remove_all_filters( 'determine_current_user' );
		remove_all_filters( 'authenticate' );

		$last_seen = new User_Last_Seen();
		$last_seen->init();

		$this->assertFalse( has_filter( 'determine_current_user' ) );
		$this->assertFalse( has_filter( 'authenticate' ) );
	}

	public function test__is_considered_inactive__should_consider_user_meta()
	{
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 30);

		$user_inactive_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		add_user_meta( $user_inactive_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime('-31 days') );

		$user_active_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		add_user_meta( $user_active_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime('-29 days') );

		$last_seen = new User_Last_Seen();
		$last_seen->init();

		$this->assertTrue( $last_seen->is_considered_inactive( $user_inactive_id ) );
		$this->assertFalse( $last_seen->is_considered_inactive( $user_active_id ) );
	}

	public function test__is_considered_inactive__should_return_false_if_user_meta_and_option_are_not_present()
	{
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 30);

		delete_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );

		$user_without_meta = $this->factory()->user->create( array( 'role' => 'administrator' ) );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$this->assertFalse( $last_seen->is_considered_inactive( $user_without_meta ) );
	}

	public function test__is_considered_inactive__should_use_release_date_option_when_user_meta_is_not_defined()
	{
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		add_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime('-16 days') );

		$user_without_meta = $this->factory()->user->create( array( 'role' => 'administrator' ) );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$this->assertTrue( $last_seen->is_considered_inactive( $user_without_meta ) );

		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime('-10 days') );

		$this->assertFalse( $last_seen->is_considered_inactive( $user_without_meta ) );
	}

	public function test__determine_current_user_should_record_last_seen_meta()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		remove_all_filters( 'determine_current_user' );

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$new_user_id = apply_filters( 'determine_current_user', $user_id, $user_id );

		$current_last_seen = get_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, true );

		$this->assertSame( $new_user_id, $user_id );
		$this->assertIsNumeric( $current_last_seen );
	}

	public function test__determine_current_user_should_record_once_last_seen_meta()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		remove_all_filters( 'determine_current_user' );

		$previous_last_seen = sprintf('%d', strtotime('-10 days') );

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, $previous_last_seen );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		apply_filters( 'determine_current_user', $user_id, $user_id );

		$current_last_seen = get_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, true );

		$new_user_id = apply_filters( 'determine_current_user', $user_id, $user_id );

		$cached_last_seen = get_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, true );

		$this->assertTrue( $current_last_seen > $previous_last_seen );
		$this->assertSame( $current_last_seen, $cached_last_seen );
		$this->assertSame( $new_user_id, $user_id );
	}

	public function test__determine_current_user_should_return_an_error_when_user_is_inactive()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		remove_all_filters( 'determine_current_user' );

		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime('-100 days') );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$user = apply_filters( 'determine_current_user', $user_id, $user_id );

		$this->assertWPError( $user, 'Expected WP_Error object to be returned' );
	}

	public function test__authenticate_should_not_return_error_when_user_is_active()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		remove_all_filters( 'authenticate' );

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime('-5 days') );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$new_user = apply_filters( 'authenticate', $user, $user );

		$this->assertSame( $user->ID, $new_user->ID );
	}

	public function test__authenticate_should_return_an_error_when_user_is_inactive()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		remove_all_filters( 'authenticate' );

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime('-100 days') );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$user = apply_filters( 'authenticate', $user, $user );

		$this->assertWPError( $user, 'Expected WP_Error object to be returned' );
	}

	public function test__register_release_date_should_register_release_date_only_once()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'RECORD_LAST_SEEN' );

		remove_all_actions( 'admin_init' );
		delete_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );
		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->register_release_date();

		$release_date = get_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );

		$last_seen->register_release_date();

		$new_release_date = get_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );

		$this->assertIsNumeric( $release_date );
		$this->assertSame( $release_date, $new_release_date );
	}

	public function test__authenticate_should_not_consider_users_without_elevated_capabilities()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		remove_all_filters( 'authenticate' );

		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime('-100 days') );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$this->assertSame( $user, apply_filters( 'authenticate', $user, $user ) );
	}

	public function test__should_check_user_last_seen_should_call_elevated_capabilities_filters()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		remove_all_filters( 'authenticate' );
		remove_all_filters( 'vip_security_last_seen_elevated_capabilities' );

		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime('-100 days') );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		add_filter( 'vip_security_last_seen_elevated_capabilities', function ( $capabilities ) {
			$capabilities[] = 'read';

			return $capabilities;
		} );

		$user = apply_filters( 'authenticate', $user, $user );

		$this->assertWPError( $user, 'Expected WP_Error object to be returned' );
	}

	public function test__should_check_user_last_seen_should_call_skip_users_filters()
	{
		Constant_Mocker::define('VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define('VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15);

		remove_all_filters( 'authenticate' );
		remove_all_filters( 'vip_security_last_seen_elevated_capabilities' );

		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime('-100 days') );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		add_filter( 'vip_security_last_seen_skip_users', function ( $users ) use ( $user_id ) {
			$users[] = $user_id;

			return $users;
		} );

		$this->assertSame( $user, apply_filters( 'authenticate', $user, $user ) );
	}
}
