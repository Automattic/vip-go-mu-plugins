<?php

require_once __DIR__ . '/../../shared-plugins/two-factor/two-factor.php';
require_once __DIR__ . '/../../wpcom-vip-two-factor/sms-provider.php';
require_once __DIR__ . '/../../lib/wpcom-error-handler/wpcom-error-handler.php';

class Test_Two_Factor_SMS_Provider extends WP_UnitTestCase {

	private array $http_requests;
	private mixed $original_error_handler;
	private array $http_response_mocks;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// Set up required constants for testing
		define( 'TWILIO_SID', 'test_twilio_sid_12345' );
		define( 'TWILIO_SECRET', 'test_twilio_secret_67890' );
		define( 'VIP_TWILIO_VERIFY_SERVICE_SID', 'VAf7cfbffb441b4ac785b76646020688c0' );
		define( 'VIP_TWILIO_MESSAGING_SERVICE_SID', 'MG0d1f6e8595804dd69b9b760132769314' );
		define( 'VIP_GO_APP_ID', 12345 );
	}

	public function setUp(): void {
		parent::setUp();

		// Set up HTTP request mocking
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		// Set up error handler to the same used in production
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		$this->original_error_handler = set_error_handler( 'wpcom_error_handler' );

		$this->http_requests       = [];
		$this->http_response_mocks = [];
	}

	public function tearDown(): void {
		if ( $this->original_error_handler ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			set_error_handler( $this->original_error_handler );
		}

		// Remove HTTP request filter
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Clear global $_REQUEST
		$_REQUEST = [];

		parent::tearDown();
	}

	public function test_strategy_selection_phone_formats(): void {
		$test_cases = [
			// Non-Qatar numbers (should use SMS API)
			'+1234567890'   => Two_Factor_Twilio_SMS_API::class,
			'+44123456789'  => Two_Factor_Twilio_SMS_API::class,
			'+861234567890' => Two_Factor_Twilio_SMS_API::class,
			'+33123456789'  => Two_Factor_Twilio_SMS_API::class,
			'+97499990000'  => Two_Factor_Twilio_SMS_API::class,
			'1234567890'    => Two_Factor_Twilio_SMS_API::class,
			'44123456789'   => Two_Factor_Twilio_SMS_API::class,
			'861234567890'  => Two_Factor_Twilio_SMS_API::class,
			'33123456789'   => Two_Factor_Twilio_SMS_API::class,
			'97499990000'   => Two_Factor_Twilio_SMS_API::class,

			// Qatar numbers (should use Verify API when available)
			'+97433334444'  => Two_Factor_Twilio_Verify_API::class,
			'+97444445555'  => Two_Factor_Twilio_Verify_API::class,
			'+97455556666'  => Two_Factor_Twilio_Verify_API::class,
			'+97466667777'  => Two_Factor_Twilio_Verify_API::class,
			'+97477778888'  => Two_Factor_Twilio_Verify_API::class,
			'97433334444'   => Two_Factor_Twilio_Verify_API::class,
			'97444445555'   => Two_Factor_Twilio_Verify_API::class,
			'97455556666'   => Two_Factor_Twilio_Verify_API::class,
			'97466667777'   => Two_Factor_Twilio_Verify_API::class,
			'97477778888'   => Two_Factor_Twilio_Verify_API::class,
		];

		foreach ( $test_cases as $phone => $expected_class ) {
			$user = $this->setup_user_with_phone( $phone );

			$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

			$this->assertInstanceOf( $expected_class, $strategy, "Phone number $phone should use $expected_class" );
			$this->assertInstanceOf( Two_Factor_Twilio_SMS::class, $strategy, 'Strategy should implement the interface' );
		}
	}

	public function test_http_timeout_handling(): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		$this->add_http_response_mock( new WP_Error( 'http_request_failed', 'Operation timed out after 30000 milliseconds' ) );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'verification_failed', $result->get_error_code() );

		// Verify that the token was cleaned up (since validation failed)
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, true ), 'Token should not remain for retry' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_sms_api_generate_and_send_token_success(): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		$this->add_http_response_mock( $this->create_successful_sms_response() );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		// Assert no error was returned (method returns null on success)
		$this->assertNull( $result );

		// Verify that HTTP request was made to Twilio REST API (VIP SMS service) with correct method and URL
		$request_body = $this->assertHttpRequestMadeWithMethodAndUrl( 'POST', 'https://api.twilio.com/2010-04-01/Accounts/ACe16d3eaebadd491f285297e03b4d3234/Messages.json' );
		$this->assertEquals( [ 'To', 'Body', 'MessagingServiceSid' ], array_keys( $request_body ) );
		$this->assertEquals( 'MG0d1f6e8595804dd69b9b760132769314', $request_body['MessagingServiceSid'] );
		$this->assertEquals( '+1234567890', $request_body['To'] );
		$this->assertMatchesRegularExpression( '/\d{8} is your Test Blog verification code\.\n\n@example\.org #\d{8}/', $request_body['Body'] );

		// Verify that the token was stored in user meta (hashed)
		$stored_token = get_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, true );
		$this->assertNotEmpty( $stored_token );

		// The stored token should be a hash, not the plain text
		$this->assertEquals( 32, strlen( $stored_token ) );
		$this->assertTrue( $strategy->has_pending_metadata() );
	}

	public function test_twilio_sms_api_generate_and_send_token_failure_malformed_phone(): void {
		$user = $this->setup_user_with_phone( 'not-a-phone-number' );
		$this->add_http_response_mock( $this->create_failed_twilio_sms_response( 400, 21211, 'Invalid phone number format' ) );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'verification_failed', $result->get_error_code() );

		// Verify API doesn't store on failure
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, true ) );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_sms_api_validate_authentication_success(): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		// Simulate a token being stored (as would happen after generate_and_send_token)
		update_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, wp_hash( '87654321' ) );
		// Set up $_REQUEST with the same valid code
		$_REQUEST['two-factor-sms-code'] = '87654321';

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertTrue( $strategy->has_pending_metadata(), 'Should have pending metadata initially (token stored)' );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication was successful
		$this->assertTrue( $result );

		// Verify that NO HTTP request was made to Twilio Verify API (since SMS API is used)
		$requests = $this->get_all_http_requests();
		foreach ( $requests as $request ) {
			$this->assertStringNotContainsString( 'verify.twilio.com', $request['url'] );
		}

		// Verify that the token was cleaned up after successful validation
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, true ), 'Token should be cleaned up after successful validation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_sms_api_validate_authentication_failure_invalid_code(): void {
		$user = $this->setup_user_with_phone( '+1234567890' );

		// Simulate a token being stored with a different code
		update_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, wp_hash( '22334455' ) );

		// Set up $_REQUEST with incorrect code
		$_REQUEST['two-factor-sms-code'] = '87654321'; // Different from stored code

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		// Verify has pending metadata initially (token is stored)
		$this->assertTrue( $strategy->has_pending_metadata(), 'Should have pending metadata initially (token stored)' );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication failed
		$this->assertFalse( $result );

		// Verify that the token was NOT cleaned up (since validation failed)
		$this->assertNotEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, true ), 'Token should remain for retry' );
		$this->assertTrue( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_generate_and_send_token_success(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );
		$this->add_http_response_mock( $this->create_successful_verification_response() );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		// Assert no error was returned (method returns null on success)
		$this->assertNull( $result );

		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/Verifications', [
			'To'      => '+97476543210',
			'Channel' => 'sms',
			'Tags'    => wp_json_encode( [
				'blog_id'        => 1,
				'domain'         => 'example.org', // LOCAL_WP_TESTS_DOMAIN
				'environment_id' => 12345,
				'user_id'        => $user->ID,
			] ),
		] );

		// Verify that verification SID was stored in user meta
		$this->assertNotEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should be stored in user meta' );
		$this->assertTrue( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_generate_and_send_token_failure_api_error(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );
		$this->add_http_response_mock( $this->create_failed_twilio_sms_response( 400, 21211, 'Invalid phone number' ) );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'verification_failed', $result->get_error_code() );
		$this->assertEquals( 'Failed to send verification code.', $result->get_error_message() );

		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/Verifications', [
			'To'      => '+97476543210',
			'Channel' => 'sms',
			'Tags'    => wp_json_encode( [
				'blog_id'        => 1,
				'domain'         => 'example.org', // LOCAL_WP_TESTS_DOMAIN
				'environment_id' => 12345,
				'user_id'        => $user->ID,
			] ),
		] );

		// Verify API doesn't store on failure
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should not be stored in user meta after failed operation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_generate_and_send_token_failure_network_error(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );
		$this->add_http_response_mock( $this->create_network_error_response() );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'verification_failed', $result->get_error_code() );
		$this->assertEquals( 'Failed to send verification code.', $result->get_error_message() );

		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/Verifications', [
			'To'      => '+97476543210',
			'Channel' => 'sms',
			'Tags'    => wp_json_encode( [
				'blog_id'        => 1,
				'domain'         => 'example.org', // LOCAL_WP_TESTS_DOMAIN
				'environment_id' => 12345,
				'user_id'        => $user->ID,
			] ),
		] );

		// Verify API doesn't store on failure
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should not be stored in user meta after failed operation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_generate_and_send_token_failure_invalid_phone(): void {
		$user = $this->setup_user_with_phone( 'invalid-phone' );
		$this->add_http_response_mock( $this->create_failed_twilio_sms_response( 400, 21211, 'Invalid phone number format' ) );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		$this->assertInstanceOf( WP_Error::class, $result );

		// Verify API doesn't store on failure
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should not be stored in user meta after failed operation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_generate_and_send_token_failure_malformed_response(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		// Set up malformed response (missing SID)
		$this->add_http_response_mock( [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode( [
				'status'  => 'pending',
				'to'      => '+1234567890',
				'channel' => 'sms',
				// Missing 'sid' field
			] ),
		] );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		$this->assertInstanceOf( WP_Error::class, $result );

		// Verify API doesn't store on failure
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should not be stored in user meta after failed operation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_validate_authentication_success(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );

		// Simulate a verification SID being stored (as would happen after generate_and_send_token)
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, 'VEe51adf654c854930939ea57199faa362' );

		// Set up $_REQUEST with valid code
		$_REQUEST['two-factor-sms-code'] = '123456';

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		// Verify has pending metadata initially (verification SID is stored)
		$this->assertTrue( $strategy->has_pending_metadata(), 'Should have pending metadata initially (verification SID stored)' );

		$this->add_http_response_mock( $this->create_successful_verification_check_response() );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication was successful
		$this->assertTrue( $result );

		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/VerificationCheck', [
			'VerificationSid' => 'VEe51adf654c854930939ea57199faa362',
			'Code'            => '123456',
		] );

		// Verify that verification SID was cleaned up after successful validation
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should be cleaned up after successful validation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_validate_authentication_failure_http_error_with_body(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );

		// Simulate a verification SID being stored
		$verification_sid = 'VEe51adf654c854930939ea57199faa362';
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, $verification_sid );

		// Set up $_REQUEST with valid code
		$_REQUEST['two-factor-sms-code'] = '123456';

		// Set up HTTP error response (status >= 300) WITH valid Twilio error body
		$this->add_http_response_mock( [
			'response' => [ 'code' => 400 ],
			'body'     => wp_json_encode( [
				'code'      => 21211,
				'message'   => 'Invalid verification code',
				'more_info' => 'https://www.twilio.com/docs/errors/21211',
				'status'    => 400,
			] ),
		] );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication failed
		$this->assertFalse( $result );

		// Verify that verification SID was cleaned up after failure
		$remaining_sid = get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true );
		$this->assertEmpty( $remaining_sid );

		// Verify that HTTP request was made
		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/VerificationCheck', [
			'VerificationSid' => $verification_sid,
			'Code'            => '123456',
		] );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		// Verify that verification SID was cleaned up after failure
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should be cleaned up after failed operation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_validate_authentication_failure_http_error_without_body(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );

		// Simulate a verification SID being stored
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, 'VEe51adf654c854930939ea57199faa362' );

		// Set up $_REQUEST with valid code
		$_REQUEST['two-factor-sms-code'] = '123456';

		// Set up HTTP error response (status >= 300) WITHOUT valid error body (missing 'message' property)
		$this->add_http_response_mock( [
			'response' => [ 'code' => 500 ],
			'body'     => wp_json_encode( [
				'code'   => 20001,
				'status' => 500,
				// Missing 'message' property - this should trigger fallback error format
			] ),
		] );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication failed
		$this->assertFalse( $result );

		// Verify that verification SID was cleaned up after failure
		$remaining_sid = get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true );
		$this->assertEmpty( $remaining_sid );

		// Verify that HTTP request was made
		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/VerificationCheck', [
			'VerificationSid' => 'VEe51adf654c854930939ea57199faa362',
			'Code'            => '123456',
		] );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		// Verify that verification SID was cleaned up after failure
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should be cleaned up after failed operation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_validate_authentication_failure_invalid_code(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );

		// Simulate a verification SID being stored
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, 'VEe51adf654c854930939ea57199faa362' );

		// Set up $_REQUEST with invalid code
		$_REQUEST['two-factor-sms-code'] = '000000';

		// Set up failed verification check response (code not approved)
		$this->add_http_response_mock( [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode( [
				'sid'    => 'VEe51adf654c854930939ea57199faa362',
				'status' => 'pending', // Not approved
				'to'     => '+1234567890',
			] ),
		] );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication failed
		$this->assertFalse( $result );

		// Verify that an HTTP request was made to Twilio Verify API for verification check
		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/VerificationCheck', [
			'VerificationSid' => 'VEe51adf654c854930939ea57199faa362',
			'Code'            => '000000',
		] );

		// Verify that verification SID remains in user meta after failed verification check
		$this->assertNotEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should remain in user meta after failed verification check' );
		$this->assertTrue( Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID )->has_pending_metadata() );
	}

	public function test_twilio_verify_validate_authentication_failure_missing_verification_sid(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );

		// Set up $_REQUEST with valid code but no verification SID stored
		$_REQUEST['two-factor-sms-code'] = '123456';

		// Call the method under test
		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication failed
		$this->assertFalse( $result );

		// Verify that no HTTP request was made (since no SID to verify)
		$this->assertEmpty( $this->get_all_http_requests() );


		// Verify that verification SID was not stored in user meta
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should not be stored in user meta' );
		$this->assertFalse( Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID )->has_pending_metadata() );
	}

	public function test_twilio_verify_validate_authentication_failure_network_error(): void {
		$user = $this->setup_user_with_phone( '+97476543210' );

		// Simulate a verification SID being stored
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, 'VEe51adf654c854930939ea57199faa362' );

		// Set up $_REQUEST with valid code
		$_REQUEST['two-factor-sms-code'] = '123456';

		$this->add_http_response_mock( $this->create_network_error_response() );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication failed
		$this->assertFalse( $result );

		// Verify that verification SID was cleaned up after failure
		$remaining_sid = get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true );
		$this->assertEmpty( $remaining_sid );

		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should be cleaned up after failed operation' );
		$this->assertFalse( Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID )->has_pending_metadata() );
	}

	// phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement
	public function mock_http_request( false|array|WP_Error $preempt, array $args, string $url ): array|WP_Error {
		$this->http_requests[] = [
			'url'  => $url,
			'args' => $args,
		];

		if ( ! empty( $this->http_response_mocks ) ) {
			return array_shift( $this->http_response_mocks );
		}

		$this->fail( 'Unexpected HTTP request: ' . $url );
	}

	private function create_successful_verification_response(): array {
		return [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode( [
				'sid'     => 'VEe51adf654c854930939ea57199faa362',
				'status'  => 'pending',
				'to'      => '+1234567890',
				'channel' => 'sms',
			] ),
		];
	}

	private function create_successful_verification_check_response(): array {
		return [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode( [
				'sid'    => 'VEe51adf654c854930939ea57199faa362',
				'status' => 'approved',
				'to'     => '+1234567890',
			] ),
		];
	}

	private function create_successful_sms_response(): array {
		return [
			'response' => [ 'code' => 201 ], // Twilio REST API returns 201 for successful message creation
			'body'     => wp_json_encode( [
				'sid'           => 'SM' . wp_generate_password( 32, false, false ),
				'status'        => 'queued',
				'to'            => '+1234567890',
				'from'          => '+14159695849',
				'body'          => 'Your verification code is: 123456',
				'date_created'  => gmdate( 'c' ),
				'price'         => null,
				'error_code'    => null,
				'error_message' => null,
			] ),
		];
	}

	private function create_failed_twilio_sms_response( int $code = 400, int $error_code = 21211, string $message = 'Invalid phone number' ): array {
		return [
			'response' => [ 'code' => $code ],
			'body'     => wp_json_encode( [
				'code'      => $error_code,
				'message'   => $message,
				'more_info' => 'https://www.twilio.com/docs/errors/' . $error_code,
				'status'    => $code,
			] ),
		];
	}

	private function create_network_error_response(): WP_Error {
		return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 30000 milliseconds' );
	}

	private function add_http_response_mock( array|WP_Error $response ): void {
		$this->http_response_mocks[] = $response;
	}

	private function setup_user_with_phone( string $phone_number ): WP_User {
		$user = self::factory()->user->create_and_get();
		update_user_meta( $user->ID, Two_Factor_SMS::PHONE_META_KEY, $phone_number );
		update_user_meta( $user->ID, Two_Factor_SMS::SMS_CONFIGURED_META_KEY, '1' );
		return $user;
	}

	private function get_all_http_requests(): array {
		return $this->http_requests;
	}

	private function assertHttpRequestMadeWithMethodAndUrl( string $method, string $url, ?array $expected_body = null ): array {
		$requests = $this->get_all_http_requests();

		foreach ( $requests as $request ) {
			$body = $request['args']['body'] ?? null;
			if ( $request['args']['method'] === $method && $request['url'] === $url ) {
				if ( null === $expected_body ) {
					return $body;
				}
				if ( wp_json_encode( $expected_body ) === wp_json_encode( $request['args']['body'] ) ) {
					return $body;
				}
			}
		}

		$body_message = null !== $expected_body ? ' with body ' . wp_json_encode( $expected_body ) : '';
		$this->fail( "Expected HTTP request '$method $url$body_message' was not made" );
	}
}
