<?php

namespace Automattic\VIP\Helpers;

use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/../../vip-helpers/class-user-cleanup.php';

class User_Cleanup_Test extends WP_UnitTestCase {
	public function data_provider__parse_emails_string() {
		return [
			'empty'                       => [ '', [] ],
			'null'                        => [ null, [] ],
			'false'                       => [ false, [] ],

			'spaces'                      => [ '  ', [] ],
			'spaces and commas'           => [ ' , ', [] ],

			'single email'                => [
				'user@example.com',
				[
					'user@example.com',
				],
			],

			'multiple emails'             => [
				'user@example.com,another@example.net',
				[
					'user@example.com',
					'another@example.net',
				],
			],

			'multiples with spaces'       => [
				' user@example.com,   another@example.net',
				[
					'user@example.com',
					'another@example.net',
				],
			],

			'multiples with some invalid' => [
				'user@example.com,,invalid,another@example.net,!!!',
				[
					'user@example.com',
					'another@example.net',
				],
			],
		];
	}

	/**
	 * @dataProvider data_provider__parse_emails_string
	 */
	public function test__parse_emails_string( $emails_string, $expected_emails ) {
		$actual_emails = User_Cleanup::parse_emails_string( $emails_string );

		$this->assertEquals( $expected_emails, $actual_emails );
	}

	public function data_provider__split_email() {
		return [
			'basic email'  => [
				'user@example.com',
				[
					'user',
					'example.com',
				],
			],

			'email with +' => [
				'user.name+ext@example.com',
				[
					'user.name',
					'example.com',
				],
			],
		];
	}

	/**
	 * @dataProvider data_provider__split_email
	 */
	public function test__split_email( $email, $expected_split ) {
		$actual_split = User_Cleanup::split_email( $email );

		$this->assertEquals( $expected_split, $actual_split );
	}

	public function test__fetch_user_ids_for_emails__exact_match() {
		$user_1_email = 'user@example.com';
		$user_1_id    = $this->factory->user->create( array( 'user_email' => $user_1_email ) );

		$expected_ids = [ $user_1_id ];

		$actual_ids = User_Cleanup::fetch_user_ids_for_emails( [ $user_1_email ] );

		$this->assertEquals( $expected_ids, $actual_ids );
	}

	public function test__fetch_user_ids_for_emails__exact_match_multiples() {
		$user_1_email = 'user@example.com';
		$user_1_id    = $this->factory->user->create( array( 'user_email' => $user_1_email ) );
		$user_2_email = 'user2@other.com';
		$user_2_id    = $this->factory->user->create( array( 'user_email' => $user_2_email ) );

		$expected_ids = [ $user_1_id, $user_2_id ];

		$actual_ids = User_Cleanup::fetch_user_ids_for_emails( [ $user_1_email, $user_2_email ] );

		$this->assertEquals( $expected_ids, $actual_ids );
	}

	public function test__fetch_user_ids_for_emails__exact_match_with_plus() {
		$user_1_email = 'user+extra@example.com';
		$user_1_id    = $this->factory->user->create( array( 'user_email' => $user_1_email ) );

		$expected_ids = [ $user_1_id ];

		$actual_ids = User_Cleanup::fetch_user_ids_for_emails( [ $user_1_email ] );

		$this->assertEquals( $expected_ids, $actual_ids );
	}

	public function test__fetch_user_ids_for_emails__email_with_plus() {
		$user_1_email = 'user+extra@example.com';
		$user_1_id    = $this->factory->user->create( array( 'user_email' => $user_1_email ) );

		$expected_ids = [ $user_1_id ];

		$actual_ids = User_Cleanup::fetch_user_ids_for_emails( [ 'user@example.com' ] );

		$this->assertEquals( $expected_ids, $actual_ids );
	}

	public function test__fetch_user_ids_for_emails__username_match_different_host() {
		$user_1_email = 'user+extra@different.com';
		$this->factory->user->create( array( 'user_email' => $user_1_email ) );

		$expected_ids = []; // emails will not match

		$actual_ids = User_Cleanup::fetch_user_ids_for_emails( [ 'user@example.com' ] );

		$this->assertEquals( $expected_ids, $actual_ids );
	}

	public function test__fetch_user_ids_for_emails__host_match_different_username() {
		$user_1_email = 'different+extra@example.com';
		$this->factory->user->create( array( 'user_email' => $user_1_email ) );

		$expected_ids = []; // emails will not match

		$actual_ids = User_Cleanup::fetch_user_ids_for_emails( [ 'user@example.com' ] );

		$this->assertEquals( $expected_ids, $actual_ids );
	}

	public function test__fetch_user_ids_for_emails__no_caps() {
		$user_1_email = 'user@example.com';
		$user_1_id    = $this->factory->user->create( array( 'user_email' => $user_1_email ) );

		get_userdata( $user_1_id )->remove_all_caps();

		// Should not return user since they are not a member of the blog anymore
		$expected_ids = [];

		$actual_ids = User_Cleanup::fetch_user_ids_for_emails( [ 'user@example.com' ] );

		$this->assertEquals( $expected_ids, $actual_ids );
	}

