<?php

namespace Automattic\VIP\Performance;
use WP_UnitTestCase;

class async_publish_actions_Test extends WP_UnitTestCase {
	/**
	 * Prepare test environment
	 */
	function setUp() {
		parent::setUp();

		// make sure the schedule is clear.
		_set_cron_array( array() );
	}

	/**
	 * Clean up after our tests
	 */
	function tearDown() {
		// make sure the schedule is clear.
		_set_cron_array( array() );

		parent::tearDown();
	}

	/**
	 * Confirm that events aren't scheduled for non-published posts
	 */
	function test_event_not_scheduled_for_draft() {
		$post = [
			'post_title'   => 'Tommy Tutone - 867-5309/Jenny',
			'post_content' => 'https://www.youtube.com/watch?v=6WTdTwcmxyo',
			'post_status'  => 'draft',
		];

		$pid = wp_insert_post( $post, true );

		$args = [
			'post_id' => (int) $pid,
			'new_status' => 'draft',
			'old_status' => 'new',
		];

		$next_transition = wp_next_scheduled( ASYNC_TRANSITION_EVENT, $args );

		// No async event for a draft post.
		$this->assertFalse( $next_transition );

	}

	/**
	 * Confirm that an event is scheduled for a published post.
	 */
	function test_event_is_scheduled_for_publish() {
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

		$next_transition = wp_next_scheduled( ASYNC_TRANSITION_EVENT, $args );

		// Event scheduled after publish.
		$this->assertInternalType( 'int',  $next_transition );
	}
}
