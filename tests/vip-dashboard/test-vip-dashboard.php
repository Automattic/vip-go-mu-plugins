<?php

require_once __DIR__ . '/../../vip-dashboard.php';

class Test_VIP_Dashboard extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		reset_phpmailer_instance();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['_wpnonce'] = wp_create_nonce( 'vip-dashboard' );
		$_POST            = array(
			'name'     => 'Support Requester',
			'email'    => 'requester@example.org',
			'subject'  => 'Test support request',
			'body'     => 'Support request body',
			'cc'       => 'colleague@example.org',
			'priority' => 'Emergency',
		);
	}

	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		reset_phpmailer_instance();
		parent::tearDown();
	}

	private function submit_contact_form(): array {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ) );
		ob_start();
		try {
			vip_contact_form_handler();
		} catch ( WPDieException $exception ) {
			self::assertSame( '', $exception->getMessage() );
		} finally {
			$response = ob_get_clean();
			remove_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ) );
			remove_filter( 'wp_doing_ajax', '__return_true' );
		}

		return json_decode( $response, true );
	}

	public function test_contact_form_respects_configured_sender(): void {
		add_filter( 'wp_mail_from', function () {
			return 'verified@example.org';
		} );

		$response = $this->submit_contact_form();
		$mailer   = tests_retrieve_phpmailer_instance();
		$header   = $mailer->get_sent()->header;

		self::assertSame( 'success', $response['status'] );
		self::assertStringContainsString( 'From: Support Requester <verified@example.org>', $header );
		self::assertStringContainsString( 'Reply-To: requester@example.org', $header );
		self::assertStringContainsString( VIP_SUPPORT_EMAIL, $header );
		self::assertStringContainsString( 'Cc: colleague@example.org', $header );
		self::assertStringContainsString( 'Subject: [Emergency] Test support request', $header );
		self::assertSame( 'WordPress', apply_filters( 'wp_mail_from_name', 'WordPress' ) );
	}

	public function test_contact_form_uses_default_sender(): void {
		$response = $this->submit_contact_form();
		$header   = tests_retrieve_phpmailer_instance()->get_sent()->header;

		self::assertSame( 'success', $response['status'] );
		self::assertStringContainsString( '<donotreply@wpvip.com>', $header );
	}

	public function test_contact_form_logs_redacted_failure_without_exposing_diagnostics(): void {
		$existing_failure_hook = has_action( 'wp_mail_failed' );
		add_filter( 'pre_wp_mail', function () {
			do_action( 'wp_mail_failed', new WP_Error(
				'wp_mail_failed',
				"Unverified sender private@example.org; api_key=private-key; password=\"private password\"; Bearer private-token; secret='private secret'; client_secret=private-client-secret; token=private-bare-token; credential=private-credential\nInternal diagnostic marker",
				array( 'message' => 'Private mail data' )
			) );
			return false;
		} );

		$log_file     = wp_tempnam( 'vip-contact-error' );
		$previous_log = ini_get( 'error_log' );
		// phpcs:ignore WordPress.PHP.IniSet.Risky -- Capture logs for this test and restore below.
		ini_set( 'error_log', $log_file );
		try {
			$response = $this->submit_contact_form();
			// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local temporary log file.
			$log = file_get_contents( $log_file );
		} finally {
			// phpcs:ignore WordPress.PHP.IniSet.Risky -- Restore the original log destination.
			ini_set( 'error_log', $previous_log );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Created by wp_tempnam above.
			unlink( $log_file );
		}

		self::assertSame( 'error', $response['status'] );
		self::assertStringNotContainsString( 'Unverified sender', $response['message'] );
		self::assertStringNotContainsString( 'Internal diagnostic marker', $response['message'] );
		self::assertStringNotContainsString( 'Private mail data', $response['message'] );
		self::assertStringContainsString( 'There was an error sending the support request.', $response['message'] );
		self::assertStringContainsString( 'mailto:' . VIP_SUPPORT_EMAIL, $response['message'] );
		self::assertStringContainsString( 'VIP Dashboard support request failed:', $log );
		self::assertStringContainsString( 'Unverified sender [redacted email]', $log );
		self::assertStringContainsString( 'Internal diagnostic marker', $log );
		self::assertStringNotContainsString( 'private@example.org', $log );
		self::assertStringNotContainsString( 'private-key', $log );
		self::assertStringNotContainsString( 'private password', $log );
		self::assertStringNotContainsString( 'private-token', $log );
		self::assertStringNotContainsString( 'private secret', $log );
		self::assertStringNotContainsString( 'private-client-secret', $log );
		self::assertStringNotContainsString( 'private-bare-token', $log );
		self::assertStringNotContainsString( 'private-credential', $log );
		self::assertStringNotContainsString( 'Private mail data', $log );
		self::assertStringContainsString( '\\nInternal diagnostic marker', $log );
		self::assertSame( $existing_failure_hook, has_action( 'wp_mail_failed' ) );
		self::assertSame( 'WordPress', apply_filters( 'wp_mail_from_name', 'WordPress' ) );
	}

	public function test_contact_form_preserves_fallback_when_mailer_provides_no_error(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$response = $this->submit_contact_form();

		self::assertSame( 'error', $response['status'] );
		self::assertStringContainsString( 'There was an error sending the support request.', $response['message'] );
		self::assertStringContainsString( 'mailto:' . VIP_SUPPORT_EMAIL, $response['message'] );
	}

	public function test_vip_echo_mailto_vip_hosting(): void {
		wp_set_current_user( 1 );
		$actual = vip_echo_mailto_vip_hosting( 'Link', false );
		self::assertStringNotContainsString( '&body=', $actual );
		self::assertStringNotContainsString( '\n', $actual );

		$matches = [];
		preg_match( '/href="([^"]+)"/', $actual, $matches );
		self::assertIsArray( $matches );
		self::assertArrayHasKey( 1, $matches );
		$href = $matches[1];

		self::assertStringNotContainsString( ' ', $href );
	}
}
