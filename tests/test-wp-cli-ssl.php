<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\WP_CLI\maybe_toggle_is_ssl;
use function Automattic\VIP\WP_CLI\init_is_ssl_toggle_for_multisite;

use WP_UnitTestCase;

// phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid
class VIP_WP_CLI__SSL__Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- safe - test environment
		$this->initial_https_value = $_SERVER['HTTPS'] ?? null;
	}

	public function tearDown(): void {
		$_SERVER['HTTPS'] = $this->initial_https_value;

		parent::tearDown();
	}

	public function get_test_data() {
		return [
			// 1) siteurl
			// 2) starting SERVER['HTTPS'] value
			// 3) expected SERVER['HTTPS'] value

			'https_site_url__not_is_ssl' => [
				'https://example.com',
				null,
				'on',
			],

			'https_site_url__is_ssl'     => [
				'https://example.com',
				'on',
				'on',
			],

			'http_site_url__not_is_ssl'  => [
				'http://example.com',
				null,
				null,
			],

			'http_site_url__is_ssl'      => [
				'http://example.com',
				'on',
				null,
			],
		];
	}

	/**
	 * @dataProvider get_test_data
	 */
	public function test__maybe_toggle_is_ssl( $siteurl, $initial_https_value, $expected_https_value ) {
		add_filter( 'pre_option_siteurl', function() use ( $siteurl ) {
			return $siteurl;
		} );

		if ( ! is_null( $initial_https_value ) ) {
			$_SERVER['HTTPS'] = $initial_https_value;
		} else {
			unset( $_SERVER['HTTPS'] );
		}

		maybe_toggle_is_ssl();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$actual_https_value = $_SERVER['HTTPS'] ?? null;

		$this->assertEquals( $expected_https_value, $actual_https_value );
	}

	public function test__is_ssl_toggle_for_multisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Not relevant on single-site' );
			return;
		}

		$blog_1_id = $this->factory->blog->create_object( [
			'domain'  => 'not-ssl.com',
			'path'    => '/',
			'title'   => 'Not SSL',
			'site_id' => 1,
		] );

		$blog_2_id = $this->factory->blog->create_object( [
			'domain'  => 'is-ssl.com',
			'path'    => '/',
			'title'   => 'Is SSL',
			'site_id' => 1,
		] );
		update_blog_option( $blog_2_id, 'siteurl', 'https://is-ssl.com' );

		init_is_ssl_toggle_for_multisite();

		// Change from ! is_ssl() to SSL site => is_ssL() === true
		switch_to_blog( $blog_2_id );

		self::assertTrue( isset( $_SERVER['HTTPS'] ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->assertEquals( 'on', $_SERVER['HTTPS'] );

		// Change to same site
		switch_to_blog( $blog_2_id );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->assertEquals( 'on', $_SERVER['HTTPS'] );

		// Change from is_ssl() to non-SSL site => is_ssl() === false
		switch_to_blog( $blog_1_id );
		$this->assertFalse( isset( $_SERVER['HTTPS'] ) );

	}
}
