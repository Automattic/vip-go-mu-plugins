<?php

namespace Automattic\VIP\Security;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

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

		Constant_Mocker::clear();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class  = new \ReflectionClass( 'Automattic\VIP\Security\Lockout' );
		$method = $class->getMethod( $name );
		return $method;
	}

	public function get_test_data__locking_states() {
		return [
			'locked'   => [ Lockout::ACCOUNT_STATUS_LOCK ],
			'shutdown' => [ Lockout::ACCOUNT_STATUS_SHUTDOWN ],
		];
	}

	public function test__user_seen_notice__warning() {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', Lockout::ACCOUNT_STATUS_WARNING );

		$user = $this->factory()->user->create_and_get();

		$user_seen_notice = self::get_method( 'user_seen_notice' );
		$user_seen_notice->invokeArgs( $this->lockout, [ $user ] );

		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY, true ),
			Constant_Mocker::constant( 'VIP_LOCKOUT_STATE' )
		);
		$this->assertNotEmpty(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, true )
		);
	}

	public function test__user_seen_notice__locked() {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', Lockout::ACCOUNT_STATUS_LOCK );

		$user = $this->factory()->user->create_and_get();

		$user_seen_notice = self::get_method( 'user_seen_notice' );
		$user_seen_notice->invokeArgs( $this->lockout, [ $user ] );

		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY, true ),
			Constant_Mocker::constant( 'VIP_LOCKOUT_STATE' )
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

		$user_seen_notice = self::get_method( 'user_seen_notice' );
		$user_seen_notice->invokeArgs( $this->lockout, [ $user ] );

		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY, true ),
			Lockout::ACCOUNT_STATUS_WARNING
		);
		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, true ),
			$date_str
		);
	}

	/**
	 * @dataProvider get_test_data__locking_states
	 */
	public function test__filter_user_has_cap__locked( $state ) {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', $state );

		$user = $this->factory()->user->create_and_get( [
			'role' => 'editor',
		]);

		$user_cap     = $user->get_role_caps();
		$expected_cap = get_role( 'subscriber' )->capabilities;

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertSameSetsWithIndex( $expected_cap, $actual_cap );

		// Exercise WordPress capability resolution with the real lockout callback.
		add_filter( 'user_has_cap', [ $this->lockout, 'filter_user_has_cap' ], PHP_INT_MAX, 4 );
		try {
			$this->assertTrue( user_can( $user, 'read' ) );
			$this->assertFalse( user_can( $user, 'edit_posts' ) );
			$this->assertFalse( user_can( $user, 'delete_posts' ) );
		} finally {
			remove_filter( 'user_has_cap', [ $this->lockout, 'filter_user_has_cap' ], PHP_INT_MAX );
		}
	}

	public function test__filter_user_has_cap__warning() {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', Lockout::ACCOUNT_STATUS_WARNING );

		$user = $this->factory()->user->create_and_get( [
			'role' => 'editor',
		]);

		$user_cap = $user->get_role_caps();

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertSameSetsWithIndex( $user_cap, $actual_cap );
	}

	public function test__filter_user_has_cap__no_state() {
		$user = $this->factory()->user->create_and_get( [
			'role' => 'editor',
		]);

		$user_cap = $user->get_role_caps();

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertSameSetsWithIndex( $user_cap, $actual_cap );
	}

	public function test__filter_user_has_cap__locked_vip_support() {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', Lockout::ACCOUNT_STATUS_LOCK );

		$user_id = \Automattic\VIP\Support_User\User::add( [
			'user_email' => 'user@automattic.com',
			'user_login' => 'vip-support',
			'user_pass'  => 'password',
		] );

		$user = wp_set_current_user( $user_id );

		$user_cap = $user->get_role_caps();

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertSameSetsWithIndex( $user_cap, $actual_cap );
	}

	/**
	 * @dataProvider get_test_data__locking_states
	 */
	public function test__filter_site_admin_option__locked( $state ) {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', $state );

		$pre_option = [ 'test1', 'test2' ];

		$actual = $this->lockout->filter_site_admin_option( $pre_option, 'site_admin', 1, '' );

		$this->assertEmpty( $actual );
	}

	public function test__filter_site_admin_option__warning() {
		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', Lockout::ACCOUNT_STATUS_WARNING );

		$pre_option = [ 'test1', 'test2' ];

		$actual = $this->lockout->filter_site_admin_option( $pre_option, 'site_admin', 1, '' );

		$this->assertEqualSets( $pre_option, $actual );
	}

	/**
	 * When locked, super admin changes should be blocked.
	 *
	 * @dataProvider get_test_data__locking_states
	 */
	public function test__filter_prevent_site_admin_option_updates__locked( $state ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite' );
		}

		global $wpdb;

		// Arrange: Have an existing user that's a super admin
		$user = $this->factory()->user->create_and_get();
		grant_super_admin( $user->ID );

		Constant_Mocker::define( 'VIP_LOCKOUT_STATE', $state );
		Constant_Mocker::define( 'VIP_LOCKOUT_MESSAGE', 'Oh no!' );

		// Recreate Lockout to re-init filters
		$this->lockout = new Lockout();

		$expected_site_admins = maybe_unserialize(
			$wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = 'site_admins' LIMIT 1" )
		);

		// Act: Try granting another user super admin
		$user2 = $this->factory()->user->create_and_get();
		grant_super_admin( $user2->ID );

		// Assert: Check the raw value to avoid conflicts with the filter
		$actual_site_admins = maybe_unserialize(
			$wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = 'site_admins' LIMIT 1" )
		);

		$this->assertEquals( $expected_site_admins, $actual_site_admins );
	}

	/**
	 * When not locked, super admin changes should work fine.
	 */
	public function test__filter_prevent_site_admin_option_updates__not_locked() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite' );
		}

		global $wpdb;

		// Arrange: Have an existing user that's a super admin
		$user = $this->factory()->user->create_and_get();
		grant_super_admin( $user->ID );

		// No lockout enabled

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

		$this->assertNotEquals( $original_site_admins, $actual_site_admins, 'Before and after site_admins options were the same' );
		$this->assertContains( $user2->user_login, $actual_site_admins );
	}
}
