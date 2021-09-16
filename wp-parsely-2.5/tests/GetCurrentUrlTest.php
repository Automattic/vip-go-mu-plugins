<?php
/**
 * \Parsely::get_current_url() tests.
 *
 * @package Parsely\Tests
 */

namespace Parsely\Tests;

use Parsely;

/**
 * \Parsely::get_current_url() tests.
 */
final class GetCurrentUrlTest extends TestCase {
	/**
	 * Data provider for test_get_current_url
	 *
	 * @return array[]
	 */
	public function data_for_test_get_current_url() {
		return array(
			// Start cases with 'force_https_canonicals' = true.
			array(
				true,
				'http://example.com',
				'https://example.com',
			),
			array(
				true,
				'https://example.com',
				'https://example.com',
			),
			array(
				true,
				'http://example.com:1234',
				'https://example.com:1234',
			),
			array(
				true,
				'https://example.com:1234',
				'https://example.com:1234',
			),
			array(
				true,
				'http://example.com:1234/foo/bar',
				'https://example.com:1234/foo/bar',
			),
			array(
				true,
				'https://example.com:1234/foo/bar',
				'https://example.com:1234/foo/bar',
			),
			// Start cases with 'force_https_canonicals' = false.
			array(
				false,
				'http://example.com',
				'http://example.com',
			),
			array(
				false,
				'https://example.com',
				'http://example.com',
			),
			array(
				false,
				'http://example.com:1234',
				'http://example.com:1234',
			),
			array(
				false,
				'https://example.com:1234',
				'http://example.com:1234',
			),
			array(
				false,
				'http://example.com:1234/foo/bar',
				'http://example.com:1234/foo/bar',
			),
			array(
				false,
				'https://example.com:1234/foo/bar',
				'http://example.com:1234/foo/bar',
			),
		);
	}

	/**
	 * Test the get_current_url() method.
	 *
	 * @dataProvider data_for_test_get_current_url
	 * @covers \Parsely::get_current_url
	 * @uses \Parsely::get_options
	 * @uses \Parsely::update_metadata_endpoint
	 *
	 * @param bool   $force_https Force HTTPS Canonical setting value.
	 * @param string $home        Home URL.
	 * @param string $expected    Expected current URL.
	 */
	public function test_get_current_url( $force_https, $home, $expected ) {
		$parsely                           = new Parsely();
		$options                           = get_option( Parsely::OPTIONS_KEY );
		$options['force_https_canonicals'] = $force_https;
		update_option( Parsely::OPTIONS_KEY, $options );

		update_option( 'home', $home );

		// Test homepage.
		$this->go_to( '/' );
		$res = $parsely->get_current_url();
		self::assertStringStartsWith( $expected, $res );

		// Test a specific post.
		$post_array = $this->create_test_post_array();
		$post_id    = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post_id );
		$res = $parsely->get_current_url( 'post', $post_id );
		self::assertStringStartsWith( $expected, $res );

		// Test a random URL.
		$this->go_to( '/random-url' );
		$res = $parsely->get_current_url();
		self::assertStringStartsWith( $expected, $res );
	}
}
