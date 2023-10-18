<?php

namespace Automattic\VIP\Security;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Security\User_Last_Seen;
use WP_UnitTestCase;

require_once __DIR__ . '/../../security/class-user-last-seen.php';

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

		remove_all_filters( 'rest_authentication_errors' );
		remove_all_filters( 'authenticate' );

		$last_seen = new User_Last_Seen();
		$last_seen->init();

		$this->assertFalse( has_filter( 'rest_authentication_errors' ) );
		$this->assertFalse( has_filter( 'authenticate' ) );
	}

	public function test__should_not_load_actions_and_filters_when_env_vars_is_set_to_no_action() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'NO_ACTION' );

		remove_all_filters( 'rest_authentication_errors' );
		remove_all_filters( 'authenticate' );

		$last_seen = new User_Last_Seen();
		$last_seen->init();

		$this->assertFalse( has_filter( 'rest_authentication_errors' ) );
		$this->assertFalse( has_filter( 'authenticate' ) );
	}

	public function test__is_considered_inactive__should_consider_user_registered() {
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 30 );
		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-100 days' ) );

		// Recent registered user
		$user1 = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => gmdate( 'Y-m-d' ),
		) );

		// Inactive user (last seen 20 days ago)
		$user2 = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user2, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-31 days' ) );

		// Active user (last seen 2 days ago)
		$user3 = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user3, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-2 days' ) );

		// Old user without meta
		$user4 = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => gmdate( 'Y-m-d', strtotime( '-40 days' ) ),
		) );

		$last_seen = new User_Last_Seen();
		$last_seen->init();

		$this->assertFalse( $last_seen->is_considered_inactive( $user1 ) );
		$this->assertTrue( $last_seen->is_considered_inactive( $user2 ) );
		$this->assertFalse( $last_seen->is_considered_inactive( $user3 ) );
		$this->assertTrue( $last_seen->is_considered_inactive( $user4 ) );
	}

	public function test__is_considered_inactive__add_extra_time_when_user_is_promoted() {
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 30 );
		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-100 days' ) );

		$user_id = $this->factory()->user->create( array(
			'role'            => 'subscriber',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-31 days' ) );

		$user = get_user_by( 'ID', $user_id );
		$user->set_role( 'administrator' );

		$last_seen = new User_Last_Seen();
		$last_seen->init();

		$this->assertTrue( $last_seen->is_considered_inactive( $user_id ) );
	}

	public function test__is_considered_inactive__should_consider_user_meta() {
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 30 );
		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-100 days' ) );

		$user_inactive_id = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user_inactive_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-31 days' ) );

		$user_active_id = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user_active_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-29 days' ) );

		$last_seen = new User_Last_Seen();
		$last_seen->init();

		$this->assertTrue( $last_seen->is_considered_inactive( $user_inactive_id ) );
		$this->assertFalse( $last_seen->is_considered_inactive( $user_active_id ) );
	}

	public function test__is_considered_inactive__should_return_false_if_user_meta_and_option_are_not_present() {
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 30 );
		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-100 days' ) );

		delete_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY );

		$user_without_meta = $this->factory()->user->create( array( 'role' => 'administrator' ) );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$this->assertFalse( $last_seen->is_considered_inactive( $user_without_meta ) );
	}

	public function test__is_considered_inactive__should_use_release_date_option_when_user_meta_is_not_defined() {
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );

		add_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-16 days' ) );

		$user_without_meta = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => '2020-01-01',
		) );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$this->assertTrue( $last_seen->is_considered_inactive( $user_without_meta ) );

		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-10 days' ) );

		$this->assertFalse( $last_seen->is_considered_inactive( $user_without_meta ) );
	}

	public function test__authenticate_should_not_return_error_when_user_is_active() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );
		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-100 days' ) );

		remove_all_filters( 'authenticate' );

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-5 days' ) );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$new_user = apply_filters( 'authenticate', $user, $user );

		$this->assertSame( $user->ID, $new_user->ID );
	}

	public function test__authenticate_should_return_an_error_when_user_is_inactive() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );
		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-100 days' ) );

		remove_all_filters( 'authenticate' );

		$user_id = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-100 days' ) );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$user = apply_filters( 'authenticate', $user, $user );

		$this->assertWPError( $user, 'Expected WP_Error object to be returned' );
	}

	public function test__rest_authentication_should_return_an_error_when_user_is_inactive() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );
		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-100 days' ) );

		remove_all_filters( 'wp_is_application_passwords_available_for_user' );
		remove_all_filters( 'rest_authentication_errors' );

		$user_id = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-100 days' ) );
		$user = get_user_by( 'id', $user_id );

		wp_set_current_user( $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$available = apply_filters( 'wp_is_application_passwords_available_for_user', true, $user );
		$this->assertFalse( $available );

		$rest_authentication_errors = apply_filters( 'rest_authentication_errors', true );

		$this->assertSame( 'inactive_account', $rest_authentication_errors->get_error_code() );
	}

	public function test__rest_authentication_should_not_block_when_action_is_not_block() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'REPORT' );
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );
		update_option( User_Last_Seen::LAST_SEEN_RELEASE_DATE_TIMESTAMP_OPTION_KEY, strtotime( '-100 days' ) );

		remove_all_filters( 'wp_is_application_passwords_available_for_user' );
		remove_all_filters( 'rest_authentication_errors' );
		remove_all_filters( 'determine_current_user' );

		$user_id = $this->factory()->user->create( array(
			'role'            => 'administrator',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-16 days' ) );

		$user = get_user_by( 'id', $user_id );

		wp_set_current_user( $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$available = apply_filters( 'wp_is_application_passwords_available_for_user', true, $user );
		$this->assertTrue( $available );

		$rest_authentication_errors = apply_filters( 'rest_authentication_errors', true );

		$this->assertNotWPError( $rest_authentication_errors );
	}

	public function test__register_release_date_should_register_release_date_only_once() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'RECORD_LAST_SEEN' );

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

	public function test__authenticate_should_not_consider_users_without_elevated_capabilities() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );

		remove_all_filters( 'authenticate' );

		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-100 days' ) );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		$this->assertSame( $user, apply_filters( 'authenticate', $user, $user ) );
	}

	public function test__should_check_user_last_seen_should_call_elevated_capabilities_filters() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );

		remove_all_filters( 'authenticate' );
		remove_all_filters( 'vip_security_last_seen_elevated_capabilities' );

		$user_id = $this->factory()->user->create( array(
			'role'            => 'subscriber',
			'user_registered' => '2020-01-01',
		) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-100 days' ) );

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

	public function test__should_check_user_last_seen_should_call_skip_users_filters() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );

		remove_all_filters( 'authenticate' );
		remove_all_filters( 'vip_security_last_seen_elevated_capabilities' );

		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, strtotime( '-100 days' ) );

		$user = get_user_by( 'id', $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		add_filter( 'vip_security_last_seen_skip_users', function ( $users ) use ( $user_id ) {
			$users[] = $user_id;

			return $users;
		} );

		$this->assertSame( $user, apply_filters( 'authenticate', $user, $user ) );
	}

	public function test__record_activity_should_be_stored_only_once() {
		Constant_Mocker::define( 'VIP_SECURITY_INACTIVE_USERS_ACTION', 'BLOCK' );
		Constant_Mocker::define( 'VIP_SECURITY_CONSIDER_USERS_INACTIVE_AFTER_DAYS', 15 );

		remove_all_filters( 'determine_current_user' );

		$user_id         = $this->factory()->user->create( array(
			'role'            => 'subscriber',
			'user_registered' => '2020-01-01',
		) );
		$first_last_seen = strtotime( '-100 days' );
		add_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, $first_last_seen );

		wp_set_current_user( $user_id );

		$last_seen = new \Automattic\VIP\Security\User_Last_Seen();
		$last_seen->init();

		apply_filters( 'determine_current_user', $user_id );
		$current_last_seen = get_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, true );
		$this->assertIsNumeric( $current_last_seen );
		$this->assertNotEquals( $first_last_seen, $current_last_seen );

		$test_value = 12345;
		update_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, $test_value );

		apply_filters( 'determine_current_user', $user_id );
		$current_last_seen = get_user_meta( $user_id, User_Last_Seen::LAST_SEEN_META_KEY, true );
		$this->assertEquals( $test_value, $current_last_seen );
	}
}
