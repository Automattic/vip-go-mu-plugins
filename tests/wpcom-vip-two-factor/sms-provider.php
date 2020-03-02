<?php

class Two_Factor_SMS__Test extends \WP_UnitTestCase {
 public function setUp() {
		require_once __DIR__ . '/../wpcom-vip-two-factor/sms-provider.php';
	}

 public function test__two_factor_sms_formatting() {

  $mock = $this->createMock( Two_Factor_SMS::class )
						->setMethods( [ 'generate_and_send_token' ] )
						->getMock();

  $user = new stdClass;
  $user->name = 'fake-user';

  $mock->expects( $this->exactly( 1 ) )
       ->method( 'generate_and_send_token' )
       ->with( $user )
       ->will( $this->returnValue( null ) );
 }
}
