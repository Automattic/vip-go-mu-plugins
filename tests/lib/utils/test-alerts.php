<?php

namespace Automattic\VIP\Utils;

class Alerts_Test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../../../lib/utils/class-alerts.php';
	}

	public function mock_http_response( $mocked_response, $response_time = 1 ) {
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $mocked_response, $response_time ) {
			usleep( $response_time * 1000000 );

			return $mocked_response;
		}, 10, 3 );
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_alerts_method( $name ) {
		$class  = new \ReflectionClass( Alerts::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__instance() {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$alerts = Alerts::instance();

		$this->assertTrue( $alerts instanceof Alerts );
		$this->assertEquals( 'test.host', $alerts->service_address, 'Wrong alerts service address' );
		$this->assertEquals( 9999, $alerts->service_port, 'Wrong alerts service port' );
		$this->assertEquals( 'http://test.host:9999/v1.0/alert', $alerts->service_url, 'Wrong alerts service URL' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__instance__missing_config() {
		$alerts = Alerts::instance();

		$this->assertWPError( $alerts );
		$this->assertEquals( 'missing-service-address', $alerts->get_error_code(), 'Wrong error code' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__instance__missing_port() {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );

		$alerts = Alerts::instance();

		$this->assertWPError( $alerts );
		$this->assertEquals( 'missing-service-port', $alerts->get_error_code(), 'Wrong error code' );
	}

	public function get_test_data__valid_channel_or_user() {
		return [
			'valid-channel' => [
				'#testchannel',
				'#testchannel',
			],
			'valid-username' => [
				'username',
				'username',
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__valid_channel_or_user
	 */
	public function test__validate_channel_or_user( $channel_or_user, $expected ) {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$alerts = Alerts::instance();

		$validate_channel_or_user_method = self::get_alerts_method( 'validate_channel_or_user' );

		$result = $validate_channel_or_user_method->invokeArgs( $alerts, [ $channel_or_user ] );

		$this->assertEquals( $expected, $result );
	}

	public function get_test_data__invalid_channel_or_user() {
		return [
			'invalid-characters' => [
				'&*%$!^',
			],
			'empty-channel-or-user' => [
				'',
			]
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__invalid_channel_or_user
	 */
	public function test__validate_channel_or_user__invalid_data( $channel_or_user ) {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$alerts = Alerts::instance();

		$validate_channel_or_user_method = self::get_alerts_method( 'validate_channel_or_user' );

		$result = $validate_channel_or_user_method->invokeArgs( $alerts, [ $channel_or_user ] );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-channel-or-user', $result->get_error_code() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__validate_message() {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$alerts = Alerts::instance();

		$validate_message_method = self::get_alerts_method( 'validate_message' );

		$result = $validate_message_method->invokeArgs( $alerts, [ 'Test message ' ] );

		$this->assertEquals( 'Test message', $result );
	}

	public function get_test_data__invalid_message() {
		return [
			'invalid-type' => [
				[],
			],
			'empty-message' => [
				'',
			]
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__invalid_message
	 */
	public function test__validate_message__invalid_message( $message ) {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$alerts = Alerts::instance();

		$validate_message_method = self::get_alerts_method( 'validate_message' );

		$result = $validate_message_method->invokeArgs( $alerts, [ $message ] );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-alert-message', $result->get_error_code(), 'Wrong error code' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__validate_opsgenie_details() {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$details = [
			'alias' => 'test/alert',
			'description' => 'Test alert',
			'entity' => 'test',
			'priority' => 'P4',
			'source' => 'test',
		];

		$alerts = Alerts::instance();

		$validate_opsgenie_details_method = self::get_alerts_method( 'validate_opsgenie_details' );

		$result = $validate_opsgenie_details_method->invokeArgs( $alerts, [ $details ] );

		$this->assertEquals( $details, $result );
	}

	public function get_test_data__invalid_details() {
		return [
			'invalid-type' => [ 'string' ],
			'missing-keys' => [ 
				'alias' => 'test/alert',
				'description' => 'Test alert',
				'entity' => 'test',
				'source' => 'test',
			],
			'extra-keys' => [ 
				'alias' => 'test/alert',
				'description' => 'Test alert',
				'entity' => 'test',
				'priority' => 'P4',
				'source' => 'test',
				'extra' => 'invalid',
			],
			'empty-keys' => [
				'alias' => 'test/alert',
				'description' => '',
				'entity' => 'test',
				'priority' => 'P4',
				'source' => 'test',
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__invalid_details
	 */
	public function test__validate_opsgenie_details__invalid_details( $details ) {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$alerts = Alerts::instance();

		$validate_opsgenie_details_method = self::get_alerts_method( 'validate_opsgenie_details' );

		$result = $validate_opsgenie_details_method->invokeArgs( $alerts, [ $details ] );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-opsgenie-details', $result->get_error_code(), 'Wrong error code' );
	}

	public function get_test_data__invalid_send_responses() {
		return [
			'not-found'    => [
				[
					'body'     => '{}',
					'response' => [
						'code'    => 404,
						'message' => 'Not found',
					],
					'cookies'  => [],
				],
			],
			'server-error' => [
				[
					'body'     => '{}',
					'response' => [
						'code'    => 500,
						'message' => 'Server error',
					],
					'cookies'  => [],
				],
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__invalid_send_responses
	 */
	public function test__send_function_failed_requests( $mock_response ) {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$this->mock_http_response( $mock_response );

		$alerts      = Alerts::instance();
		$send_method = self::get_alerts_method( 'send' );

		$body   = [ 'somekey' => 'someproperty' ];
		$result = $send_method->invokeArgs( $alerts, [ $body ] );

		$this->assertWPError( $result );
		$this->assertEquals( 'The request returned an invalid response: ' . $mock_response['response']['message'], $result->get_error_message() );
	}
}
