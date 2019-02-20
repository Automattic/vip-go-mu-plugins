<?php

namespace Automattic\VIP\Security;

class Lockout_Test extends \WP_UnitTestCase {

	/**
	 * @var Lockout
	 */
	private $lockout;

	/**
	 * Make tests run in separate processes and don't preserve global state so
	 * that constants set in tests won't affect one another.
	 */
	protected $preserveGlobalState = FALSE;
	protected $runTestInSeparateProcess = TRUE;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../../security/class-lockout.php';
	}

	public function setUp() {
		parent::setUp();

		$this->lockout = new Lockout();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass( 'Automattic\VIP\Security\Lockout' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	public function test__user_seen_notice__warning() {
		define( 'VIP_LOCKOUT_STATE', 'warning' );

		$user = $this->factory->user->create_and_get();

		$user_seen_notice = self::get_method( 'user_seen_notice' );
		$user_seen_notice->invokeArgs( $this->lockout, [ $user ] );

		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY , true ),
			VIP_LOCKOUT_STATE
		);
		$this->assertNotEmpty(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, true )
		);
	}

	public function test__user_seen_notice__locked() {
		define( 'VIP_LOCKOUT_STATE', 'locked' );

		$user = $this->factory->user->create_and_get();

		$user_seen_notice = self::get_method( 'user_seen_notice' );
		$user_seen_notice->invokeArgs( $this->lockout, [ $user ] );

		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY , true ),
			VIP_LOCKOUT_STATE
		);
		$this->assertNotEmpty(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, true )
		);
	}

	public function test__user_seen_notice__already_seen() {
		define( 'VIP_LOCKOUT_STATE', 'locked' );

		$user = $this->factory->user->create_and_get();

		$date_str = date('Y-m-d H:i:s');
		add_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY, 'warning', true );
		add_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, $date_str, true );

		$user_seen_notice = self::get_method( 'user_seen_notice' );
		$user_seen_notice->invokeArgs( $this->lockout, [ $user ] );

		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_KEY , true ),
			'warning'
		);
		$this->assertEquals(
			get_user_meta( $user->ID, Lockout::USER_SEEN_WARNING_TIME_KEY, true ),
			$date_str
		);
	}

	public function test__filter_user_has_cap__locked() {
		define( 'VIP_LOCKOUT_STATE', 'locked' );

		$user = $this->factory->user->create_and_get( [
			'role' => 'editor'
		]);

		$user_cap = $user->get_role_caps();
		$expected_cap = get_role( 'subscriber' )->capabilities;

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertEqualSets( $expected_cap, $actual_cap );
	}

	public function test__filter_user_has_cap__warning() {
		define( 'VIP_LOCKOUT_STATE', 'warning' );

		$user = $this->factory->user->create_and_get( [
			'role' => 'editor'
		]);

		$user_cap = $user->get_role_caps();

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertEqualSets( $user_cap, $actual_cap );
	}

	public function test__filter_user_has_cap__no_state() {
		$user = $this->factory->user->create_and_get( [
			'role' => 'editor'
		]);

		$user_cap = $user->get_role_caps();

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertEqualSets( $user_cap, $actual_cap );
	}

	public function test__filter_user_has_cap__locked_vip_support() {
		require_once __DIR__ . '/../../vip-support/class-vip-support-user.php';
		require_once __DIR__ . '/../../vip-support/class-vip-support-role.php';

		define( 'VIP_LOCKOUT_STATE', 'locked' );

		$user_id = \Automattic\VIP\Support_User\User::add( [
			'user_email' => 'user@automattic.com',
			'user_login' => 'vip-support',
			'user_pass' => 'password',
		] );

		$user = wp_set_current_user( $user_id );

		$user_cap = $user->get_role_caps();

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertEqualSets( $user_cap, $actual_cap );
	}

	public function test__filter_site_admin_option__locked() {
		define( 'VIP_LOCKOUT_STATE', 'locked' );

		$pre_option = [ 'test1', 'test2' ];

		$actual = $this->lockout->filter_site_admin_option( $pre_option, 'site_admin', 1, '' );

		$this->assertEmpty( $actual );
	}

	public function test__filter_site_admin_option__warning() {
		define( 'VIP_LOCKOUT_STATE', 'warning' );

		$pre_option = [ 'test1', 'test2' ];

		$actual = $this->lockout->filter_site_admin_option( $pre_option, 'site_admin', 1, '' );

		$this->assertEqualSets( $pre_option, $actual );
	}
}
