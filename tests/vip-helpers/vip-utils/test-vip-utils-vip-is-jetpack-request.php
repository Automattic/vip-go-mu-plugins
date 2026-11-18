<?php

use Automattic\VIP\Utils\Jetpack_IP_Manager;

use function Automattic\Test\Utils\http_response;

class WPCOM_VIP_Utils_Vip_Is_Jetpack_Request_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		add_filter( 'pre_http_request', function ( $response, $args, $url ) {
			if ( Jetpack_IP_Manager::ENDPOINT === $url ) {
				$response = http_response( 200, '["122.248.245.244\/32","54.217.201.243\/32","54.232.116.4\/32","192.0.80.0\/20","192.0.96.0\/20","192.0.112.0\/20","195.234.108.0\/22"]' );
			}

			return $response;
		}, 10, 3 );
	}


	/**
	 * @dataProvider data__vip_is_jetpack_request
	 */
	public function test__vip_is_jetpack_request( $user_agent, $xff, $expected ) {
		//phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		$_SERVER['HTTP_USER_AGENT'] = $user_agent;
		if ( null !== $xff ) {
			//phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $xff;
		}

		$this->assertSame( $expected, vip_is_jetpack_request() );
	}

	public function data__vip_is_jetpack_request() {
		return [
			'not the Jetpack user agent' => [ 'something_else', null, false ],
			'single Jetpack IP'          => [ 'jetpack', '192.0.96.202', true ],
			'Jetpack IP in a chain'      => [ 'jetpack', '127.0.0.1, 192.0.96.202, ::1', true ],
			'no Jetpack IP in a chain'   => [ 'jetpack', '127.0.0.1, ::1', false ],
		];
	}
}
