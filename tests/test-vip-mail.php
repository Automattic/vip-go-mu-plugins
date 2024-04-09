<?php
namespace Automattic\VIP\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Automattic\Test\Constant_Mocker;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer does not follow the conventions
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail -- we are testing it

class VIP_Mail_Test extends \WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		reset_phpmailer_instance();
		if ( ! Constant_Mocker::defined( 'USE_VIP_PHPMAILER' ) ) {
			Constant_Mocker::define( 'USE_VIP_PHPMAILER', true );
		}
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		reset_phpmailer_instance();
		parent::tearDown();
	}

	public function test__all_smtp_servers__not_array() {
		$GLOBALS['all_smtp_servers'] = false;

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();

		// Expect defaults to be unchanged
		$this->assertEquals( 'mail', $mailer->Mailer );
		$this->assertEquals( 'localhost', $mailer->Host );
	}

	public function test__all_smtp_servers__empty() {
		$GLOBALS['all_smtp_servers'] = [];

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();

		// Expect defaults to be unchanged
		$this->assertEquals( 'mail', $mailer->Mailer );
		$this->assertEquals( 'localhost', $mailer->Host );
	}

	public function test__has_smtp_servers() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertEquals( 'smtp', $mailer->Mailer );
		$this->assertTrue( in_array( $mailer->Host, $GLOBALS['all_smtp_servers'] ) );
	}

	public function test__mail_from() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();
		$header = $mailer->get_sent()->header;

		$this->assertStringContainsString( 'From: WordPress <donotreply@wpvip.com>', $header );
	}

	public function test__has_tracking_header_with_key() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();
		$header = $mailer->get_sent()->header;

		$this->assertMatchesRegularExpression( '/X-Automattic-Tracking: 1:\d+:.+:\d+:\d+:\d+(\\r\\n|\\r|\\n)/', $header );
	}

	public function test__vip_smtp_enabled() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];
		Constant_Mocker::define( 'VIP_SMTP_ENABLED', true );
		Constant_Mocker::define( 'VIP_SMTP_USERNAME', 'username' );
		Constant_Mocker::define( 'VIP_SMTP_PASSWORD', 'password' );
		Constant_Mocker::define( 'VIP_SMTP_PORT', 25 );

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();
		// Verify that the SMTP settings are set
		self::assertEquals( 25, $mailer->Port );
		self::assertEquals( true, $mailer->SMTPAuth );
		self::assertEquals( PHPMailer::ENCRYPTION_STARTTLS, $mailer->SMTPSecure );
		self::assertEquals( 'username', $mailer->Username );
		self::assertEquals( 'password', $mailer->Password );
	}

	public function test__vip_smtp_disabled() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];
		Constant_Mocker::define( 'VIP_SMTP_ENABLED', false );

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();

		// Verify that the SMTP Auth settings are not set
		self::assertEquals( false, $mailer->SMTPAuth );
	}

	public function test__handle_wp_mail_failures() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];
		Constant_Mocker::define( 'VIP_SMTP_ENABLED', true );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		// Simulate a mail failure
		$mail_data = [
			'to'                       => [ 'test@example.com' ],
			'subject'                  => 'Test Subject',
			'message'                  => 'Test Message',
			'phpmailer_exception_code' => 1,
			'attachments'              => [],
			'headers'                  => [],
		];

		do_action( 'wp_mail_failed', new \WP_Error( 'wp_mail_failed', 'SMTP Error: The following recipients failed: test@test.com: : Sender address rejected: not owned by user user-123', $mail_data ) );
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEquals( $mail_data['message'], $mailer->Body );
		$this->assertEquals( $mail_data['subject'], $mailer->Subject );
		$this->assertEquals( 'donotreply@wpvip.com', $mailer->From );
	}

	public function test__handle_wp_mail_failures_disabled() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];
		Constant_Mocker::define( 'VIP_SMTP_ENABLED', true );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'not-production' );

		// Simulate a mail failure
		$mail_data = [
			'to'                       => [ 'test@example.com' ],
			'subject'                  => 'Test Subject',
			'message'                  => 'Test Message',
			'phpmailer_exception_code' => 1,
			'attachments'              => [],
			'headers'                  => [],
		];

		do_action( 'wp_mail_failed', new \WP_Error( 'wp_mail_failed', 'SMTP Error: The following recipients failed: test@test.com: : Sender address rejected: not owned by user user-123', $mail_data ) );
		$mailer = tests_retrieve_phpmailer_instance();

		// mail failure is ignored, hence none of these values gets assigned.
		$this->assertEquals( '', $mailer->Body );
		$this->assertEquals( '', $mailer->Subject );
		$this->assertEquals( '', $mailer->From );
	}

	public function test_load_VIP_PHPMailer() {
		$this->assertTrue( class_exists( '\Automattic\VIP\Mail\VIP_PHPMailer', false ) );
	}

	/**
	 * Test base cases here: local attachment and a remote (disallowed)
	 *
	 * @return void
	 */
	public function test__attachments_path_validation() {
		$temp = tmpfile();
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		fwrite( $temp, "I'm a test file" );
		$filename = stream_get_meta_data( $temp )['uri'];
		wp_mail( 'test@example.com', 'Test with attachment', 'Test', '', [ $filename ] );
		fclose( $temp );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertStringContainsString( 'Content-Disposition: attachment; filename=' . basename( $filename ), $mailer->get_sent()->body );

		reset_phpmailer_instance();

		wp_mail( 'test@example.com', 'Test with attachment', 'Test', '', [ 'http://lorempixel.com/400/200/' ] );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertThat( $mailer->get_sent()->body, $this->logicalNot( $this->stringContains( 'Content-Disposition: attachment; filename=' ) ) );
	}

	/**
	 * @ticket GH-1066
	 */
	public function test_smtp_servers_not_overwritten(): void {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];

		$expected = 'preset-server';

		add_action( 'phpmailer_init', function ( PHPMailer &$phpmailer ) use ( $expected ) {
			$phpmailer->isSMTP();
			$phpmailer->Host = $expected;
		} );

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();

		self::assertEquals( $expected, $mailer->Host );
	}

	/**
	 * @ticket GH-3638
	 */
	public function test_filter_removal(): void {
		$instance = VIP_SMTP::instance();

		self::assertEquals( 1, has_filter( 'wp_mail_from', [ $instance, 'filter_wp_mail_from' ] ) );

		remove_filter( 'wp_mail_from', [ $instance, 'filter_wp_mail_from' ], 1 );
		self::assertFalse( has_filter( 'wp_mail_from', [ $instance, 'filter_wp_mail_from' ] ) );

		$expected = 'test-gh-3638@example.com';
		add_filter( 'wp_mail_from', fn () => $expected, 0 );

		$actual = apply_filters( 'wp_mail_from', 'bad@example.com' );

		self::assertEquals( $expected, $actual );
	}

	public function test_noop_mailer__filter_only() {
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			static function ( $errno, $errstr ) {
				restore_error_handler();
				throw new \Exception( $errstr, $errno ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			},
			E_ALL
		);

		add_filter( 'vip_block_wp_mail', '__return_true' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'VIP_Noop_Mailer::send: skipped sending email with subject `Test` to test@example.com' );

		wp_mail( 'test@example.com', 'Test', 'Should not be sent' );

		restore_error_handler();
	}

	public function test_noop_mailer__constant_true_filter_false() {
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			static function ( $errno, $errstr ) {
				restore_error_handler();
				throw new \Exception( $errstr, $errno ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			},
			E_ALL
		);

		Constant_Mocker::define( 'VIP_BLOCK_WP_MAIL', true );
		add_filter( 'vip_block_wp_mail', '__return_false' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'VIP_Noop_Mailer::send: skipped sending email with subject `Test` to test@example.com' );

		wp_mail( 'test@example.com', 'Test', 'Should not be sent' );

		restore_error_handler();
	}

	public function test_noop_mailer__constant_only() {
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			static function ( $errno, $errstr ) {
				restore_error_handler();
				throw new \Exception( $errstr, $errno ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			},
			E_ALL
		);

		Constant_Mocker::define( 'VIP_BLOCK_WP_MAIL', true );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'VIP_Noop_Mailer::send: skipped sending email with subject `Test` to test@example.com' );

		wp_mail( 'test@example.com', 'Test', 'Should not be sent' );

		restore_error_handler();
	}

	public function test_noop_mailer__constant_and_filter_false() {
		Constant_Mocker::define( 'VIP_BLOCK_WP_MAIL', false );
		add_filter( 'vip_block_wp_mail', '__return_false' );

		$body = 'Testing should send';
		wp_mail( 'test@example.com', 'Test', $body );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertEquals( $body, $mailer->Body );
	}

	public function test_noop_mailer__constant_false_filter_true() {
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			static function ( $errno, $errstr ) {
				restore_error_handler();
				throw new \Exception( $errstr, $errno ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			},
			E_ALL
		);

		Constant_Mocker::define( 'VIP_BLOCK_WP_MAIL', false );
		add_filter( 'vip_block_wp_mail', '__return_true' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'VIP_Noop_Mailer::send: skipped sending email with subject `Test` to test@example.com' );

		wp_mail( 'test@example.com', 'Test', 'Should not be sent' );

		restore_error_handler();
	}
}