	public function test__revoke_super_admin_for_users__singlesite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Test specific to single site installations' );
		}

		$this->backup_super_admins();

		$user_id_1 = $this->factory->user->create();
		$user_id_2 = $this->factory->user->create();
		grant_super_admin( $user_id_1 );
		grant_super_admin( $user_id_2 );

		// False because super admin is not a thing in single site
		$expected_results = [
			$user_id_1 => false,
			$user_id_2 => false,
		];

		$actual_results = User_Cleanup::revoke_super_admin_for_users( [ $user_id_1, $user_id_2 ] );

		$this->assertEquals( $expected_results, $actual_results );

		$this->restore_super_admins();
	}

	public function test__revoke_super_admin_for_users__multisite__one_user() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Test specific to multisite installations.' );
		}

		$this->backup_super_admins();

		$user_id_1 = $this->factory->user->create();
		grant_super_admin( $user_id_1 );

		$expected_results = [
			$user_id_1 => true,
		];

		$expected_super_admins = [ 'admin' ];

		$actual_results = User_Cleanup::revoke_super_admin_for_users( [ $user_id_1 ] );

		$this->assertEquals( $expected_results, $actual_results );
		$this->assertEquals( $expected_super_admins, get_super_admins(), 'get_super_admins() is incorrect' );

		$this->restore_super_admins();
	}

	public function test__revoke_super_admin_for_users__multisite__all_super_admins() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Test specific to multisite installations.' );
		}

		$this->backup_super_admins();

		$user_id_1 = $this->factory->user->create();
		$user_id_2 = $this->factory->user->create();
		grant_super_admin( $user_id_1 );
		grant_super_admin( $user_id_2 );

		$expected_results = [
			$user_id_1 => true,
			$user_id_2 => true,
		];

		$expected_super_admins = [ 'admin' ];

		$actual_results = User_Cleanup::revoke_super_admin_for_users( [ $user_id_1, $user_id_2 ] );

		$this->assertEquals( $expected_results, $actual_results, 'Return value from revoke_super_admin_for_users was incorrect' );
		$this->assertEquals( $expected_super_admins, get_super_admins(), 'get_super_admins() is incorrect' );

		$this->restore_super_admins();
	}

	public function test__revoke_super_admin_for_users__multisite__some_super_admins() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Test specific to multisite installations.' );
		}

		$this->backup_super_admins();

		$user_id_1 = $this->factory->user->create();
		$user_id_2 = $this->factory->user->create();
		// user_id_1 is not a super admin
		grant_super_admin( $user_id_2 );

		$expected_results = [
			$user_id_1 => false, // not a super admin so false
			$user_id_2 => true,
		];

		$expected_super_admins = [ 'admin' ];

		$actual_results = User_Cleanup::revoke_super_admin_for_users( [ $user_id_1, $user_id_2 ] );

		$this->assertEquals( $expected_results, $actual_results, 'Return value from revoke_super_admin_for_users was incorrect' );
		$this->assertEquals( $expected_super_admins, get_super_admins(), 'get_super_admins() is incorrect' );

		$this->restore_super_admins();
	}

	public function test__revoke_super_admin_for_users__multisite__existing_super_admins() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Test specific to multisite installations.' );
		}

		$this->backup_super_admins();

		// 3 super admins, and we only revoke two of them
		$user_id_1 = $this->factory->user->create();
		$user_id_2 = $this->factory->user->create();
		$user_id_3 = $this->factory->user->create();
		grant_super_admin( $user_id_1 );
		grant_super_admin( $user_id_2 );
		grant_super_admin( $user_id_3 );

		$expected_results = [
			$user_id_1 => true,
			$user_id_2 => true,
		];

		$expected_super_admins = [ 'admin', get_userdata( $user_id_3 )->user_login ];

		$actual_results = User_Cleanup::revoke_super_admin_for_users( [ $user_id_1, $user_id_2 ] );

		$this->assertEquals( $expected_results, $actual_results, 'Return value from revoke_super_admin_for_users was incorrect' );
		$this->assertEquals( $expected_super_admins, array_values( get_super_admins() ), 'get_super_admins() is incorrect' );

		$this->restore_super_admins();
	}

	public function test__revoke_roles_for_users() {
		$user_1_id = $this->factory->user->create();

		$expected_results = [
			$user_1_id => true,
		];

		$actual_results = User_Cleanup::revoke_roles_for_users( [ $user_1_id ] );

		$user_1 = get_userdata( $user_1_id );

		$this->assertEquals( $expected_results, $actual_results, 'revoke_roles_for_users returned incorrect results' );

		$this->assertEquals( $user_1->roles, [], 'User 1 roles field was not empty' );
		$this->assertEquals( $user_1->caps, [], 'User 2 caps field was not empty' );

		if ( is_multisite() ) {
			$this->assertFalse( is_user_member_of_blog( $user_1_id ), 'is_user_member_of_blog did not return false' );
		}
	}

	public function test__revoke_roles_for_users_nonexisting() {
		$user_id        = -1;
		$actual_results = User_Cleanup::revoke_roles_for_users( [ $user_id ] );
		$this->assertIsArray( $actual_results );
		$this->assertArrayHasKey( $user_id, $actual_results );
		$this->assertInstanceOf( WP_Error::class, $actual_results[ $user_id ] );
	}

	private function backup_super_admins() {
		if ( isset( $GLOBALS['super_admins'] ) ) {
			$this->_old_superadmins = $GLOBALS['super_admins'];
			unset( $GLOBALS['super_admins'] );
		}
	}

	private function restore_super_admins() {
		if ( isset( $this->_old_superadmins ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['super_admins'] = $this->_old_superadmins;
		}
	}
}
