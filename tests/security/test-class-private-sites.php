<?php

namespace Automattic\VIP\Security;

use Yoast\WPTestUtils\WPIntegration\TestCase;

class Private_Sites_Test extends TestCase {
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_private_regular_site() {
		define( 'WPCOM_VIP_BASIC_AUTH', false );
		define( 'WPCOM_VIP_IP_ALLOW_LIST', false );

		$is_jp_private = \Automattic\VIP\Security\Private_Sites::is_jetpack_private();

		$this->assertFalse( $is_jp_private );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_private_when_constant_set_false() {
		define( 'VIP_JETPACK_IS_PRIVATE', false );

		// But also set other constants that would otherwise set it to private
		define( 'WPCOM_VIP_BASIC_AUTH', true );

		$is_jp_private = \Automattic\VIP\Security\Private_Sites::is_jetpack_private();

		$this->assertFalse( $is_jp_private );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_private_when_constant_set_true() {
		define( 'VIP_JETPACK_IS_PRIVATE', true );

		// But also set other constants that would otherwise not set it to private
		define( 'WPCOM_VIP_BASIC_AUTH', false );
		define( 'WPCOM_VIP_IP_ALLOW_LIST', false );

		$is_jp_private = \Automattic\VIP\Security\Private_Sites::is_jetpack_private();

		$this->assertTrue( $is_jp_private );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_private_with_ip_restrictions() {
		define( 'WPCOM_VIP_BASIC_AUTH', false );
		define( 'WPCOM_VIP_IP_ALLOW_LIST', true );

		$is_jp_private = \Automattic\VIP\Security\Private_Sites::is_jetpack_private();

		$this->assertTrue( $is_jp_private );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_jetpack_private_with_http_basic_auth() {
		define( 'WPCOM_VIP_BASIC_AUTH', true );
		define( 'WPCOM_VIP_IP_ALLOW_LIST', false );

		$is_jp_private = \Automattic\VIP\Security\Private_Sites::is_jetpack_private();

		$this->assertTrue( $is_jp_private );
	}

	public function test__filter_jetpack_active_modules() {
		$modules = array(
			'json-api',
			'enhanced-distribution',
			'search',
			'something-else',
		);

		$private = \Automattic\VIP\Security\Private_Sites::instance();

		$filtered = $private->filter_jetpack_active_modules( $modules );

		$this->assertEquals( array( 'something-else' ), $filtered );
	}

	public function test__filter_jetpack_get_available_modules() {
		$modules = array(
			'json-api' => true,
			'enhanced-distribution' => true,
			'search' => true,
			'something-else' => true,
		);

		$private = \Automattic\VIP\Security\Private_Sites::instance();

		$filtered = $private->filter_jetpack_get_available_modules( $modules );

		$expected = array(
			'search' => true,
			'something-else' => true,
		);

		$this->assertEquals( $expected, $filtered );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter_blog_public_option_for_sync() {
		define( 'VIP_JETPACK_IS_TRUE', true );

		$private = \Automattic\VIP\Security\Private_Sites::instance();

		$input = array( 'blog_public', 'foo', '1' );

		$filtered = $private->filter_blog_public_option_for_sync( $input );

		$this->assertEquals( '-1', $filtered[2] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__filter_blog_public_option_for_sync_other_option() {
		define( 'VIP_JETPACK_IS_TRUE', true );

		$private = \Automattic\VIP\Security\Private_Sites::instance();

		$input = array( 'foo', 'bar', '1' );

		$filtered = $private->filter_blog_public_option_for_sync( $input );

		// Unchanged
		$this->assertEquals( '1', $filtered[2] );
	}

	public function test__filter_blog_public_option_for_full_sync() {
		define( 'VIP_JETPACK_IS_TRUE', true );

		$private = \Automattic\VIP\Security\Private_Sites::instance();

		$input = array(
			'blog_public' => 1,
			'foo' => 'bar',
		);

		$filtered = $private->filter_blog_public_option_for_full_sync( $input );

		$expected = array(
			'blog_public' => '-1',
			'foo' => 'bar',
		);

		$this->assertEquals( $expected, $filtered );
	}
}
