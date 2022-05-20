<?php

// phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude

namespace Automattic\VIP\Tests;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_UnitTest_Factory;
use WP_UnitTestCase;

class Test_WPComVIP_Restrictions extends WP_UnitTestCase {
	private static $user_id;
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$user = get_user_by( 'login', 'wpcomvip' );
		if ( false === $user ) {
			self::$user_id = $factory->user->create([
				'user_login' => 'wpcomvip',
				'role'       => 'Administrator',
			]);
		} else {
			self::$user_id = $user->ID;
		}

		self::$post_id = $factory->post->create([
			'post_title'   => 'Test Post',
			'post_content' => 'Lorem ipsum sit amet',
			'post_author'  => 1,
		]);
	}

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( 1 );
	}

	public function test_insert_post(): void {
		$data = [
			'post_author'  => self::$user_id,
			'post_title'   => 'Test',
			'post_content' => 'Test',
		];

		$post_id = wp_insert_post( $data );
		self::assertNotInstanceOf( WP_Error::class, $post_id );

		$post = get_post( $post_id );
		self::assertInstanceOf( WP_Post::class, $post );

		self::assertEquals( 1, $post->post_author );
	}

	public function test_update_post(): void {
		$data = [
			'ID'          => self::$post_id,
			'post_author' => self::$user_id,
		];

		$result = wp_update_post( $data );
		self::assertNotInstanceOf( WP_Error::class, $result );

		$actual = get_post( self::$post_id );
		self::assertInstanceOf( WP_Post::class, $actual );

		self::assertEquals( 1, $actual->post_author );
	}

	public function test_insert_attachment(): void {
		$data = [
			'post_title'   => 'Test Attachment',
			'post_content' => 'No strings attached',
			'post_author'  => 1,
		];

		$att_id = wp_insert_attachment( $data, false, 0 );
		self::assertNotInstanceOf( WP_Error::class, $att_id );

		$post = get_post( $att_id );
		self::assertInstanceOf( WP_Post::class, $post );

		self::assertEquals( 1, $post->post_author );
	}

	/**
	 * @dataProvider data_wp_dropdown_users
	 */
	public function test_wp_dropdown_users( array $args ): void {
		$html = wp_dropdown_users( $args + [
			'show' => 'user_login',
			'echo' => false,
		] );

		self::assertStringNotContainsString( 'wpcomvip', $html );
	}

	public function data_wp_dropdown_users(): iterable {
		return [
			[
				[],
			],
			[
				[ 'exclude' => 1048576 ],
			],
			[
				[ 'exclude' => '1048576' ],
			],
			[
				[ 'exclude' => [ 1048576 ] ],
			],
		];
	}

	public function test_rest_api(): void {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = rest_do_request( $request );

		self::assertFalse( $response->is_error() );
		
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertCount( 1, $data );
		self::assertArrayHasKey( 0, $data );
		self::assertIsArray( $data[0] );
		self::assertArrayHasKey( 'id', $data[0] );
		self::assertEquals( 1, $data[0]['id'] );
	}

	public function test_insert_post_wpcomvip(): void {
		wp_set_current_user( self::$user_id );
		$data = [
			'post_author'  => self::$user_id,
			'post_title'   => 'Test',
			'post_content' => 'Test',
		];

		$post_id = wp_insert_post( $data );
		self::assertNotInstanceOf( WP_Error::class, $post_id );

		$post = get_post( $post_id );
		self::assertInstanceOf( WP_Post::class, $post );

		self::assertEquals( 0, $post->post_author );
	}
}
