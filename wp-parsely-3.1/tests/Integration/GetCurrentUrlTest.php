<?php
/**
 * \Parsely\Parsely::get_current_url() tests.
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration;

use Parsely\Parsely;

/**
 * \Parsely\Parsely::get_current_url() tests.
 */
final class GetCurrentUrlTest extends TestCase {
	/**
	 * Data provider for test_get_current_url
	 *
	 * @return iterable
	 */
	public function data_for_test_get_current_url() {
		yield 'Home is http with force HTTPS true' => array(
			'force_https' => true,
			'home'        => 'http://example.com',
			'expected'    => 'https://example.com',
		);

		yield 'Home is https with force HTTPS true' => array(
			'force_https' => true,
			'home'        => 'https://example.com',
			'expected'    => 'https://example.com',
		);

		yield 'Home is http with port with force HTTPS true' => array(
			'force_https' => true,
			'home'        => 'http://example.com:1234',
			'expected'    => 'https://example.com:1234',
		);

		yield 'Home is https with port with force HTTPS true' => array(
			'force_https' => true,
			'home'        => 'https://example.com:1234',
			'expected'    => 'https://example.com:1234',
		);

		yield 'Home is http with port and path with force HTTPS true' => array(
			'force_https' => true,
			'home'        => 'http://example.com:1234/foo/bar',
			'expected'    => 'https://example.com:1234/foo/bar',
		);

		yield 'Home is https with port and path with force HTTPS true' => array(
			'force_https' => true,
			'home'        => 'https://example.com:1234/foo/bar',
			'expected'    => 'https://example.com:1234/foo/bar',
		);

		// Start cases with 'force_https_canonicals' = false.
		yield 'Home is http with force HTTPS false' => array(
			'force_https' => false,
			'home'        => 'http://example.com',
			'expected'    => 'http://example.com',
		);

		yield 'Home is https with force HTTPS false' => array(
			'force_https' => false,
			'home'        => 'https://example.com',
			'expected'    => 'http://example.com',
		);

		yield 'Home is http with port with force HTTPS false' => array(
			'force_https' => false,
			'home'        => 'http://example.com:1234',
			'expected'    => 'http://example.com:1234',
		);

		yield 'Home is https with port with force HTTPS false' => array(
			'force_https' => false,
			'home'        => 'https://example.com:1234',
			'expected'    => 'http://example.com:1234',
		);

		yield 'Home is http with port and path with force HTTPS false' => array(
			'force_https' => false,
			'home'        => 'http://example.com:1234/foo/bar',
			'expected'    => 'http://example.com:1234/foo/bar',
		);

		yield 'Home is https with port and path with force HTTPS false' => array(
			'force_https' => false,
			'home'        => 'https://example.com:1234/foo/bar',
			'expected'    => 'http://example.com:1234/foo/bar',
		);
	}

	/**
	 * Test the get_current_url() method.
	 *
	 * Assert that homepage, a specific page, and a random URL return the expected URL.
	 *
	 * @testdox Given Force HTTPS is $force_https, when home is $home, then expect URLs starting with $expected.
	 * @dataProvider data_for_test_get_current_url
	 * @covers \Parsely\Parsely::get_current_url
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 *
	 * @param bool   $force_https Force HTTPS Canonical setting value.
	 * @param string $home        Home URL.
	 * @param string $expected    Expected current URL.
	 */
	public function test_get_current_url( bool $force_https, string $home, string $expected ): void {
		$this->set_options( array( 'force_https_canonicals' => $force_https ) );
		update_option( 'home', $home );

		$this->assertCurrentUrlForHomepage( $expected );
		$this->assertCurrentUrlForSpecificPostWithId( $expected );
		$this->assertCurrentUrlForRandomUrl( $expected );
	}

	/**
	 * Assert the correct current URL for the homepage.
	 *
	 * @param string $expected Expected start of the URL.
	 */
	private function assertCurrentUrlForHomepage( string $expected ): void {
		$this->go_to( '/' );

		$parsely = new Parsely();
		$res     = $parsely->get_current_url();

		self::assertEquals( $expected . '/', $res, 'Homepage page does not match.' );
	}

	/**
	 * Assert the correct current URL for a post by ID.
	 *
	 * @param string $expected Expected start of the URL.
	 */
	private function assertCurrentUrlForSpecificPostWithId( string $expected ): void {
		$post_id = $this->go_to_new_post();

		$parsely = new Parsely();
		$res     = $parsely->get_current_url( 'post', $post_id );

		self::assertEquals( $expected . '/?p=' . $post_id, $res, 'Specific post by ID does not match.' );
	}

	/**
	 * Assert the correct current URL for a random URL with trailing slash.
	 *
	 * @param string $expected Expected start of the URL.
	 */
	private function assertCurrentUrlForRandomUrl( string $expected ): void {
		$parsely = new Parsely();
		$this->go_to( '/random/url/' );
		$res = $parsely->get_current_url();

		$constructed_expected = $expected . '/random/url/';
		self::assertEquals( $constructed_expected, $res, 'Random URL does not match.' );
	}
}
