<?php

use Automattic\VIP\Utils\Jetpack_IP_Manager;

class WPCOM_VIP_Utils_Vip_Is_Jetpack_Request_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		add_filter( 'pre_http_request', function ( $response, $args, $url ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$response = [
					'headers'  => [],
					'body'     => '["122.248.245.244\/32","54.217.201.243\/32","54.232.116.4\/32","192.0.80.0\/20","192.0.96.0\/20","192.0.112.0\/20","195.234.108.0\/22"]',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
				];
			}

			return $response;
		}, 10, 3 );
	}

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
