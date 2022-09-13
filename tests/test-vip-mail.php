<?php

use PHPMailer\PHPMailer\PHPMailer;
use Yoast\PHPUnitPolyfills\Polyfills\AssertionRenames;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer does not follow the conventions
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail -- we are testing it

class VIP_Mail_Test extends WP_UnitTestCase {
	use AssertionRenames;

	public function setUp(): void {
		parent::setUp();
		reset_phpmailer_instance();
		if ( ! defined( 'USE_VIP_PHPMAILER' ) ) {
			define( 'USE_VIP_PHPMAILER', true );
		}
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

	public function test_load_VIP_PHPMailer() {
		$this->assertTrue( class_exists( 'VIP_PHPMailer', false ) );
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
}
