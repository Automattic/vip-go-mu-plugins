<?php

namespace Automattic\VIP\Security;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;
use WP_User;

use function Automattic\Test\Utils\get_class_method_as_public;

require_once __DIR__ . '/../../security/class-lockout.php';
require_once __DIR__ . '/../../vip-support/class-vip-support-user.php';
require_once __DIR__ . '/../../vip-support/class-vip-support-role.php';

// phpcs:disable WordPress.DB.DirectDatabaseQuery

class Lockout_Test extends WP_UnitTestCase {
	/**
	 * @var Lockout
	 */
	private $lockout;

	public function setUp(): void {
		parent::setUp();

		$this->lockout = new Lockout();
	}

	private function invoke_user_seen_notice( WP_User $user ): void {
		get_class_method_as_public( Lockout::class, 'user_seen_notice' )->invokeArgs( $this->lockout, [ $user ] );
	}

	public function get_test_data__notice_states() {
		return [
			'warning' => [ Lockout::ACCOUNT_STATUS_WARNING ],
			'locked'  => [ Lockout::ACCOUNT_STATUS_LOCK ],
		];
	}

	/**
	 * @dataProvider get_test_data__notice_states
	 */
	public function test__user_seen_notice( $state ) {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', $state );

		$user = $this->factory()->user->create_and_get();

		$this->invoke_user_seen_notice( $user );

		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY, true ),
			$state
		);
		$this->assertNotEmpty(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, true )
		);
	}

	public function test__user_seen_notice__already_seen() {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', Lockout::ACCOUNT_STATUS_LOCK );

		$user = $this->factory()->user->create_and_get();

		$date_str = gmdate( 'Y-m-d H:i:s' );
		add_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY, Lockout::ACCOUNT_STATUS_WARNING, true );
		add_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, $date_str, true );

		$this->invoke_user_seen_notice( $user );

		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY, true ),
			Lockout::ACCOUNT_STATUS_WARNING
		);
		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, true ),
			$date_str
		);
	}

	public function get_test_data__filter_user_has_cap() {
		return [
			'locked'   => [ Lockout::ACCOUNT_STATUS_LOCK, true ],
			'shutdown' => [ Lockout::ACCOUNT_STATUS_SHUTDOWN, true ],
			'warning'  => [ Lockout::ACCOUNT_STATUS_WARNING, false ],
			'no state' => [ null, false ],
		];
	}

	/**
	 * Locked states reduce an editor to the caps of a subscriber; other states leave the caps untouched.
	 *
	 * @dataProvider get_test_data__filter_user_has_cap
	 */
	public function test__filter_user_has_cap( $state, $expect_subscriber_caps ) {
		if ( null !== $state ) {
			Constant_Mocker::define( 'VIP_LOCKOUT_STATE', $state );
		}

		$user = $this->factory()->user->create_and_get( [
			'role' => 'editor',
		]);

		$user_cap     = $user->get_role_caps();
		$expected_cap = $expect_subscriber_caps ? get_role( 'subscriber' )->capabilities : $user_cap;

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertSameSetsWithIndex( $expected_cap, $actual_cap );
	}

	/**
	 * Lockout registration must restrict ordinary actors while preserving support access.
	 */
	public function test__locked_authorization_through_wordpress() {
		$editor     = $this->factory()->user->create_and_get( [ 'role' => 'editor' ] );
		$support_id = \Automattic\VIP\Support_User\User::add( [
			'user_email' => 'hook-support@automattic.com',
			'user_login' => 'hook-support',
			'user_pass'  => 'password',
		] );
		$support    = get_user_by( 'id', $support_id );
		$support->add_cap( 'edit_posts' );
		$this->assertTrue( is_automattician( $support_id ) );
		$this->assertTrue( user_can( $editor, 'edit_posts' ) );
		$this->assertTrue( user_can( $support_id, 'edit_posts' ) );

		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', 'locked' );
		$this->lockout = new Lockout();

		$this->assertTrue( $editor->has_cap( 'read' ) );
		$this->assertFalse( $editor->has_cap( 'edit_posts' ) );
		$this->assertFalse( user_can( $editor, 'delete_posts' ) );
		$this->assertTrue( user_can( $support_id, 'edit_posts' ) );
	}

	public function get_test_data__filter_site_admin_option() {
		return [
			'locked'   => [ Lockout::ACCOUNT_STATUS_LOCK, [] ],
			'shutdown' => [ Lockout::ACCOUNT_STATUS_SHUTDOWN, [] ],
			'warning'  => [ Lockout::ACCOUNT_STATUS_WARNING, [ 'test1', 'test2' ] ],
		];
	}

	/**
	 * @dataProvider get_test_data__filter_site_admin_option
	 */
	public function test__filter_site_admin_option( $state, $expected ) {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', $state );

		$actual = $this->lockout->filter_site_admin_option( [ 'test1', 'test2' ], 'site_admin', 1, '' );

		$this->assertSame( $expected, $actual );
	}

	public function get_test_data__site_admin_option_updates() {
		return [
			'locked'     => [ Lockout::ACCOUNT_STATUS_LOCK, true ],
			'shutdown'   => [ Lockout::ACCOUNT_STATUS_SHUTDOWN, true ],
			'not locked' => [ null, false ],
		];
	}

	/**
	 * When locked, super admin changes should be blocked; otherwise they should work fine.
	 *
	 * @dataProvider get_test_data__site_admin_option_updates
	 */
	public function test__filter_prevent_site_admin_option_updates( $state, $expect_blocked ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite' );
		}

		global $wpdb;

		// Arrange: Have an existing user that's a super admin
		$user = $this->factory()->user->create_and_get();
		grant_super_admin( $user->ID );

		if ( null !== $state ) {
			Constant_Mocker::define( 'VIP_LOCKOUT_STATE', $state );
			Constant_Mocker::define( 'VIP_LOCKOUT_MESSAGE', 'Oh no!' );
		}

		// Recreate Lockout to re-init filters
		$this->lockout = new Lockout();

		$original_site_admins = maybe_unserialize(
			$wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = 'site_admins' LIMIT 1" )
		);

		// Act: Try granting another user super admin
		$user2 = $this->factory()->user->create_and_get();
		grant_super_admin( $user2->ID );

		// Assert: Check the raw value to avoid conflicts with the filter
		$actual_site_admins = maybe_unserialize(
			$wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = 'site_admins' LIMIT 1" )
		);

		$expected_site_admins = $expect_blocked ? $original_site_admins : array_merge( $original_site_admins, [ $user2->user_login ] );
		$this->assertEquals( $expected_site_admins, $actual_site_admins );
	}
}
