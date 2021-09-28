<?php

use Yoast\WPTestUtils\WPIntegration\TestCase;

class VIP_Mail_Test extends TestCase {
	public function set_up() {
		parent::set_up();
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

		$this->assertStringContainsString( 'From: WordPress <donotreply@wordpress.com>', $header );
	}

	public function test__has_tracking_header_with_key() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();
		$header = $mailer->get_sent()->header;

		$this->assertMatchesRegularExpression( '/X-Automattic-Tracking: 1:\d+:.+:\d+:\d+:\d+(\\r\\n|\\r|\\n)/', $header );
	}

	/**
	 * @preserveGlobalState disabled
	 * @runInSeparateProcess
	 */
	public function test_load_VIP_PHPMailer_gte_55() {
		global $wp_version;
		if ( version_compare( $wp_version, '5.5', '<' ) ) {
			$this->markTestSkipped( 'Not testing WP < 5.5' );
		}

		$this->assertEquals( true, class_exists( 'VIP_PHPMailer' ), 'VIP_PHPMailer should be loaded on >= 5.5. Version: ' . $wp_version );
	}

	/**
	 * @preserveGlobalState disabled
	 * @runInSeparateProcess
	 */
	public function test_dont_load_VIP_PHPMailer_lt_55() {
		global $wp_version;
		if ( version_compare( $wp_version, '5.5', '>=' ) ) {
			$this->markTestSkipped( 'Not testing WP < 5.5' );
		}

		$this->assertEquals( false, class_exists( 'VIP_PHPMailer' ), 'VIP_PHPMailer should not be loaded on < 5.5. Version: ' . $wp_version );
	}

	/**
	 * Test base cases here: local attachment and a remote (disallowed)
	 *
	 * @return void
	 */
	public function test__attachments_path_validation() {
		global $wp_version;
		if ( version_compare( $wp_version, '5.5', '<' ) ) {
			$this->markTestSkipped( 'Skipping VIP_PHPMailer logic validation on WP < 5.5' );
		}

		$temp = tmpfile();
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
