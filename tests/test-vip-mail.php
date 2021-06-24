<?php


/**
 * @preserveGlobalState false
 * @runInSeparateProcess true
 */
class VIP_Mail_Test extends \WP_UnitTestCase {
	public function setUp() {
		parent::setUp();
		reset_phpmailer_instance();
	}

	protected function tearDown(): void {
		reset_phpmailer_instance();
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

		$this->assertContains( 'From: WordPress <donotreply@wordpress.com>', $header );
	}

	public function test__has_tracking_header_with_key() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();
		$header = $mailer->get_sent()->header;

		$this->assertRegExp( '/X-Automattic-Tracking: 1:\d+:.+:\d+:\d+:\d+(\\r\\n|\\r|\\n)/', $header );
	}

	/**
	 * Todo: remove this once we remove 5.4 stack
	 *
	 */
	public function test__conditional_loading_of_VIP_PHPMailer() {
		global $wp_version;
		if ( version_compare( $wp_version, '5.5', '>=' ) ) {
			$this->assertEquals( class_exists( 'VIP_PHPMailer' ), true, 'VIP_PHPMailer should be loaded on >= 5.5' );
		} else {
			$this->assertEquals( class_exists( 'VIP_PHPMailer' ), false, 'VIP_PHPMailer should not be loaded on <= 5.4' );
		}
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

		$this->assertStringContainsString( 'Content-Disposition: attachment; filename=' . basename( $filename ) , $mailer->get_sent()->body );

		reset_phpmailer_instance();

		wp_mail( 'test@example.com', 'Test with attachment', 'Test', '', [ 'http://lorempixel.com/400/200/' ] );
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertThat( $mailer->get_sent()->body, $this->logicalNot( $this->stringContains('Content-Disposition: attachment; filename=') ) );
	}
}
