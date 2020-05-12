<?php

class VIP_Mail_Test extends \WP_UnitTestCase {
	public function setUp() {
		parent::setUp();
		reset_phpmailer_instance();
	}

	public function test__disable_vip_smtp() {
		$GLOBALS['all_smtp_servers'] = [ 'server1', 'server2' ];

		add_filter( 'vip_smtp_enabled', '__return_false' );

		wp_mail( 'test@example.com', 'Test', 'Test' );
		$mailer = tests_retrieve_phpmailer_instance();

		// Expect defaults to be unchanged
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->assertEquals( 'mail', $mailer->Mailer );
		$this->assertEquals( 'localhost', $mailer->Host );
		// phpcs:enable
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

		$this->assertRegExp( '/X-Automattic-Tracking: 1:\d+:.+:\d+:\d+:\d+\n/', $header );
	}
}
