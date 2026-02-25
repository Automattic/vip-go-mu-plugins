<?php

require_once __DIR__ . '/include-wpcom-vip-get-user-profile.php';

class WPCOM_VIP_Get_User_Profile_Test extends WP_UnitTestCase {

	private ?Closure $http_filter = null;

	public function setUp(): void {
		parent::setUp();
		VIP_Test_Deserialization_Class::$wakeup_called = false;
		wp_cache_flush();
	}

	public function tearDown(): void {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter );
			$this->http_filter = null;
		}
		parent::tearDown();
	}

	private function mock_profile_response( string $body ): void {
		$this->http_filter = function () use ( $body ) {
			return [
				'headers'  => [],
				'body'     => $body,
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};
		add_filter( 'pre_http_request', $this->http_filter );
	}

	/**
	 * Test that __wakeup() is not triggered when the remote response contains
	 * a serialized object (insecure deserialization protection via allowed_classes => false).
	 */
	public function test__wpcom_vip_get_user_profile__serialized_object_does_not_call_wakeup() {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->mock_profile_response( serialize( new VIP_Test_Deserialization_Class() ) );

		$result = wpcom_vip_get_user_profile( 'test@example.com' );

		$this->assertFalse( VIP_Test_Deserialization_Class::$wakeup_called, '__wakeup() must not be called during deserialization' );
		$this->assertFalse( $result, 'Profile should be false when deserialized data is not a valid profile array' );
	}

	/**
	 * Test that __wakeup() is not triggered when the remote response contains a
	 * serialized object nested inside the entry array (insecure deserialization protection).
	 */
	public function test__wpcom_vip_get_user_profile__serialized_object_in_entry_does_not_call_wakeup() {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->mock_profile_response( serialize( [ 'entry' => [ new VIP_Test_Deserialization_Class() ] ] ) );

		wpcom_vip_get_user_profile( 'nested@example.com' );

		$this->assertFalse( VIP_Test_Deserialization_Class::$wakeup_called, '__wakeup() must not be called during deserialization' );
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

		$this->assertEquals( $entry, $result );
	}
}
