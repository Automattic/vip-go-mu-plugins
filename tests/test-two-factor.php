<?php

namespace Automattic\VIP\Tests;

require_once dirname( __DIR__ ) . '/wpcom-vip-two-factor/is-jetpack-sso.php';

class VIP_Go_Two_Factor_Test extends \WP_UnitTestCase {

	public $valid_user_id;
	public $invalid_user_id;

	public $valid_expire;
	public $invalid_expire;

	public function setUp() {
		$username = 'testuser_' . mt_rand();
		$password = wp_generate_password( 12 );
		$this->valid_user_id = wp_create_user( $username, $password, $username . '@example.com' );
		wp_set_current_user( $this->valid_user_id );

		$this->invalid_user_id = 999999;
		$this->valid_expire = strtotime( '+1 day' );
		$this->invalid_expire = strtotime( '-1 day' );
	}

	public function test__valid_cookie() {
		$scheme = 'sso';

		$cookie = \Automattic\VIP\TwoFactor\create_cookie( $this->valid_user_id, $this->valid_expire, $scheme );
		$valid = \Automattic\VIP\TwoFactor\verify_cookie( $cookie, $scheme );

		$this->assertTrue( $valid );
	}

	public  function test__invalid_user() {
		$scheme = 'sso';

		$cookie = \Automattic\VIP\TwoFactor\create_cookie( $this->invalid_user_id, $this->valid_expire, $scheme );
		$valid = \Automattic\VIP\TwoFactor\verify_cookie( $cookie, $scheme );

		$this->assertFalse( $valid );
	}

	public function test__expired_cookie() {
		$scheme = 'sso';

		$cookie = \Automattic\VIP\TwoFactor\create_cookie( $this->valid_user_id, $this->invalid_expire, $scheme );
		$valid = \Automattic\VIP\TwoFactor\verify_cookie( $cookie, $scheme );

		$this->assertFalse( $valid );
	}

}
