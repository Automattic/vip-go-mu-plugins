<?php

use function Automattic\Test\Utils\http_response;

require_once __DIR__ . '/include-wpcom-vip-get-user-profile.php';

class WPCOM_VIP_Get_User_Profile_Test extends WP_UnitTestCase {

	private function mock_profile_response( string $body ): void {
		add_filter( 'pre_http_request', fn() => http_response( 200, $body ) );
	}

	public function test__wpcom_vip_get_user_profile__request_identity_and_destination() {
		$requests = [];
		$entry    = [ 'displayName' => 'Mixed Case User' ];
		// Substitute only HTTP transport: capture the real helper's request.
		add_filter( 'pre_http_request', function ( $preempt, $args, $url ) use ( &$requests, $entry ) {
			$requests[] = [
				'url'    => $url,
				'method' => $args['method'],
			];
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			return http_response( 200, serialize( [ 'entry' => [ $entry ] ] ) );
		}, 10, 3 );

		$result = wpcom_vip_get_user_profile( 'Mixed.Case@Example.COM' );

		self::assertSame( $entry, $result );
		self::assertSame( [
			[
				'url'    => 'https://en.gravatar.com/7a126a993c9ece5663288f1e48a453a6b4b12656af38d84103cb6beb8a5862b9.php',
				'method' => 'GET',
			],
		], $requests );
		self::assertStringNotContainsString( '@', $requests[0]['url'] );
		self::assertStringNotContainsString( 'Mixed.Case', $requests[0]['url'] );
	}

	/**
	 * Test that __wakeup() is not triggered when the remote response contains
	 * a serialized object (insecure deserialization protection via allowed_classes => false).
	 */
	public function test__wpcom_vip_get_user_profile__serialized_object_does_not_call_wakeup() {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->mock_profile_response( serialize( new VIP_Test_Deserialization_Class() ) );

		$result = wpcom_vip_get_user_profile( 'test@example.com' );

		self::assertFalse( VIP_Test_Deserialization_Class::$wakeup_called, '__wakeup() must not be called during deserialization' );
		self::assertFalse( $result, 'Profile should be false when deserialized data is not a valid profile array' );
	}

	/**
	 * Test that __wakeup() is not triggered when the remote response contains a
	 * serialized object nested inside the entry array (insecure deserialization protection).
	 */
	public function test__wpcom_vip_get_user_profile__serialized_object_in_entry_does_not_call_wakeup() {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->mock_profile_response( serialize( [ 'entry' => [ new VIP_Test_Deserialization_Class() ] ] ) );

		wpcom_vip_get_user_profile( 'nested@example.com' );

		self::assertFalse( VIP_Test_Deserialization_Class::$wakeup_called, '__wakeup() must not be called during deserialization' );
	}

	/**
	 * Test that a valid serialized profile array is returned correctly.
	 */
	public function test__wpcom_vip_get_user_profile__valid_profile_is_returned() {
		$entry = [
			'id'          => '12345',
			'displayName' => 'Test User',
		];
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->mock_profile_response( serialize( [ 'entry' => [ $entry ] ] ) );

		$result = wpcom_vip_get_user_profile( 'valid@example.com' );

		self::assertEquals( $entry, $result );
	}
}
