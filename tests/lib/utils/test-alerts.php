<?php

namespace Automattic\VIP\Utils;

use WP_UnitTestCase;

require_once __DIR__ . '/class-testable-alerts.php';

class Alerts_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		Testable_Alerts::$svc_address = 'test.host';
		Testable_Alerts::$svc_port    = 9999;
		Testable_Alerts::clear_instance();
	}

	public function mock_http_response( $mocked_response ) {
		add_filter( 'pre_http_request', function() use ( $mocked_response ) {
			return $mocked_response;
		}, 10, 3 );
	}

	public function test__instance() {
		$alerts = Testable_Alerts::instance();

		$this->assertTrue( $alerts instanceof Alerts );
		$this->assertEquals( 'test.host', $alerts->service_address, 'Wrong alerts service address' );
		$this->assertEquals( 9999, $alerts->service_port, 'Wrong alerts service port' );
		$this->assertEquals( 'http://test.host:9999/v1.0/alert', $alerts->service_url, 'Wrong alerts service URL' );
	}

	public function test__instance__missing_config() {
		Testable_Alerts::$svc_address = null;

		$alerts = Testable_Alerts::instance();

		$this->assertWPError( $alerts );
		$this->assertEquals( 'missing-service-address', $alerts->get_error_code(), 'Wrong error code' );
	}

	public function test__instance__missing_port() {
		Testable_Alerts::$svc_port = null;

		$alerts = Testable_Alerts::instance();

		$this->assertWPError( $alerts );
		$this->assertEquals( 'missing-service-port', $alerts->get_error_code(), 'Wrong error code' );
	}

	public function get_test_data__valid_channel_or_user() {
		return [
			'valid-channel'  => [
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
	 * @dataProvider get_test_data__valid_channel_or_user
	 */
	public function test__validate_channel_or_user( $channel_or_user, $expected ) {
		$alerts = Testable_Alerts::instance();

		$result = $alerts->validate_channel_or_user( $channel_or_user );

		$this->assertEquals( $expected, $result );
	}

	public function get_test_data__invalid_channel_or_user() {
		return [
			'invalid-characters'    => [
				'&*%$!^',
			],
			'empty-channel-or-user' => [
				'',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__invalid_channel_or_user
	 */
	public function test__validate_channel_or_user__invalid_data( $channel_or_user ) {
		$alerts = Testable_Alerts::instance();

		$result = $alerts->validate_channel_or_user( $channel_or_user );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-channel-or-user', $result->get_error_code() );
	}

	public function test__validate_message() {
		$alerts = Testable_Alerts::instance();

		$result = $alerts->validate_message( 'Test message ' );

		$this->assertEquals( 'Test message', $result );
	}

	public function get_test_data__invalid_message() {
		return [
			'invalid-type'  => [
				[],
			],
			'empty-message' => [
				'',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__invalid_message
	 */
	public function test__validate_message__invalid_message( $message ) {
		$alerts = Testable_Alerts::instance();

		$result = $alerts->validate_message( $message );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-alert-message', $result->get_error_code(), 'Wrong error code' );
	}

	public function test__validate_opsgenie_details() {
		$details = [
			'alias'       => 'test/alert',
			'description' => 'Test alert',
			'entity'      => 'test',
			'priority'    => 'P4',
			'source'      => 'test',
		];

		$alerts = Testable_Alerts::instance();

		$result = $alerts->validate_opsgenie_details( $details );

		$this->assertEquals( $details, $result );
	}

	public function get_test_data__invalid_details() {
		return [
			'invalid-type' => [ 'string' ],
			'missing-keys' => [ 
				'alias'       => 'test/alert',
				'description' => 'Test alert',
				'entity'      => 'test',
				'source'      => 'test',
			],
			'extra-keys'   => [ 
				'alias'       => 'test/alert',
				'description' => 'Test alert',
				'entity'      => 'test',
				'priority'    => 'P4',
				'source'      => 'test',
				'extra'       => 'invalid',
			],
			'empty-keys'   => [
				'alias'       => 'test/alert',
				'description' => '',
				'entity'      => 'test',
				'priority'    => 'P4',
				'source'      => 'test',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__invalid_details
	 */
	public function test__validate_opsgenie_details__invalid_details( $details ) {
		$alerts = Testable_Alerts::instance();

		$result = $alerts->validate_opsgenie_details( $details );

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
	 * @dataProvider get_test_data__invalid_send_responses
	 */
	public function test__send_function_failed_requests( $mock_response ) {
		$this->mock_http_response( $mock_response );

		$alerts = Testable_Alerts::instance();
		$body   = [ 'somekey' => 'someproperty' ];
		$result = $alerts->send( $body );

		$this->assertWPError( $result );
		$this->assertEquals( 'The request returned an invalid response: ' . $mock_response['response']['message'], $result->get_error_message() );
	}
}
