<?php

class WPCOM_VIP_Utils_Vip_Is_Jetpack_Request_Test extends WP_UnitTestCase {

	public function test__vip_is_jetpack_request__shortcut() {
		//phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		$_SERVER['HTTP_USER_AGENT'] = 'something_else';

		$this->assertFalse( vip_is_jetpack_request() );
	}

	public function test__vip_is_jetpack_request__true() {
		//phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		$_SERVER['HTTP_USER_AGENT'] = 'jetpack';
		//phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.96.202';

		$this->assertTrue( vip_is_jetpack_request() );
	}

}
