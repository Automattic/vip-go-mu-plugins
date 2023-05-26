<?php

namespace Automattic\VIP\Security;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

class Private_Sites_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test__is_jetpack_private_regular_site() {
		Constant_Mocker::define( 'WPCOM_VIP_BASIC_AUTH', false );
		Constant_Mocker::define( 'WPCOM_VIP_IP_ALLOW_LIST', false );

		$is_jp_private = Private_Sites::is_jetpack_private();

		$this->assertFalse( $is_jp_private );
	}

	public function test__is_jetpack_private_when_constant_set_false() {
		Constant_Mocker::define( 'VIP_JETPACK_IS_PRIVATE', false );

		// But also set other constants that would otherwise set it to private
		Constant_Mocker::define( 'WPCOM_VIP_BASIC_AUTH', true );

		$is_jp_private = Private_Sites::is_jetpack_private();

		$this->assertFalse( $is_jp_private );
	}

	public function test__is_jetpack_private_when_constant_set_true() {
		Constant_Mocker::define( 'VIP_JETPACK_IS_PRIVATE', true );

		// But also set other constants that would otherwise not set it to private
		Constant_Mocker::define( 'WPCOM_VIP_BASIC_AUTH', false );
		Constant_Mocker::define( 'WPCOM_VIP_IP_ALLOW_LIST', false );

		$is_jp_private = Private_Sites::is_jetpack_private();

		$this->assertTrue( $is_jp_private );
	}

	public function test__is_jetpack_private_with_ip_restrictions() {
		Constant_Mocker::define( 'WPCOM_VIP_BASIC_AUTH', false );
		Constant_Mocker::define( 'WPCOM_VIP_IP_ALLOW_LIST', true );

		$is_jp_private = Private_Sites::is_jetpack_private();

		$this->assertTrue( $is_jp_private );
	}

	public function test__is_jetpack_private_with_http_basic_auth() {
		Constant_Mocker::define( 'WPCOM_VIP_BASIC_AUTH', true );
		Constant_Mocker::define( 'WPCOM_VIP_IP_ALLOW_LIST', false );

		$is_jp_private = Private_Sites::is_jetpack_private();

		$this->assertTrue( $is_jp_private );
	}

	public function test__filter_jetpack_active_modules() {
		$modules = array(
			'json-api',
			'enhanced-distribution',
			'search',
			'something-else',
		);

		$private = Private_Sites::instance();

		$filtered = $private->filter_jetpack_active_modules( $modules );

		$this->assertEquals( array( 'something-else' ), $filtered );
	}

	public function test__filter_jetpack_get_available_modules() {
		$modules = array(
			'json-api'              => true,
			'enhanced-distribution' => true,
			'search'                => true,
			'something-else'        => true,
		);

		$private = Private_Sites::instance();

		$filtered = $private->filter_jetpack_get_available_modules( $modules );

		$expected = array(
			'search'         => true,
			'something-else' => true,
		);

		$this->assertEquals( $expected, $filtered );
	}

	public function test__filter_restrict_blog_public_keeps_2() {
		$private  = Private_Sites::instance();
		$filtered = $private->filter_restrict_blog_public( '2' );

		$this->assertEquals( '2', $filtered );
	}
	public function test__filter_restrict_blog_public_keeps_0() {
		$private  = Private_Sites::instance();
		$filtered = $private->filter_restrict_blog_public( '0' );

		$this->assertEquals( '0', $filtered );
	}
	public function test__filter_restrict_blog_public_changes_1() {
		$private  = Private_Sites::instance();
		$filtered = $private->filter_restrict_blog_public( '1' );

		$this->assertEquals( '-1', $filtered );
	}
}
