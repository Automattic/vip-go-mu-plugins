<?php
/**
 * Row actions tests.
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\UI;

use Parsely\Parsely;
use Parsely\Tests\Integration\TestCase;
use Parsely\UI\Row_Actions;

/**
 * Row actions tests.
 *
 * @since 2.6.0
 */
final class RowActionsTest extends TestCase {
	/**
	 * Internal variable.
	 *
	 * @var Row_Actions $row_actions Holds the Row_Actions object.
	 */
	private static $row_actions;

	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		parent::set_up();

		self::$row_actions = new Row_Actions( new Parsely() );
	}

	/**
	 * Check that run() method will not add hooks to add the row action links by default.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\UI\Row_Actions::__construct
	 * @covers \Parsely\UI\Row_Actions::run
	 * @group ui
	 */
	public function test_row_actions_class_will_not_add_row_actions_filter_when_enabling_filter_returns_false(): void {
		add_filter( 'wp_parsely_enable_row_action_links', '__return_false' );
		self::$row_actions->run();

		self::assertFalse( has_filter( 'post_row_actions', array( self::$row_actions, 'row_actions_add_parsely_link' ) ) );
		self::assertFalse( has_filter( 'page_row_actions', array( self::$row_actions, 'row_actions_add_parsely_link' ) ) );
	}

	/**
	 * Check that run() method will add hooks to add the row action links.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\UI\Row_Actions::__construct
	 * @covers \Parsely\UI\Row_Actions::run
	 * @group ui
	 */
	public function test_row_actions_class_will_add_row_actions_filter_when_enabling_filter_returns_true(): void {
		add_filter( 'wp_parsely_enable_row_action_links', '__return_true' );
		self::$row_actions->run();

		self::assertNotFalse( has_filter( 'post_row_actions', array( self::$row_actions, 'row_actions_add_parsely_link' ) ) );
		self::assertNotFalse( has_filter( 'page_row_actions', array( self::$row_actions, 'row_actions_add_parsely_link' ) ) );
	}

	/**
	 * Test if a row action is correctly not added when conditions are not good.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\UI\Row_Actions::__construct
	 * @covers \Parsely\UI\Row_Actions::row_actions_add_parsely_link
	 * @covers \Parsely\UI\Row_Actions::generate_aria_label_for_post
	 * @covers \Parsely\UI\Row_Actions::generate_link_to_parsely
	 * @covers \Parsely\Dashboard_Link::generate_url
	 * @uses \Parsely\Dashboard_Link::can_show_link
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::get_api_key
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_link_to_Parsely_is_not_added_to_row_actions_when_conditions_fail(): void {
		// Insert a single post and set as global post.
		// This post is a viewable type, with a trackable status (published).
		$post_id = self::factory()->post->create( array( 'post_title' => 'Foo1' ) );
		$post    = get_post( $post_id );

		// Existing actions is an array.
		$existing_actions = array();

		// Unset API key.
		self::set_options( array( 'apikey' => '' ) );

		// Guard clause catches, and original $actions is returned.
		$actions = self::$row_actions->row_actions_add_parsely_link( $existing_actions, $post );
		self::assertSame( $existing_actions, $actions );
	}

	/**
	 * Test if a row action is correctly added.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\UI\Row_Actions::__construct
	 * @covers \Parsely\UI\Row_Actions::row_actions_add_parsely_link
	 * @covers \Parsely\UI\Row_Actions::generate_aria_label_for_post
	 * @covers \Parsely\UI\Row_Actions::generate_link_to_parsely
	 * @covers \Parsely\Dashboard_Link::generate_url
	 * @uses \Parsely\Dashboard_Link::can_show_link
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::get_api_key
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_link_to_Parsely_is_added_to_row_actions(): void {
		// Insert a single post and set as global post.
		// This post is a viewable type, with a trackable status (published).
		$post_id = self::factory()->post->create( array( 'post_title' => 'Foo2' ) );
		$post    = get_post( $post_id );

		// Existing actions is an array.
		$existing_actions = array();

		// Set the API key.
		self::set_options( array( 'apikey' => 'somekey' ) );

		// All conditions for the guard clause have been met.
		$actions = self::$row_actions->row_actions_add_parsely_link( $existing_actions, $post );
		self::assertCount( 1, $actions );
		self::assertArrayHasKey( 'find_in_parsely', $actions );

		$url        = 'https://dash.parsely.com/somekey/find?url=http%3A%2F%2Fexample.org%2F%3Fp%3D' . $post_id . '&#038;utm_campaign=wp-admin-posts-list&#038;utm_source=wp-admin&#038;utm_medium=wp-parsely';
		$aria_label = 'Go to Parse.ly stats for &quot;Foo2&quot;';
		self::assertSame(
			'<a href="' . $url . '" aria-label="' . $aria_label . '">Parse.ly&nbsp;Stats</a>',
			$actions['find_in_parsely']
		);
	}
}
