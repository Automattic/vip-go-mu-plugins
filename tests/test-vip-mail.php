<?php

use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
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

	/**
	 * @global string $wp_version
	 */
	public function test_load_VIP_PHPMailer() {
		global $wp_version;
		$should_be_loaded = version_compare( $wp_version, '5.5', '>=' );
		$this->assertEquals( $should_be_loaded, class_exists( 'VIP_PHPMailer', false ), 'VIP_PHPMailer should be loaded only for WP >= 5.5. Version: ' . $wp_version );
	}

	/**
	 * Test base cases here: local attachment and a remote (disallowed)
	 *
	 * @return void
	 * @global string $wp_version
	 */
	public function test__attachments_path_validation() {
		global $wp_version;

		if ( version_compare( $wp_version, '5.5', '<' ) ) {
			$this->markTestSkipped( 'Skipping VIP_PHPMailer logic validation on WP < 5.5' );
		}

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
}
