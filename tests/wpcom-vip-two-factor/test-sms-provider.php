<?php

// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Tests log in through the logged-in cookie.

use function Automattic\Test\Utils\http_response;

require_once __DIR__ . '/../../shared-plugins/two-factor/two-factor.php';
require_once __DIR__ . '/../../wpcom-vip-two-factor/sms-provider.php';
require_once __DIR__ . '/../../lib/wpcom-error-handler/wpcom-error-handler.php';

class Test_Two_Factor_SMS_Provider extends WP_UnitTestCase {

	private const SMS_API_URL            = 'https://api.twilio.com/2010-04-01/Accounts/ACe16d3eaebadd491f285297e03b4d3234/Messages.json';
	private const VERIFICATIONS_URL      = 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/Verifications';
	private const VERIFICATION_CHECK_URL = 'https://verify.twilio.com/v2/Services/VAf7cfbffb441b4ac785b76646020688c0/VerificationCheck';
	private const VERIFICATION_SID       = 'VEe51adf654c854930939ea57199faa362';
	private const QATAR_PHONE            = '+97476543210';

	private static WP_User $user;

	private array $http_requests;
	private mixed $original_error_handler;
	private string $original_display_errors;
	private string $original_error_log;
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

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Shared by every test; the phone and verification meta each test sets are rolled back with it.
		self::$user = $factory->user->create_and_get();
	}

	public function setUp(): void {
		parent::setUp();

		// Set up HTTP request mocking
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );

		$this->original_display_errors = (string) ini_get( 'display_errors' );
		$this->original_error_log      = (string) ini_get( 'error_log' );

		// Set up error handler to the same used in production
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		$this->original_error_handler = set_error_handler( 'wpcom_error_handler' );
		// phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed
		ini_set( 'display_errors', '0' ); // wpcom_error_handler logs to stderr
		// phpcs:ignore WordPress.PHP.IniSet.Risky
		ini_set( 'error_log', '/dev/null' ); // wpcom_error_handler logs to error_log

		$this->http_requests       = [];
		$this->http_response_mocks = [];
	}

	public function tearDown(): void {
		// Pop exactly the handler installed in setUp, including when its predecessor was null.
		restore_error_handler();
		// phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed -- Restore the saved diagnostic configuration.
		ini_set( 'display_errors', $this->original_display_errors );
		// phpcs:ignore WordPress.PHP.IniSet.Risky -- Restore the saved diagnostic configuration.
		ini_set( 'error_log', $this->original_error_log );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Observe and immediately restore the current handler.
		$restored_handler = set_error_handler( static fn() => false );
		restore_error_handler();

		// Remove HTTP request filter
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		// Clear request globals
		$_REQUEST = [];
		$_POST    = [];
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );

		parent::tearDown();

		$this->assertSame( $this->original_display_errors, ini_get( 'display_errors' ) );
		$this->assertSame( $this->original_error_log, ini_get( 'error_log' ) );
		$this->assertSame( $this->original_error_handler, $restored_handler );
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
			$user = $this->setup_user_with_phone( (string) $phone );

			$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

			$this->assertInstanceOf( $expected_class, $strategy, "Phone number $phone should use $expected_class" );
			$this->assertInstanceOf( Two_Factor_Twilio_SMS::class, $strategy, 'Strategy should implement the interface' );
		}
	}

	public function test_twilio_sms_api_generate_and_send_token_success(): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		$this->add_http_response_mock( self::SMS_API_URL, http_response( 201, wp_json_encode( [ // Twilio REST API returns 201 for successful message creation
			'sid'           => 'SM' . wp_generate_password( 32, false, false ),
			'status'        => 'queued',
			'to'            => '+1234567890',
			'from'          => '+14159695849',
			'body'          => 'Your verification code is: 123456',
			'date_created'  => gmdate( 'c' ),
			'price'         => null,
			'error_code'    => null,
			'error_message' => null,
		] ) ) );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		// Assert no error was returned (method returns null on success)
		$this->assertNull( $result );

		// Verify that HTTP request was made to Twilio REST API (VIP SMS service) with correct method and URL
		$request_body = $this->assertHttpRequestMadeWithMethodAndUrl( 'POST', self::SMS_API_URL );
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
		preg_match( '/^(\d{8}) is your/', $request_body['Body'], $matches );
		$this->assertSame( wp_hash( '+1234567890:' . $matches[1] ), $stored_token );

		// Validating the sent code makes no further HTTP request (mock_http_request fails on unexpected ones).
		$_REQUEST['two-factor-sms-code'] = $matches[1];
		$this->assertTrue( Two_Factor_SMS::get_instance()->validate_authentication( $user ) );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function data_sms_api_send_failures(): array {
		return [
			'HTTP timeout'    => [ '+1234567890', new WP_Error( 'http_request_failed', 'Operation timed out after 30000 milliseconds' ) ],
			'malformed phone' => [ 'not-a-phone-number', self::twilio_error_response( 400, 21211, 'Invalid phone number format' ) ],
		];
	}

	/**
	 * @dataProvider data_sms_api_send_failures
	 */
	public function test_twilio_sms_api_generate_and_send_token_failure( string $phone, array|WP_Error $response ): void {
		$user = $this->setup_user_with_phone( $phone );
		$this->add_http_response_mock( self::SMS_API_URL, $response );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertInstanceOf( Two_Factor_Twilio_SMS_API::class, $strategy );
		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'verification_failed', $result->get_error_code() );

		// The token stored before sending is cleaned up, so nothing remains for a retry.
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, true ) );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function data_authentication_page_send_result(): array {
		return [
			'code sent'     => [ http_response( 201, wp_json_encode( [ 'status' => 'queued' ] ) ), 'A verification code has been sent' ],
			'code not sent' => [ new WP_Error( 'http_request_failed', 'Operation timed out after 30000 milliseconds' ), 'A verification code could not be sent' ],
		];
	}

	/**
	 * The login prompt says whether the code was sent (the test bootstrap's GET request sends one).
	 *
	 * @dataProvider data_authentication_page_send_result
	 */
	public function test_authentication_page_reports_send_result( array|WP_Error $response, string $expected_message ): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		$this->add_http_response_mock( self::SMS_API_URL, $response );

		ob_start();
		Two_Factor_SMS::get_instance()->authentication_page( $user );

		$this->assertStringContainsString( $expected_message, ob_get_clean() );
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

	/**
	 * A code only verifies the number it was sent to, so a send that finishes after the number changed can't verify the new one.
	 */
	public function test_twilio_sms_api_code_does_not_verify_changed_number(): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		$this->add_http_response_mock( self::SMS_API_URL, http_response( 201, wp_json_encode( [ 'status' => 'queued' ] ) ) );
		Two_Factor_SMS::get_instance()->generate_and_send_token( $user );
		preg_match( '/^(\d{8}) is your/', $this->assertHttpRequestMadeWithMethodAndUrl( 'POST', self::SMS_API_URL )['Body'], $matches );

		update_user_meta( $user->ID, Two_Factor_SMS::PHONE_META_KEY, '+1234567899' );
		$_REQUEST['two-factor-sms-code'] = $matches[1];

		$this->assertFalse( Two_Factor_SMS::get_instance()->validate_authentication( $user ) );
	}

	public function test_validate_authentication_rejects_non_string_code(): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		update_user_meta( $user->ID, Two_Factor_Twilio_SMS_API::TOKEN_META_KEY, wp_hash( '22334455' ) );
		$_REQUEST['two-factor-sms-code'] = [ '22334455' ];

		$this->assertFalse( Two_Factor_SMS::get_instance()->validate_authentication( $user ) );
	}

	public function test_twilio_verify_generate_and_send_token_success(): void {
		$user = $this->setup_user_with_phone( self::QATAR_PHONE );
		$this->add_http_response_mock( self::VERIFICATIONS_URL, http_response( 200, wp_json_encode( [
			'sid'     => self::VERIFICATION_SID,
			'status'  => 'pending',
			'to'      => '+1234567890',
			'channel' => 'sms',
		] ) ) );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		// Assert no error was returned (method returns null on success)
		$this->assertNull( $result );

		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', self::VERIFICATIONS_URL, $this->expected_verify_body( self::QATAR_PHONE ) );

		// Verify that verification SID was stored in user meta
		$this->assertSame( self::VERIFICATION_SID, get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ) );
		$this->assertTrue( $strategy->has_pending_metadata() );

		// The approved verification check validates the code and cleans up the SID.
		$_REQUEST['two-factor-sms-code'] = '123456';
		$this->add_http_response_mock( self::VERIFICATION_CHECK_URL, http_response( 200, wp_json_encode( [
			'sid'    => self::VERIFICATION_SID,
			'status' => 'approved',
			'to'     => self::QATAR_PHONE,
		] ) ) );
		$this->assertTrue( Two_Factor_SMS::get_instance()->validate_authentication( $user ) );
		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', self::VERIFICATION_CHECK_URL, [
			'VerificationSid' => self::VERIFICATION_SID,
			'Code'            => '123456',
		] );
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ) );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function data_verify_send_failures(): array {
		return [
			'API error'                   => [ self::QATAR_PHONE, self::twilio_error_response( 400, 21211, 'Invalid phone number' ) ],
			'network error'               => [ self::QATAR_PHONE, self::network_error() ],
			// Only well-formed Qatar numbers reach the Verify strategy, so Twilio Verify is the one rejecting the number.
			'invalid phone'               => [ '+97470000000', self::twilio_error_response( 400, 60200, 'Invalid parameter `To`: +97470000000' ) ],
			'malformed response (no SID)' => [
				self::QATAR_PHONE,
				http_response( 200, wp_json_encode( [
					'status'  => 'pending',
					'to'      => '+1234567890',
					'channel' => 'sms',
				] ) ),
			],
		];
	}

	/**
	 * @dataProvider data_verify_send_failures
	 */
	public function test_twilio_verify_generate_and_send_token_failure( string $phone, array|WP_Error $response ): void {
		$user = $this->setup_user_with_phone( $phone );
		$this->add_http_response_mock( self::VERIFICATIONS_URL, $response );

		$strategy = Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID );

		$this->assertInstanceOf( Two_Factor_Twilio_Verify_API::class, $strategy );
		$this->assertFalse( $strategy->has_pending_metadata(), 'Should have no pending metadata initially' );

		$result = Two_Factor_SMS::get_instance()->generate_and_send_token( $user );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'verification_failed', $result->get_error_code() );
		$this->assertEquals( 'Failed to send verification code.', $result->get_error_message() );

		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', self::VERIFICATIONS_URL, $this->expected_verify_body( $phone ) );

		// Verify API doesn't store on failure
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should not be stored in user meta after failed operation' );
		$this->assertFalse( $strategy->has_pending_metadata() );
	}

	public function test_twilio_verify_send_failure_masks_number_without_float_deprecation(): void {
		$user = $this->setup_user_with_phone( self::QATAR_PHONE );
		$this->add_http_response_mock( self::VERIFICATIONS_URL, self::twilio_error_response( 400, 21211, 'Invalid phone number' ) );

		// wpcom_error_handler swallows deprecations, so record what it handles. Masking an 11-character number truncates 11 / 1.5 and must not raise a float-to-int deprecation.
		$errors = [];
		add_action( 'php_error_handler', function ( string $type, string $message ) use ( &$errors ): void {
			$errors[] = "$type: $message";
		}, 10, 2 );

		$result = ( new Two_Factor_Twilio_Verify_API( $user->ID, '+1234567890' ) )->send_code( '123456' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( [ 'Warning: Failed to send SMS to +123456xxx: Twilio Verify API responded with error (21211): Invalid phone number #vip-go-sms-error' ], $errors );
	}

	public function data_verify_check_failures(): array {
		return [
			'HTTP error with body'    => [
				http_response( 400, wp_json_encode( [
					'code'      => 21211,
					'message'   => 'Invalid verification code',
					'more_info' => 'https://www.twilio.com/docs/errors/21211',
					'status'    => 400,
				] ) ),
			],
			// Missing 'message' property - this should trigger fallback error format
			'HTTP error without body' => [
				http_response( 500, wp_json_encode( [
					'code'   => 20001,
					'status' => 500,
				] ) ),
			],
			'network error'           => [ self::network_error() ],
		];
	}

	/**
	 * A failed verification check request invalidates the pending verification.
	 *
	 * @dataProvider data_verify_check_failures
	 */
	public function test_twilio_verify_validate_authentication_failure( array|WP_Error $response ): void {
		$user = $this->setup_user_with_phone( self::QATAR_PHONE );
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, self::VERIFICATION_SID );
		$_REQUEST['two-factor-sms-code'] = '123456';
		$this->add_http_response_mock( self::VERIFICATION_CHECK_URL, $response );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		$this->assertFalse( $result );
		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', self::VERIFICATION_CHECK_URL, [
			'VerificationSid' => self::VERIFICATION_SID,
			'Code'            => '123456',
		] );
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should be cleaned up after failed operation' );
		$this->assertFalse( Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID )->has_pending_metadata() );
	}

	public function test_twilio_verify_validate_authentication_failure_invalid_code(): void {
		$user = $this->setup_user_with_phone( self::QATAR_PHONE );

		// Simulate a verification SID being stored
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, self::VERIFICATION_SID );

		// Set up $_REQUEST with invalid code
		$_REQUEST['two-factor-sms-code'] = '000000';

		// Set up failed verification check response (code not approved)
		$this->add_http_response_mock( self::VERIFICATION_CHECK_URL, http_response( 200, wp_json_encode( [
			'sid'    => self::VERIFICATION_SID,
			'status' => 'pending', // Not approved
			'to'     => '+1234567890',
		] ) ) );

		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication failed
		$this->assertFalse( $result );

		// Verify that an HTTP request was made to Twilio Verify API for verification check
		$this->assertHttpRequestMadeWithMethodAndUrl( 'POST', self::VERIFICATION_CHECK_URL, [
			'VerificationSid' => self::VERIFICATION_SID,
			'Code'            => '000000',
		] );

		// Verify that verification SID remains in user meta after failed verification check
		$this->assertNotEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should remain in user meta after failed verification check' );
		$this->assertTrue( Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID )->has_pending_metadata() );
	}

	/**
	 * An approved verification for another number, stored by a send that finished after the number changed, is rejected.
	 */
	public function test_twilio_verify_approved_check_for_another_number_fails(): void {
		$user = $this->setup_user_with_phone( '+97476543211' );
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, self::VERIFICATION_SID );
		$_REQUEST['two-factor-sms-code'] = '123456';
		$this->add_http_response_mock( self::VERIFICATION_CHECK_URL, http_response( 200, wp_json_encode( [
			'sid'    => self::VERIFICATION_SID,
			'status' => 'approved',
			'to'     => self::QATAR_PHONE,
		] ) ) );

		$this->assertFalse( Two_Factor_SMS::get_instance()->validate_authentication( $user ) );
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ) );
	}

	public function test_twilio_verify_validate_authentication_failure_missing_verification_sid(): void {
		$user = $this->setup_user_with_phone( self::QATAR_PHONE );

		// Set up $_REQUEST with valid code but no verification SID stored
		$_REQUEST['two-factor-sms-code'] = '123456';

		// Call the method under test
		$result = Two_Factor_SMS::get_instance()->validate_authentication( $user );

		// Assert authentication failed
		$this->assertFalse( $result );

		// Verify that no HTTP request was made (since no SID to verify)
		$this->assertEmpty( $this->http_requests );

		// Verify that verification SID was not stored in user meta
		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ), 'Verification SID should not be stored in user meta' );
		$this->assertFalse( Two_Factor_SMS::get_instance()->get_sms_strategy( $user->ID )->has_pending_metadata() );
	}

	public function data_user_options_update_revalidation(): array {
		return [
			'session revalidated within the grace period' => [ 0, '' ],
			'session past the grace period'               => [ 21 * MINUTE_IN_SECONDS, '+1234567890' ],
		];
	}

	/**
	 * Changing the SMS settings of a user who uses two-factor (here, emailed codes) needs a recently revalidated session.
	 *
	 * @dataProvider data_user_options_update_revalidation
	 */
	public function test_user_options_update_requires_revalidated_session( int $seconds_since_two_factor, string $expected_phone ): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		update_user_meta( $user->ID, Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY, [ 'Two_Factor_Email' ] );

		// Log the user in with a session that passed two-factor $seconds_since_two_factor ago.
		$expiration                  = time() + HOUR_IN_SECONDS;
		$token                       = WP_Session_Tokens::get_instance( $user->ID )->create( $expiration );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user->ID, $expiration, 'logged_in', $token );
		wp_set_current_user( $user->ID );
		Two_Factor_Core::update_current_user_session( [ 'two-factor-login' => time() - $seconds_since_two_factor ] );

		$this->submit_sms_options( $user, [ 'vip-two-factor-phone-delete' => 'Delete' ] );

		$this->assertSame( $expected_phone, get_user_meta( $user->ID, Two_Factor_SMS::PHONE_META_KEY, true ) );
	}

	public function data_is_available_for_user(): array {
		return [
			'verified number'                      => [ '+1234567890', [], true ],
			'number verified before it was stored' => [ '1', [], true ],
			'another number verified'              => [ '+1234567899', [ 'Two_Factor_SMS' ], false ],
			'number without flag, SMS enabled'     => [ '', [ 'Two_Factor_SMS' ], true ],
			'number without flag, SMS not enabled' => [ '', [], false ],
			'unverified number, SMS enabled'       => [ '0', [ 'Two_Factor_SMS' ], false ],
		];
	}

	/**
	 * Only a verified number can be enabled; numbers saved before verification was required keep working where SMS is enabled.
	 *
	 * @dataProvider data_is_available_for_user
	 */
	public function test_is_available_for_user( string $configured, array $enabled_providers, bool $expected ): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		update_user_meta( $user->ID, Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY, $enabled_providers );
		update_user_meta( $user->ID, Two_Factor_SMS::SMS_CONFIGURED_META_KEY, $configured );

		$this->assertSame( $expected, Two_Factor_SMS::get_instance()->is_available_for_user( $user ) );
	}

	/**
	 * Enabling SMS outside the profile form, as `wp two-factor enable` does, doesn't make a number without the flag usable.
	 */
	public function test_enable_provider_for_user_does_not_make_unflagged_number_available(): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		update_user_meta( $user->ID, Two_Factor_SMS::SMS_CONFIGURED_META_KEY, '' );
		// The provider is a singleton, so an earlier test's hook reset may have removed the hooks its constructor added.
		add_filter( 'update_user_metadata', [ Two_Factor_SMS::get_instance(), 'mark_unflagged_number_unverified_on_enable' ], 10, 4 );

		$this->assertTrue( Two_Factor_Core::enable_provider_for_user( $user->ID, 'Two_Factor_SMS' ) );

		$this->assertFalse( Two_Factor_SMS::get_instance()->is_available_for_user( $user ) );
	}

	public function data_user_options_update_phone(): array {
		return [
			'same number'                => [ '', '+1234567890', '+1234567890', '' ],
			'different number'           => [ '', '+1234567899', '+1234567899', '0' ],
			'different number, verified' => [ '+1234567890', '+1234567899', '+1234567890', '+1234567890' ],
		];
	}

	/**
	 * Saving a different number marks it unverified, so a number without the flag can't be swapped for an unverified one.
	 * A verified number stays until it is deleted.
	 *
	 * @dataProvider data_user_options_update_phone
	 */
	public function test_user_options_update_changed_number( string $configured, string $phone, string $expected_phone, string $expected_configured ): void {
		$user = $this->setup_user_with_phone( '+1234567890' );
		update_user_meta( $user->ID, Two_Factor_SMS::SMS_CONFIGURED_META_KEY, $configured );
		wp_set_current_user( $user->ID );

		$this->submit_sms_options( $user, [ 'vip-two-factor-phone' => $phone ] );

		$this->assertSame( $expected_phone, get_user_meta( $user->ID, Two_Factor_SMS::PHONE_META_KEY, true ) );
		$this->assertSame( $expected_configured, get_user_meta( $user->ID, Two_Factor_SMS::SMS_CONFIGURED_META_KEY, true ) );
	}

	public function data_user_options_update_verify_code(): array {
		return [
			'number unchanged during the check' => [ false, true ],
			'number changed during the check'   => [ true, false ],
		];
	}

	/**
	 * An approved check verifies the number it was sent to, not a number saved by another request while it ran.
	 *
	 * @dataProvider data_user_options_update_verify_code
	 */
	public function test_user_options_update_verifies_checked_number( bool $change_number, bool $expected_available ): void {
		$user = $this->setup_user_with_phone( self::QATAR_PHONE );
		update_user_meta( $user->ID, Two_Factor_SMS::SMS_CONFIGURED_META_KEY, '0' );
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, self::VERIFICATION_SID );
		wp_set_current_user( $user->ID );
		$this->add_http_response_mock( self::VERIFICATION_CHECK_URL, http_response( 200, wp_json_encode( [
			'status' => 'approved',
			'to'     => self::QATAR_PHONE,
		] ) ) );
		if ( $change_number ) {
			add_filter( 'pre_http_request', function ( $preempt ) use ( $user ) {
				update_user_meta( $user->ID, Two_Factor_SMS::PHONE_META_KEY, '+1234567899' );
				return $preempt;
			}, 9 );
		}

		$this->submit_sms_options( $user, [
			'vip-two-factor-phone-verify-code' => 'Submit',
			'two-factor-sms-code'              => '123456',
		] );

		$this->assertSame( $expected_available, Two_Factor_SMS::get_instance()->is_available_for_user( $user ) );
	}

	public function data_user_options_update_number_removed(): array {
		return [
			'number deleted' => [ [ 'vip-two-factor-phone-delete' => 'Delete' ] ],
			'number changed' => [ [ 'vip-two-factor-phone' => '+1234567890' ] ],
		];
	}

	/**
	 * A code pending for the old number is cleared when the number is deleted or changed, including for Twilio Verify numbers.
	 *
	 * @dataProvider data_user_options_update_number_removed
	 */
	public function test_user_options_update_clears_pending_code_for_old_number( array $fields ): void {
		$user = $this->setup_user_with_phone( self::QATAR_PHONE );
		update_user_meta( $user->ID, Two_Factor_SMS::SMS_CONFIGURED_META_KEY, '0' );
		update_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, self::VERIFICATION_SID );
		wp_set_current_user( $user->ID );

		$this->submit_sms_options( $user, $fields );

		$this->assertEmpty( get_user_meta( $user->ID, Two_Factor_Twilio_Verify_API::VERIFICATION_SID_META_KEY, true ) );
	}

	// phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement
	public function mock_http_request( false|array|WP_Error $preempt, array $args, string $url ): array|WP_Error {
		$this->http_requests[] = [
			'url'  => $url,
			'args' => $args,
		];

		if ( ! empty( $this->http_response_mocks ) ) {
			$fixture = array_shift( $this->http_response_mocks );
			$this->assertSame( $fixture['url'], $url );
			$this->assertSame( 'POST', $args['method'] );
			$this->assertSame( 'Basic ' . base64_encode( TWILIO_SID . ':' . TWILIO_SECRET ), $args['headers']['Authorization'] ?? null );
			return $fixture['response'];
		}

		$this->fail( 'Unexpected HTTP request: ' . $url );
	}

	private static function twilio_error_response( int $code, int $error_code, string $message ): array {
		return http_response( $code, wp_json_encode( [
			'code'      => $error_code,
			'message'   => $message,
			'more_info' => 'https://www.twilio.com/docs/errors/' . $error_code,
			'status'    => $code,
		] ) );
	}

	private static function network_error(): WP_Error {
		return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 30000 milliseconds' );
	}

	/**
	 * The Verifications request body sent for the shared user.
	 */
	private function expected_verify_body( string $phone ): array {
		return [
			'To'                 => $phone,
			'Channel'            => 'sms',
			'CustomFriendlyName' => 'example.org',
			'Tags'               => wp_json_encode( [
				'blog_id'        => 1,
				'domain'         => 'example.org', // LOCAL_WP_TESTS_DOMAIN
				'environment_id' => 12345,
				'user_id'        => self::$user->ID,
			] ),
		];
	}

	/**
	 * Bind the queued provider response to its expected endpoint.
	 */
	private function add_http_response_mock( string $url, array|WP_Error $response ): void {
		$this->http_response_mocks[] = [
			'url'      => $url,
			'response' => $response,
		];
	}

	/**
	 * Submit the SMS profile options with a valid nonce, as the profile form does.
	 */
	private function submit_sms_options( WP_User $user, array $fields ): void {
		$form     = [ '_nonce_user_two_factor_sms_options' => wp_create_nonce( 'user_two_factor_sms_options' ) ] + $fields;
		$_POST    = $form;
		$_REQUEST = $form;
		Two_Factor_SMS::get_instance()->user_options_update( $user->ID );
	}

	private function setup_user_with_phone( string $phone_number ): WP_User {
		update_user_meta( self::$user->ID, Two_Factor_SMS::PHONE_META_KEY, $phone_number );
		update_user_meta( self::$user->ID, Two_Factor_SMS::SMS_CONFIGURED_META_KEY, '1' );
		return self::$user;
	}

	private function assertHttpRequestMadeWithMethodAndUrl( string $method, string $url, ?array $expected_body = null ): array {
		foreach ( $this->http_requests as $request ) {
			$body = $request['args']['body'] ?? null;
			if ( $request['args']['method'] === $method && $request['url'] === $url ) {
				$this->assertSame( 'Basic ' . base64_encode( TWILIO_SID . ':' . TWILIO_SECRET ), $request['args']['headers']['Authorization'] ?? null );
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
