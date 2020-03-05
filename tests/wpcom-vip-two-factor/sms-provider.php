<?php

class Two_Factor_SMS_Test extends WP_UnitTestCase {
	public function setUp() {
		require_once __DIR__ . '/../../shared-plugins/two-factor/two-factor.php';
		require_once __DIR__ . '/../../wpcom-vip-two-factor/sms-provider.php';
	}

	public function test__two_factor_sms_formatting() {
		$two_factor = Two_Factor_SMS::get_instance();

		$token      = 123456;
		$site_title = 'Test Blog'; // used set values for test site
		$home_url   = 'example.org'; // used set values for test site

		$format = '%1$d is your %2$s verification code.' . "\n\n" . '@%3$s #%1$d';

		$expected = sprintf( $format, $token, $site_title, $home_url );
		$actual   = $two_factor->format_sms_message( $token );

		$this->assertEquals( $expected, $actual );
	}
}
