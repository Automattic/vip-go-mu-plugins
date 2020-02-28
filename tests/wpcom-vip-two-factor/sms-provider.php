<?php

class Two_Factor_SMS__Test extends \WP_UnitTestCase {
 public function test__formatting() {
  $token = '123456'
  $expected = $token . " is your " . $domain . " verification code.\r\n" . "@" . $domain . " #" . $token;
  $actual = format_sms_message( $token );

  $this->assertEquals( $expected, $actual );
 }
}
