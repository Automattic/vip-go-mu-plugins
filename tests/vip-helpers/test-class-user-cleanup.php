<?php

namespace Automattic\VIP\Helpers;

use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/../../vip-helpers/class-user-cleanup.php';

class User_Cleanup_Test extends WP_UnitTestCase {
	/**
	 * A set $super_admins global makes grant_super_admin() a no-op, so tests run without it.
	 *
	 * @var array|null
	 */
	private $original_super_admins = null;

	public function setUp(): void {
		parent::setUp();

		if ( isset( $GLOBALS['super_admins'] ) ) {
			$this->original_super_admins = $GLOBALS['super_admins'];
			unset( $GLOBALS['super_admins'] );
		}
	}

	public function tearDown(): void {
		if ( null !== $this->original_super_admins ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['super_admins'] = $this->original_super_admins;
		}

		parent::tearDown();
	}

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

	public function data_provider__fetch_user_ids_for_emails() {
		return [
			'exact match'                   => [ [ 'user@example.com' ], [ 'user@example.com' ], [ 0 ] ],
			'exact match multiples'         => [ [ 'user@example.com', 'user2@other.com' ], [ 'user@example.com', 'user2@other.com' ], [ 0, 1 ] ],
			'exact match with plus'         => [ [ 'user+extra@example.com' ], [ 'user+extra@example.com' ], [ 0 ] ],
			'email with plus'               => [ [ 'user+extra@example.com' ], [ 'user@example.com' ], [ 0 ] ],
			// emails will not match
			'username match different host' => [ [ 'user+extra@different.com' ], [ 'user@example.com' ], [] ],
			'host match different username' => [ [ 'different+extra@example.com' ], [ 'user@example.com' ], [] ],
			// Should not return user since they are not a member of the blog anymore
			'no caps'                       => [ [ 'user@example.com' ], [ 'user@example.com' ], [], true ],
		];
	}

	/**
	 * @dataProvider data_provider__fetch_user_ids_for_emails
	 */
	public function test__fetch_user_ids_for_emails( $user_emails, $search_emails, $expected_user_indexes, $remove_caps = false ) {
		$user_ids = [];
		foreach ( $user_emails as $user_email ) {
			$user_ids[] = $this->factory()->user->create( array( 'user_email' => $user_email ) );
		}

		if ( $remove_caps ) {
			get_userdata( $user_ids[0] )->remove_all_caps();
		}

		$expected_ids = array_map( fn( $index ) => $user_ids[ $index ], $expected_user_indexes );

		$actual_ids = User_Cleanup::fetch_user_ids_for_emails( $search_emails );

		$this->assertEquals( $expected_ids, $actual_ids );
	}

	public function test__revoke_super_admin_for_users__singlesite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Test specific to single site installations' );
		}

		$user_id_1 = $this->factory()->user->create();
		$user_id_2 = $this->factory()->user->create();
		grant_super_admin( $user_id_1 );
		grant_super_admin( $user_id_2 );

		// False because super admin is not a thing in single site
		$expected_results = [
			$user_id_1 => false,
			$user_id_2 => false,
		];

		$actual_results = User_Cleanup::revoke_super_admin_for_users( [ $user_id_1, $user_id_2 ] );

		$this->assertEquals( $expected_results, $actual_results );
	}

	public function data_provider__revoke_super_admin_for_users__multisite() {
		return [
			// Users are referenced by index: [ user count, super admins, revoked, expected results, remaining super admins ]
			'one user'              => [ 1, [ 0 ], [ 0 ], [ true ], [] ],
			'all super admins'      => [ 2, [ 0, 1 ], [ 0, 1 ], [ true, true ], [] ],
			'some super admins'     => [ 2, [ 1 ], [ 0, 1 ], [ false, true ], [] ],
			'existing super admins' => [ 3, [ 0, 1, 2 ], [ 0, 1 ], [ true, true ], [ 2 ] ],
		];
	}

	/**
	 * @dataProvider data_provider__revoke_super_admin_for_users__multisite
	 */
	public function test__revoke_super_admin_for_users__multisite( $user_count, $super_admins, $revoked, $expected_results, $remaining_super_admins ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Test specific to multisite installations.' );
		}

		$user_ids = $this->factory()->user->create_many( $user_count );
		foreach ( $super_admins as $index ) {
			grant_super_admin( $user_ids[ $index ] );
		}

		$revoked_ids           = array_map( fn( $index ) => $user_ids[ $index ], $revoked );
		$expected_super_admins = array_merge(
			[ 'admin' ],
			array_map( fn( $index ) => get_userdata( $user_ids[ $index ] )->user_login, $remaining_super_admins )
		);

		$actual_results = User_Cleanup::revoke_super_admin_for_users( $revoked_ids );

		$this->assertEquals( array_combine( $revoked_ids, $expected_results ), $actual_results, 'Return value from revoke_super_admin_for_users was incorrect' );
		$this->assertEquals( $expected_super_admins, array_values( get_super_admins() ), 'get_super_admins() is incorrect' );
	}

	public function test__revoke_roles_for_users() {
		$user_1_id = $this->factory()->user->create();

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
}
