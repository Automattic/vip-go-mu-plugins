<?php

namespace Automattic\WP\Cron_Control\Tests;

use Automattic\WP\Cron_Control;

class Internal_Events_Tests extends \WP_UnitTestCase {
	function setUp(): void {
		parent::setUp();
		Utils::clear_cron_table();
	}

	function tearDown(): void {
		Utils::clear_cron_table();
		parent::tearDown();
	}

	function test_internal_events_are_scheduled() {
		Cron_Control\Internal_Events::instance()->schedule_internal_events();
		$scheduled_events = Cron_Control\Events::query( [ 'limit' => 100 ] );

		$expected_count = 4; // Number of events created by the Internal_Events::prepare_internal_events() method, which is private.
		$expected_count += count( \CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS );
		$this->assertEquals( count( $scheduled_events ), $expected_count, 'Correct number of Internal Events registered' );

		foreach ( $scheduled_events as $scheduled_event ) {
			$this->assertTrue( $scheduled_event->is_internal(), sprintf( 'Action `%s` is not an Internal Event', $scheduled_event->get_action() ) );
		}
	}

	function test_migrate_legacy_cron_events() {
		global $wpdb;

		// Ensure we start with an empty cron option.
		delete_option( 'cron' );

		// Create one saved event and two unsaved events.
		$existing_event = Utils::create_test_event( [ 'timestamp' => time(), 'action' => 'existing_event' ] );
		$legacy_event = Utils::create_unsaved_event( [ 'timestamp' => time() + 500, 'action' => 'legacy_event' ] );
		$legacy_recurring_event = Utils::create_unsaved_event( [ 'timestamp' => time() + 600, 'action' => 'legacy_recurring_event', 'schedule' => 'hourly', 'interval' => \HOUR_IN_SECONDS ] );

		$cron_array = Cron_Control\Events::format_events_for_wp( [ $existing_event, $legacy_event, $legacy_recurring_event ] );
		$cron_array['version'] = 2;

		// Put the legacy event directly into the cron option, avoiding our special filtering. @codingStandardsIgnoreLine
		$result = $wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)", 'cron', serialize( $cron_array ), 'yes' ) );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'cron', 'options' );

		// Run the migration.
		Cron_Control\Internal_Events::instance()->clean_legacy_data();

		// Should now have all three events registered.
		$registered_events = Cron_Control\Events::query( [ 'limit' => 100 ] );
		$this->assertEquals( 3, count( $registered_events ), 'correct number of registered events' );
		$this->assertEquals( $registered_events[0]->get_action(), $existing_event->get_action(), 'existing event stayed registered' );
		$this->assertEquals( $registered_events[1]->get_action(), $legacy_event->get_action(), 'legacy event was registered' );
		$this->assertEquals( $registered_events[2]->get_schedule(), $legacy_recurring_event->get_schedule(), 'legacy recurring event was registered' );

