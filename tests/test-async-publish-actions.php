<?php
/**
 * Test async publish actions
 */

namespace Automattic\VIP\Async_Publish_Actions\Tests;

use Automattic\VIP\Async_Publish_Actions;
use WP_UnitTestCase;

/**
 * @group async-publish-actions
 */
class Async_Publish_Actions_Test extends WP_UnitTestCase {
	/**
	 * Prepare test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// make sure the schedule is clear.
		_set_cron_array( array() );

		// Add simple hook to trigger async events by default.
		add_action( 'async_transition_post_status', '__return_true' );
	}

	/**
	 * Clean up after our tests
	 */
	public function tearDown(): void {
		// make sure the schedule is clear.
		_set_cron_array( array() );

		remove_action( 'async_transition_post_status', '__return_true' );
		parent::tearDown();
	}

	/**
	 * Confirm that events aren't scheduled for non-published posts
	 */
	public function test_event_not_scheduled_for_draft() {
		$post = [
			'post_title'   => 'Tommy Tutone - 867-5309/Jenny',
			'post_content' => 'https://www.youtube.com/watch?v=6WTdTwcmxyo',
			'post_status'  => 'draft',
		];

		$pid = wp_insert_post( $post, true );

		$args = [
			'post_id'    => (int) $pid,
			'new_status' => 'draft',
			'old_status' => 'new',
		];

		$next = wp_next_scheduled( Async_Publish_Actions\ASYNC_TRANSITION_EVENT, $args );

		$this->assertFalse( $next );

	}

	/**
	 * Confirm that events aren't scheduled for non-published posts
	 */
	public function test_event_not_scheduled_for_auto_draft() {
		$post = [
			'post_title'   => 'Corey Hart - Sunglasses At Night',
			'post_content' => 'https://www.youtube.com/watch?v=X2LTL8KgKv8',
			'post_status'  => 'auto-draft',
		];

		$pid = wp_insert_post( $post, true );

		$args = [
			'post_id'    => (int) $pid,
			'new_status' => 'draft',
			'old_status' => 'new',
		];

		$next = wp_next_scheduled( Async_Publish_Actions\ASYNC_TRANSITION_EVENT, $args );

		$this->assertFalse( $next );

	}

	/**
	 * Confirm that an event is scheduled for a published post.
	 */
	public function test_event_is_scheduled_for_publish() {
		$post = [
			'post_title'   => 'Toto - Africa',
			'post_content' => 'https://www.youtube.com/watch?v=FTQbiNvZqaY',
			'post_status'  => 'draft',
		];

		$pid = wp_insert_post( $post, true );
		wp_publish_post( $pid );

		$args = [
			'post_id'    => (int) $pid,
			'new_status' => 'publish',
			'old_status' => 'draft',
		];

		$next = wp_next_scheduled( Async_Publish_Actions\ASYNC_TRANSITION_EVENT, $args );

		$this->assertIsInt( $next );
	}

	/**
	 * Confirm that an event is scheduled for a published post.
	 */
	public function test_event_is_not_scheduled_for_same_status() {
		$post = [
			'post_title'   => 'Starship - We Built This City',
			'post_content' => 'https://www.youtube.com/watch?v=K1b8AhIsSYQ',
			'post_status'  => 'publish',
		];

		$pid = wp_insert_post( $post, true );

		$post['ID']            = $pid;
		$post['post_content'] .= "\n\nhttps://www.gq.com/story/oral-history-we-built-this-city-worst-song-of-all-time";

		wp_update_post( $post );

		$args = [
			'post_id'    => (int) $pid,
			'new_status' => 'publish',
			'old_status' => 'publish',
		];

		$next = wp_next_scheduled( Async_Publish_Actions\ASYNC_TRANSITION_EVENT, $args );

		$this->assertFalse( $next );
	}

	/**
	 * Confirm that an event is scheduled for a published post.
	 */
	public function test_event_is_scheduled_for_unpublish() {
		$post = [
			'post_title'   => 'Michael Jackson - Thriller',
			'post_content' => 'https://www.youtube.com/watch?v=sOnqjkJTMaA',
			'post_status'  => 'publish',
		];

		$pid = wp_insert_post( $post, true );

		$post['ID']          = $pid;
		$post['post_status'] = 'draft';

		wp_update_post( $post );

		$args = [
			'post_id'    => (int) $pid,
			'new_status' => 'draft',
			'old_status' => 'publish',
		];

		$next = wp_next_scheduled( Async_Publish_Actions\ASYNC_TRANSITION_EVENT, $args );

		$this->assertIsInt( $next );
	}

	/**
	 * Confirm that events are scheduled for succeeding published posts.
	 */
	public function test_two_events_are_scheduled_for_succeeding_events() {
		$post = [
			'post_title'   => 'Whitney Houston - I Will Always Love You',
			'post_content' => 'https://www.youtube.com/watch?v=3JWTaaS7LdU',
			'post_status'  => 'publish',
		];

		$pid = wp_insert_post( $post, true );

		$second_post = [
			'post_title'   => 'Whitney Houston - I Wanna Dance With Somebody',
			'post_content' => 'https://www.youtube.com/watch?v=eH3giaIzONA',
			'post_status'  => 'publish',
		];

		$second_pid = wp_insert_post( $second_post, true );

		$args = [
			'post_id'    => (int) $pid,
			'new_status' => 'publish',
			'old_status' => 'new',
		];

		$scheduled_for_first = wp_next_scheduled( Async_Publish_Actions\ASYNC_TRANSITION_EVENT, $args );

		$args['post_id'] = (int) $second_pid;

		$scheduled_for_second = wp_next_scheduled( Async_Publish_Actions\ASYNC_TRANSITION_EVENT, $args );

		$this->assertIsInt( $scheduled_for_first, 'No event for first post' );
		$this->assertIsInt( $scheduled_for_second, 'No event for second post' );
	}

	/**
	 * Confirm no events are scheduled when there are no active hooks.
	 */
	public function test_events_are_not_scheduled_when_not_needed() {
		remove_action( 'async_transition_post_status', '__return_true' );

		$post = [
			'post_title'   => 'Blank Space - Taylor Swift',
			'post_content' => 'https://www.youtube.com/watch?v=e-ORhEE9VVg',
			'post_status'  => 'draft',
		];

		$pid = wp_insert_post( $post, true );
		wp_publish_post( $pid );

		$args = [
			'post_id'    => (int) $pid,
			'new_status' => 'publish',
			'old_status' => 'draft',
		];

		$next = wp_next_scheduled( Async_Publish_Actions\ASYNC_TRANSITION_EVENT, $args );

		$this->assertFalse( $next );

		add_action( 'async_transition_post_status', '__return_true' );
	}
}
