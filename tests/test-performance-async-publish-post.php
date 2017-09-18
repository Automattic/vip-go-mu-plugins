<?php

namespace Automattic\VIP\Performance;

class async_publish_post_Test extends \WP_UnitTestCase {
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
	 * Test that offload is scheduled when post is published
	 */
	function test_schedule_offload() {
		$post = [
			'post_title'   => 'Tommy Tutone - 867-5309/Jenny',
			'post_content' => 'https://www.youtube.com/watch?v=6WTdTwcmxyo',
			'post_status'  => 'draft',
		];

		$pid = wp_insert_post( $post, true );

		// Insert succeeded.
		$this->assertFalse( is_wp_error( $pid ) );

		$args = [ 'post_id' => (int) $pid ];
		$next = wp_next_scheduled( ASYNC_PUBLISH_EVENT, $args );

		// No async event for a draft post.
		$this->assertFalse( $next );

		wp_publish_post( $pid );

		$args = [ 'post_id' => (int) $pid ];
		$next = wp_next_scheduled( ASYNC_PUBLISH_EVENT, $args );

		// Event scheduled after publish.
		$this->assertTrue( is_int( $next ) );
	}
}
