<?php
/**
 * Tests of the Dashboard Link class
 *
 * @package Parsely\Tests\Unit
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration;

use Parsely\Parsely;
use Parsely\Dashboard_Link;

/**
 * Test the functions on the utilities class.
 */
final class DashboardLinkTest extends TestCase {
	/**
	 * Internal Parsely variable
	 *
	 * @var Parsely $parsely Holds the Parsely object
	 */
	private static $parsely;

	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		parent::set_up();

		self::$parsely = new Parsely();
	}

	/**
	 * Test if Parse.ly Dash URL can be generated for a post.
	 *
	 * @covers \Parsely\Dashboard_Link::generate_url
	 */
	public function test_generate_parsely_post_url(): void {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );
		$apikey  = 'demo-api-key';

		$expected = 'https://dash.parsely.com/demo-api-key/find?url=http%3A%2F%2Fexample.org%2F%3Fp%3D' . $post_id . '&utm_campaign=wp-admin-posts-list&utm_source=wp-admin&utm_medium=wp-parsely';
		$actual   = Dashboard_Link::generate_url( $post, $apikey, 'wp-admin-posts-list', 'wp-admin' );

		self::assertSame( $expected, $actual );
	}

	/**
	 * Test generating a URL for a post that doesn't exist.
	 *
	 * @since 3.1.2
	 *
	 * @covers \Parsely\Dashboard_Link::generate_url
	 */
	public function test_generate_invalid_post_url(): void {
		add_filter( 'post_link', '__return_false' );

		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );
		$apikey  = 'demo-api-key';

		$expected = '';
		$actual   = Dashboard_Link::generate_url( $post, $apikey, 'wp-admin-posts-list', 'wp-admin' );

		self::assertSame( $expected, $actual );
	}

	/**
	 * Test if logic for showing Parse.ly row action accounts for actions not being an array.
	 *
	 * @since 2.6.0
	 * @since 3.1.0 Moved to `DashboardLinkTest.php`
	 *
	 * @covers \Parsely\Dashboard_Link::can_show_link
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_can_correctly_determine_if_Parsely_link_can_be_shown(): void {
		$published_post = self::factory()->post->create_and_get();
		self::set_options( array( 'apikey' => 'somekey' ) );

		self::assertTrue( Dashboard_Link::can_show_link( $published_post, self::$parsely ) );
	}

	/**
	 * Test if logic for showing Parse.ly row action accounts for post having trackable status.
	 *
	 * @since 2.6.0
	 * @since 3.1.0 Moved to `DashboardLinkTest.php`
	 *
	 * @covers \Parsely\Dashboard_Link::can_show_link
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_can_correctly_determine_if_Parsely_link_can_be_shown_when_post_has_not_trackable_status(): void {
		$draft_post = self::factory()->post->create_and_get( array( 'post_status' => 'draft' ) );
		self::set_options( array( 'apikey' => 'somekey' ) );

		// Test if post does not have trackable status - only published posts are tracked by default.
		self::assertFalse( Dashboard_Link::can_show_link( $draft_post, self::$parsely ) );
	}

	/**
	 * Test if logic for showing Parse.ly row action accounts for post not having a viewable type.
	 *
	 * @since 2.6.0
	 * @since 3.1.0 Moved to `DashboardLinkTest.php`
	 *
	 * @covers \Parsely\Dashboard_Link::can_show_link
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_can_correctly_determine_if_Parsely_link_can_be_shown_when_post_is_viewable(): void {
		$non_publicly_queryable_post = self::factory()->post->create_and_get( array( 'post_type' => 'parsely_tests_pt' ) );
		self::set_options( array( 'apikey' => 'somekey' ) );

		// Test if post is not viewable status.
		self::assertFalse( Dashboard_Link::can_show_link( $non_publicly_queryable_post, self::$parsely ) );
	}

	/**
	 * Test if logic for showing Parse.ly row action accounts for API key option being saved or not.
	 *
	 * @since 2.6.0
	 * @since 3.1.0 Moved to `DashboardLinkTest.php`
	 *
	 * @covers \Parsely\Dashboard_Link::can_show_link
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_can_correctly_determine_if_Parsely_link_can_be_shown_when_api_key_is_set_or_missing(): void {
		$published_post = self::factory()->post->create_and_get();

		// Test if API key is not set.
		self::set_options( array( 'apikey' => '' ) );
		self::assertFalse( Dashboard_Link::can_show_link( $published_post, self::$parsely ) );

		// Test with API key set.
		self::set_options( array( 'apikey' => 'somekey' ) );
		self::assertTrue( Dashboard_Link::can_show_link( $published_post, self::$parsely ) );
	}
}
