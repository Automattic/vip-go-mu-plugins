<?php
/**
 * Row actions tests.
 *
 * @package Parsely
 */

namespace Parsely\Tests\UI;

use Parsely;
use Parsely\Tests\TestCase;
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
	 * @var string $row_actions Holds the Row_Actions object.
	 */
	private static $row_actions;

	/**
	 * The setUp run before each test
	 */
	public function setUp() {
		parent::setUp();

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
	public function test_row_actions_class_will_not_add_row_actions_filter_when_enabling_filter_returns_false() {
		// wp_parsely_enable_row_action_links is false by default.
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
	public function test_row_actions_class_will_add_row_actions_filter_when_enabling_filter_returns_true() {
		add_filter( 'wp_parsely_enable_row_action_links', '__return_true' );
		self::$row_actions->run();

		self::assertNotFalse( has_filter( 'post_row_actions', array( self::$row_actions, 'row_actions_add_parsely_link' ) ) );
		self::assertNotFalse( has_filter( 'page_row_actions', array( self::$row_actions, 'row_actions_add_parsely_link' ) ) );
	}

	/**
	 * Test if logic for showing Parse.ly row action accounts for actions not being an array.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\UI\Row_Actions::cannot_show_parsely_link
	 * @uses \Parsely\UI\Row_Actions::__construct
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::post_has_viewable_type
	 * @uses \Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_can_correctly_determine_if_Parsely_link_can_be_shown_when_actions_are_an_array_or_not() {
		$cannot_show_parsely_link = self::getMethod( 'cannot_show_parsely_link', Row_Actions::class );

		$published_post = self::factory()->post->create_and_get();
		self::set_options( array( 'apikey' => 'somekey' ) );

		// Test if $actions are not an array.
		self::assertTrue( $cannot_show_parsely_link->invokeArgs( self::$row_actions, array( 'not_on_array', $published_post ) ) );
		self::assertFalse( $cannot_show_parsely_link->invokeArgs( self::$row_actions, array( array(), $published_post ) ) );
	}

	/**
	 * Test if logic for showing Parse.ly row action accounts for post having trackable status.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\UI\Row_Actions::cannot_show_parsely_link
	 * @uses \Parsely\UI\Row_Actions::__construct
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::post_has_viewable_type
	 * @uses \Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_can_correctly_determine_if_Parsely_link_can_be_shown_when_post_has_trackable_status_or_not() {
		$cannot_show_parsely_link = self::getMethod( 'cannot_show_parsely_link', Row_Actions::class );

		$draft_post     = self::factory()->post->create_and_get( array( 'post_status' => 'draft' ) );
		$published_post = self::factory()->post->create_and_get();

		$actions = array();
		self::set_options( array( 'apikey' => 'somekey' ) );

		// Test if post does not have trackable status - only published posts are tracked by default.
		self::assertTrue( $cannot_show_parsely_link->invokeArgs( self::$row_actions, array( $actions, $draft_post ) ) );
		self::assertFalse( $cannot_show_parsely_link->invokeArgs( self::$row_actions, array( $actions, $published_post ) ) );
	}

	/**
	 * Test if logic for showing Parse.ly row action accounts for post not having a viewable type.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\UI\Row_Actions::cannot_show_parsely_link
	 * @uses \Parsely\UI\Row_Actions::__construct
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::post_has_viewable_type
	 * @uses \Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_can_correctly_determine_if_Parsely_link_can_be_shown_when_post_is_viewable_or_not() {
		$cannot_show_parsely_link = self::getMethod( 'cannot_show_parsely_link', Row_Actions::class );

		$non_publicly_queryable_post = self::factory()->post->create_and_get( array( 'post_type' => 'parsely_tests_pt' ) );
		$published_post              = self::factory()->post->create_and_get();

		$actions = array();
		self::set_options( array( 'apikey' => 'somekey' ) );

		// Test if post is not viewable status.
		self::assertTrue( $cannot_show_parsely_link->invokeArgs( self::$row_actions, array( $actions, $non_publicly_queryable_post ) ) );
		self::assertFalse( $cannot_show_parsely_link->invokeArgs( self::$row_actions, array( $actions, $published_post ) ) );
	}

	/**
	 * Test if logic for showing Parse.ly row action accounts for API key option being saved or not.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\UI\Row_Actions::cannot_show_parsely_link
	 * @uses \Parsely\UI\Row_Actions::__construct
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::post_has_viewable_type
	 * @uses \Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_can_correctly_determine_if_Parsely_link_can_be_shown_when_api_key_is_set_or_missing() {
		$cannot_show_parsely_link = self::getMethod( 'cannot_show_parsely_link', Row_Actions::class );

		$published_post = self::factory()->post->create_and_get();

		$actions = array();

		// Test if API key is not set.
		self::set_options( array( 'apikey' => '' ) );
		self::assertTrue( $cannot_show_parsely_link->invokeArgs( self::$row_actions, array( $actions, $published_post ) ) );

		// Test with API key set.
		self::set_options( array( 'apikey' => 'somekey' ) );
		self::assertFalse( $cannot_show_parsely_link->invokeArgs( self::$row_actions, array( $actions, $published_post ) ) );
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
	 * @covers \Parsely\UI\Row_Actions::generate_url
	 * @uses \Parsely\UI\Row_Actions::cannot_show_parsely_link
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::get_api_key
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::post_has_viewable_type
	 * @uses \Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_link_to_Parsely_is_not_added_to_row_actions_when_conditions_fail() {
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
	 * @covers \Parsely\UI\Row_Actions::generate_url
	 * @uses \Parsely\UI\Row_Actions::cannot_show_parsely_link
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::get_api_key
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::post_has_viewable_type
	 * @uses \Parsely::update_metadata_endpoint
	 * @group ui
	 */
	public function test_link_to_Parsely_is_added_to_row_actions() {
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

		$url        = 'https://dash.parsely.com/somekey/find?url=http%3A%2F%2Fexample.org%2F%3Fp%3D' . $post_id . '&#038;utm_campaign=wp-admin-posts-list&#038;utm_medium=wp-parsely&#038;utm_source=wp-admin';
		$aria_label = 'Go to Parse.ly stats for &quot;Foo2&quot;';
		self::assertSame(
			'<a href="' . $url . '" aria-label="' . $aria_label . '">Parse.ly&nbsp;Stats</a>',
			$actions['find_in_parsely']
		);
	}
}
