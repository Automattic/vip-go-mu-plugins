<?php
namespace Automattic\VIP\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Automattic\Test\Constant_Mocker;

use function Automattic\Test\Utils\get_class_method_as_public;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer does not follow the conventions
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail -- we are testing it

class VIP_Mail_Test extends \WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		reset_phpmailer_instance();
	}

	public function tearDown(): void {
		unset( $GLOBALS['all_smtp_servers'] );
		reset_phpmailer_instance();
		parent::tearDown();
	}

	/**
	 * Send a test email through wp_mail() and return the mailer it used.
	 *
	 * @param mixed $smtp_servers Value for the `$all_smtp_servers` global.
	 */
	private function send_test_mail( $smtp_servers = [ 'server1', 'server2' ], string $body = 'Test' ): \MockPHPMailer {
		$GLOBALS['all_smtp_servers'] = $smtp_servers;

		wp_mail( 'test@example.com', 'Test', $body );

		return tests_retrieve_phpmailer_instance();
	}

	public function data_invalid_smtp_servers(): array {
		return [
			'not an array' => [ false ],
			'empty array'  => [ [] ],
		];
	}

	/**
	 * @dataProvider data_invalid_smtp_servers
	 */
	public function test__all_smtp_servers__invalid( $smtp_servers ) {
		$mailer = $this->send_test_mail( $smtp_servers );

		// Expect defaults to be unchanged
		$this->assertEquals( 'mail', $mailer->Mailer );
		$this->assertEquals( 'localhost', $mailer->Host );
	}

	public function test__has_smtp_servers() {
		$smtp_servers = [ 'server1', 'server2' ];
		$mailer       = $this->send_test_mail( $smtp_servers );
		$header       = $mailer->get_sent()->header;

		$this->assertEquals( 'smtp', $mailer->Mailer );
		$this->assertContains( $mailer->Host, $smtp_servers );
		$this->assertStringContainsString( 'From: WordPress <donotreply@wpvip.com>', $header );
		$this->assertMatchesRegularExpression( '/X-Automattic-Tracking: 1:\d+:.+:\d+:\d+:\d+(\\r\\n|\\r|\\n)/', $header );
	}

	public function test__vip_smtp_enabled() {
		Constant_Mocker::define( 'VIP_SMTP_ENABLED', true );
		Constant_Mocker::define( 'VIP_SMTP_USERNAME', 'username' );
		Constant_Mocker::define( 'VIP_SMTP_PASSWORD', 'password' );
		Constant_Mocker::define( 'VIP_SMTP_PORT', 25 );

		$mailer = $this->send_test_mail();
		// Verify that the SMTP settings are set
		self::assertEquals( 25, $mailer->Port );
		self::assertEquals( true, $mailer->SMTPAuth );
		self::assertEquals( PHPMailer::ENCRYPTION_STARTTLS, $mailer->SMTPSecure );
		self::assertEquals( 'username', $mailer->Username );
		self::assertEquals( 'password', $mailer->Password );
	}

	public function test__vip_smtp_disabled() {
		Constant_Mocker::define( 'VIP_SMTP_ENABLED', false );

		$mailer = $this->send_test_mail();

		// Verify that the SMTP Auth settings are not set
		self::assertEquals( false, $mailer->SMTPAuth );
	}

	public function data_attachment_paths(): array {
		return [
			'local file'                 => [ '/tmp/attachment.txt', true ],
			'VIP uploads stream'         => [ 'vip://wp-content/uploads/2024/01/attachment.pdf', true ],
			'VIP stream outside uploads' => [ 'vip://wp-content/themes/attachment.php', false ],
			'remote URL'                 => [ 'http://lorempixel.com/400/200/', false ],
		];
	}

	/**
	 * VIP_PHPMailer permits VIP File System uploads, unlike core PHPMailer, but still rejects other URLs.
	 *
	 * @dataProvider data_attachment_paths
	 */
	public function test__attachments_path_validation( string $path, bool $permitted ) {
		$is_permitted_path = get_class_method_as_public( VIP_PHPMailer::class, 'isPermittedPath' );

		$this->assertSame( $permitted, $is_permitted_path->invoke( null, $path ) );
	}

	public function data_preset_smtp_host(): array {
		// [ VIP_SMTP_HOST_OVERWRITE_ALLOW_LIST, host set by another phpmailer_init callback, $all_smtp_servers, expected host ]
		return [
			'no allow list'                 => [ null, 'preset-server', [ 'server1', 'server2' ], 'preset-server' ],
			'preset host not in allow list' => [ 'server1,not-preset-server,server2', 'preset-server', [ 'server1', 'server2' ], 'preset-server' ],
			'preset host in the allow list' => [ 'server1,overwritable-host,server2', 'overwritable-host', [ 'new-host' ], 'new-host' ],
		];
	}

	/**
	 * @ticket GH-1066
	 * @dataProvider data_preset_smtp_host
	 */
	public function test_preset_smtp_host( ?string $allow_list, string $preset_host, array $smtp_servers, string $expected_host ): void {
		if ( null !== $allow_list ) {
			Constant_Mocker::define( 'VIP_SMTP_HOST_OVERWRITE_ALLOW_LIST', $allow_list );
		}

		add_action( 'phpmailer_init', function ( PHPMailer &$phpmailer ) use ( $preset_host ) {
			$phpmailer->isSMTP();
			$phpmailer->Host = $preset_host;
		} );

		$mailer = $this->send_test_mail( $smtp_servers );

		self::assertEquals( $expected_host, $mailer->Host );
	}

	/**
	 * The wp_mail_from filter must stay removable: a public callable on the singleton at priority 1.
	 *
	 * @ticket GH-3638
	 */
	public function test_filter_removal(): void {
		self::assertSame( 1, has_filter( 'wp_mail_from', [ VIP_SMTP::instance(), 'filter_wp_mail_from' ] ) );
	}

	public function data_noop_mailer(): array {
		// [ VIP_BLOCK_WP_MAIL, vip_block_wp_mail filter result, whether mail is blocked ]
		return [
			'filter only'                 => [ null, true, true ],
			'constant only'               => [ true, null, true ],
			'constant true, filter false' => [ true, false, true ],
			'constant false, filter true' => [ false, true, true ],
			'constant and filter false'   => [ false, false, false ],
		];
	}

	/**
	 * @dataProvider data_noop_mailer
	 */
	public function test_noop_mailer( ?bool $constant, ?bool $filter, bool $blocked ): void {
		if ( null !== $constant ) {
			Constant_Mocker::define( 'VIP_BLOCK_WP_MAIL', $constant );
		}

		if ( null !== $filter ) {
			add_filter( 'vip_block_wp_mail', $filter ? '__return_true' : '__return_false' );
		}

		if ( $blocked ) {
			$this->expectException( \Exception::class );
			$this->expectExceptionMessage( 'VIP_Noop_Mailer::send: skipped sending email with subject `Test` to test@example.com' );

			// Turn the Noop mailer's notice into an exception.
			set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
				static function ( $errno, $errstr ) {
					throw new \Exception( $errstr, $errno ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				},
				E_ALL
			);
		}

		$body = 'Testing should send';
		try {
			$mailer = $this->send_test_mail( null, $body );
		} finally {
			if ( $blocked ) {
				restore_error_handler();
			}
		}

		$this->assertEquals( $body, $mailer->Body );
	}
}
