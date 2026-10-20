<?php

namespace Automattic\VIP\Security;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

class Private_Sites_Test extends WP_UnitTestCase {
	public function data_is_jetpack_private(): array {
		return [
			'regular site'                        => [
				[
					'WPCOM_VIP_BASIC_AUTH'    => false,
					'WPCOM_VIP_IP_ALLOW_LIST' => false,
				],
				false,
			],
			'opted out despite basic auth'        => [
				[
					'VIP_JETPACK_IS_PRIVATE' => false,
					'WPCOM_VIP_BASIC_AUTH'   => true,
				],
				false,
			],
			'opted in without other restrictions' => [
				[
					'VIP_JETPACK_IS_PRIVATE'  => true,
					'WPCOM_VIP_BASIC_AUTH'    => false,
					'WPCOM_VIP_IP_ALLOW_LIST' => false,
				],
				true,
			],
			'IP restrictions'                     => [
				[
					'WPCOM_VIP_BASIC_AUTH'    => false,
					'WPCOM_VIP_IP_ALLOW_LIST' => true,
				],
				true,
			],
			'HTTP basic auth'                     => [
				[
					'WPCOM_VIP_BASIC_AUTH'    => true,
					'WPCOM_VIP_IP_ALLOW_LIST' => false,
				],
				true,
			],
		];
	}

	/**
	 * @dataProvider data_is_jetpack_private
	 */
	public function test__is_jetpack_private( array $constants, bool $expected ) {
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		$this->assertSame( $expected, Private_Sites::is_jetpack_private() );
	}

	public function data_filter_restrict_blog_public(): array {
		return [
			'discourages search engines' => [ '0', '-1' ],
			'public'                     => [ '1', '-1' ],
			'already restricted'         => [ '2', '2' ],
		];
	}

	/**
	 * @dataProvider data_filter_restrict_blog_public
	 */
	public function test__filter_restrict_blog_public( string $blog_public, string $expected ) {
		$this->assertSame( $expected, ( new Private_Sites() )->filter_restrict_blog_public( $blog_public ) );
	}

	/**
	 * Privacy initialization must affect real option, Jetpack and feed hooks.
	 */
	public function test__privacy_restrictions_through_registered_hooks() {
		update_option( 'blog_public', '1' );
		Constant_Mocker::define( 'VIP_JETPACK_IS_PRIVATE', false );
		$public = new Private_Sites();
		$public->init();
		$this->assertSame( '1', get_option( 'blog_public' ) );
		$this->assertSame( [ 'json-api', 'search' ], apply_filters( 'jetpack_active_modules', [ 'json-api', 'search' ] ) );
		$this->assertFalse( has_action( 'do_feed_rss2', [ $public, 'action_do_feed' ] ) );

		Constant_Mocker::clear();
		Constant_Mocker::define( 'VIP_JETPACK_IS_PRIVATE', true );
		$private = new Private_Sites();
		$private->init();
		$this->assertSame( '-1', get_option( 'blog_public' ) );
		$this->assertSame( [ 'other' ], apply_filters( 'jetpack_active_modules', [ 'json-api', 'enhanced-distribution', 'search', 'other' ] ) );
		$available_modules = apply_filters( 'jetpack_get_available_modules', [
			'json-api'              => true,
			'enhanced-distribution' => true,
			'search'                => true,
			'other'                 => true,
		] );
		$this->assertSame( [
			'search' => true,
			'other'  => true,
		], $available_modules );
		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Controlled request fixture.
		$original_agent             = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$_SERVER['HTTP_USER_AGENT'] = Private_Sites::FEEDBOT_USER_AGENT;
		try {
			do_action( 'do_feed_rss2', false );
			$this->fail( 'Expected private feed request to be blocked.' );
		} catch ( \WPDieException $error ) {
			$this->assertSame( 'Feeds are disabled in Jetpack Private Mode', $error->getMessage() );
		} finally {
			if ( null === $original_agent ) {
				unset( $_SERVER['HTTP_USER_AGENT'] );
			} else {
				$_SERVER['HTTP_USER_AGENT'] = $original_agent;
			}
		}
		// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
}
