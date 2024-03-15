<?php

class WPCOM_VIP_Should_Load_Plugins_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		wp_installing( true );
	}

	public function tearDown(): void {
		wp_installing( false );
		parent::tearDown();
	}

	/**
	 * @dataProvider data_load_in_wp_activate
	 */
	public function test_load_in_wp_activate( string $request_uri, bool $expected ): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$saved = $_SERVER['REQUEST_URI'] ?? null;
		try {
			$_SERVER['REQUEST_URI'] = $request_uri;
			$actual                 = wpcom_vip_should_load_plugins( true );

			self::assertSame( $expected, $actual );
		} finally {
			$_SERVER['REQUEST_URI'] = $saved;
		}
	}

	public function data_load_in_wp_activate(): iterable {
		return [
			[ '/wp-activate.php', true ],
			[ '/wp-activate.php?something', true ],
			[ '/wp-activate.php/path', false ],
			[ '/?/wp-activate.php', false ],
			[ '/dir/wp-activate.php', true ],
			[ '/dir/subdir/wp-activate.php', true ],
		];
	}
}
