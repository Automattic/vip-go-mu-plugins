<?php

require_once __DIR__ . '/../../shared-plugins/two-factor/two-factor.php';
require_once __DIR__ . '/../../wpcom-vip-two-factor/sms-provider.php';

class Two_Factor_SMS_Test extends WP_UnitTestCase {
	public function test__two_factor_sms_formatting() {
		$token    = 123456;
		$expected = '123456 is your Test Blog verification code.' . "\n\n" . '@example.org #123456';

		$two_factor = Two_Factor_SMS::get_instance();
		$actual     = $two_factor->format_sms_message( $token );

		$this->assertEquals( $expected, $actual );
	}

	public function test__two_factor_sms_formatting__code_with_leading_zero() {
		$token    = 0123456;
		$expected = '0123456 is your Test Blog verification code.' . "\n\n" . '@example.org #0123456';

		$two_factor = Two_Factor_SMS::get_instance();
		$actual     = $two_factor->format_sms_message( $token );

		$this->assertEquals( $expected, $actual );
	}
}