		$cron_row = $wpdb->get_row( "SELECT * FROM $wpdb->options WHERE option_name = 'cron'" );
		$this->assertNull( $cron_row, 'cron option was deleted' );
	}

	function test_prune_duplicate_events() {
		// We don't prune single events, even if duplicates.
		$original_single_event  = Utils::create_test_event( [ 'timestamp' => time(), 'action' => 'single_event', 'args' => [ 'same' ] ] );
		$duplicate_single_event = Utils::create_test_event( [ 'timestamp' => time() + 100, 'action' => 'single_event', 'args' => [ 'same' ] ] );
		$unique_single_event    = Utils::create_test_event( [ 'timestamp' => time() + 200, 'action' => 'single_event', 'args' => [ 'unique' ] ] );

		// We do prune duplicate recurring events.
		$original_recurring_event   = Utils::create_test_event( [ 'timestamp' => time() + 500, 'action' => 'recurring_event', 'schedule' => 'hourly', 'interval' => \HOUR_IN_SECONDS ] );
		$duplicate_recurring_event  = Utils::create_test_event( [ 'timestamp' => time() + 100, 'action' => 'recurring_event', 'schedule' => 'hourly', 'interval' => \HOUR_IN_SECONDS ] );
		$duplicate_recurring_event2 = Utils::create_test_event( [ 'timestamp' => time() + 200, 'action' => 'recurring_event', 'schedule' => 'hourly', 'interval' => \HOUR_IN_SECONDS ] );
		$unique_recurring_event     = Utils::create_test_event( [ 'timestamp' => time() + 100, 'action' => 'recurring_event', 'schedule' => 'hourly', 'interval' => \HOUR_IN_SECONDS, 'args' => [ 'unique' ] ] );

		// This prevent events starting with `wp_` from being scheduled, like wp_version_check,
		// wp_update_plugins or wp_update_themes to avoid affecting the count assertions.
		$prevent_wp_cron_events = function ( $event ) {
			if ( str_starts_with( $event->hook, 'wp_' ) ) {
				return false;
			}
			return $event;
		};

		// Filter to block any WordPress core cron events so the test events are isolated.
		add_filter( 'schedule_event', $prevent_wp_cron_events );

		// Run the pruning.
		Cron_Control\Internal_Events::instance()->clean_legacy_data();

		// Remove the filter after the pruning calls.
		remove_filter( 'schedule_event', $prevent_wp_cron_events );

		// Should have 5 events left, and the oldest IDs should have been kept..
		$remaining_events = Cron_Control\Events::query( [ 'limit' => 100, 'orderby' => 'ID', 'order' => 'ASC' ] );
		$this->assertCount( 5, $remaining_events, 'correct number of registered events left after pruning' );
		$this->assertEquals( $remaining_events[0]->get_id(), $original_single_event->get_id(), 'original single event was kept' );
		$this->assertEquals( $remaining_events[1]->get_id(), $duplicate_single_event->get_id(), 'duplicate single event was also kept' );
		$this->assertEquals( $remaining_events[2]->get_id(), $unique_single_event->get_id(), 'unique single event was kept' );
		$this->assertEquals( $remaining_events[3]->get_id(), $original_recurring_event->get_id(), 'original recurring event was kept' );
		$this->assertEquals( $remaining_events[4]->get_id(), $unique_recurring_event->get_id(), 'unique recurring event was kept' );

		// The two duplicates should be marked as completed now.
		$duplicate_recurring_1 = Cron_Control\Event::get( $duplicate_recurring_event->get_id() );
		$duplicate_recurring_2 = Cron_Control\Event::get( $duplicate_recurring_event2->get_id() );
		$this->assertEquals( $duplicate_recurring_1->get_status(), Cron_Control\Events_Store::STATUS_COMPLETED, 'duplicate recurring event 1 was marked as completed' );
		$this->assertEquals( $duplicate_recurring_2->get_status(), Cron_Control\Events_Store::STATUS_COMPLETED, 'duplicate recurring event 2 was marked as completed' );
	}

	function test_force_publish_missed_schedules() {
		// Define the filter callback to override post status.
		$future_insert_filter = function ( $data ) {
			if ( 'publish' === $data['post_status'] ) {
				$data['post_status'] = 'future'; // Ensure it remains future even if the date is in the past.
			}
			return $data;
		};

		// Add the filter to ensure 'future' posts with past dates are not auto-published.
		add_filter( 'wp_insert_post_data', $future_insert_filter );

		// Create two posts with a 'future' status.
		$this->factory()->post->create(
			array(
				'post_title'  => 'Future post that should be published',
				'post_status' => 'future',
				'post_type'   => 'post',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() - 1000 ),
			)
		);

		$this->factory()->post->create(
			array(
				'post_title'  => 'Future post that should not be published',
				'post_status' => 'future',
				'post_type'   => 'post',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + 1000 ),
			)
		);

		// Remove the filter after creating the test posts.
		remove_filter( 'wp_insert_post_data', $future_insert_filter );

		// Count posts with 'future' status before running the method.
		$future_posts_before = get_posts(
			array(
				'post_status' => 'future',
				'numberposts' => -1,
			)
		);

		$this->assertCount( 2, $future_posts_before, 'Two posts should be scheduled initially.' );

		// Run the function to publish missed schedules.
		Cron_Control\Internal_Events::instance()->force_publish_missed_schedules();

		// Query posts again after running the function.
		$future_posts_after = get_posts(
			array(
				'post_status' => 'future',
				'post_type'   => 'post',
				'numberposts' => -1,
			)
		);

		$published_posts = get_posts(
			array(
				'post_status' => 'publish',
				'post_type'   => 'post',
				'numberposts' => -1,
			)
		);

		// Assert counts after the function runs.
		$this->assertCount( 1, $future_posts_after, 'One post should still be scheduled.' );
		$this->assertCount( 1, $published_posts, 'One post should be published.' );
	}

	public function test_confirm_scheduled_posts() {
		// Create posts with 'future' status.
		$future_posts = array(
			$this->factory()->post->create(
				array(
					'post_title'  => '1 hour in the future',
					'post_status' => 'future',
					'post_type'   => 'post',
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
				)
			),
			$this->factory()->post->create(
				array(
					'post_title'  => '2 hours in the future',
					'post_status' => 'future',
					'post_type'   => 'post',
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 hours' ) ),
				)
			),
			$this->factory()->post->create(
				array(
					'post_title'  => '3 hours in the future',
					'post_status' => 'future',
					'post_type'   => 'post',
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+3 hours' ) ),
				)
			),
		);

		// Clear existing cron events to isolate the test.
		Utils::clear_cron_table();

		// Query all cron events to confirm none exist.
		$events = Cron_Control\Events::query();
		$this->assertEmpty( $events, 'No scheduled events should exist initially.' );

		// Call the method to confirm scheduled posts.
		Cron_Control\Internal_Events::instance()->confirm_scheduled_posts();

		// Verify that cron jobs are scheduled for each future post.
		foreach ( $future_posts as $future_post_id ) {
			$timestamp = wp_next_scheduled( 'publish_future_post', array( $future_post_id ) );
			$this->assertNotFalse( $timestamp, "Cron job should be scheduled for post ID: $future_post_id." );
		}

		// Reschedule one post with a different timestamp and call the method again.
		$future_post_gmt_time = strtotime( get_gmt_from_date( get_post( $future_posts[0] )->post_date ) . ' GMT' );
		wp_clear_scheduled_hook( 'publish_future_post', array( $future_posts[0] ) );
		wp_schedule_single_event( $future_post_gmt_time - 3600, 'publish_future_post', array( $future_posts[0] ) ); // Schedule 1 hour earlier.

		Cron_Control\Internal_Events::instance()->confirm_scheduled_posts();

		// Verify the post's cron job has been rescheduled to the correct timestamp.
		$rescheduled_timestamp = wp_next_scheduled( 'publish_future_post', array( $future_posts[0] ) );
		$this->assertEquals( $future_post_gmt_time, $rescheduled_timestamp, 'Cron job for post 1 should be rescheduled to the correct timestamp.' );
	}
}
