<?php

class VIP_Lockout_Test extends WP_UnitTestCase {

	/**
	 * @var VIP_Lockout
	 */
	private $lockout;

	protected $preserveGlobalState = FALSE;
	protected $runTestInSeparateProcess = TRUE;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../vip-lockout.php';
	}

	public function setUp() {
		parent::setUp();

		$this->lockout = new VIP_Lockout();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass(  'VIP_Lockout' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	public function test__user_seen_warning() {
		$user = $this->factory->user->create_and_get();

		$user_seen_warning = self::get_method( 'user_seen_warning' );
		$user_seen_warning->invokeArgs( $this->lockout, [ $user ] );

		$this->assertEquals(
			get_user_meta( $user->ID, VIP_Lockout::USER_SEEN_WARNING_KEY , true ),
			1
		);
		$this->assertNotEmpty(
			get_user_meta( $user->ID, VIP_Lockout::USER_SEEN_WARNING_TIME_KEY, true )
		);
	}

	public function test__user_seen_warning__already_seen() {
		$user = $this->factory->user->create_and_get();

		$date_str = date('Y-m-d H:i:s');
		add_user_meta( $user->ID, VIP_Lockout::USER_SEEN_WARNING_KEY, true, true );
		add_user_meta( $user->ID, VIP_Lockout::USER_SEEN_WARNING_TIME_KEY, $date_str, true );

		$user_seen_warning = self::get_method( 'user_seen_warning' );
		$user_seen_warning->invokeArgs( $this->lockout, [ $user ] );

		$this->assertEquals(
			get_user_meta( $user->ID, VIP_Lockout::USER_SEEN_WARNING_KEY , true ),
			1
		);
		$this->assertEquals(
			get_user_meta( $user->ID, VIP_Lockout::USER_SEEN_WARNING_TIME_KEY, true ),
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
		require_once __DIR__ . '/../vip-support/class-vip-support-user.php';
		require_once __DIR__ . '/../vip-support/class-vip-support-role.php';

		define( 'VIP_LOCKOUT_STATE', 'locked' );

		$email = 'user@automattic.com';
		$user = $this->factory->user->create_and_get( [
			'role' => 'administrator',
			'user_email' => $email,
		] );
		add_user_meta( $user->ID, \Automattic\VIP\Support_User\User::META_EMAIL_VERIFIED, $email );

		$user_cap = $user->get_role_caps();

		$actual_cap = $this->lockout->filter_user_has_cap( $user_cap, [], [], $user );

		$this->assertEqualSets( $user_cap, $actual_cap );
	}
}
