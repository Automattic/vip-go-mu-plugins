<?php
/**
 * Test the WP adapter, the filters where we implement our custom data store.
 */

namespace Automattic\WP\Cron_Control\Tests;

use Automattic\WP\Cron_Control\Events_Store;
use Automattic\WP\Cron_Control\Events;
use Automattic\WP\Cron_Control\Event;
use Automattic\WP\Cron_Control;

class WP_Adapter_Tests extends \WP_UnitTestCase {
	function setUp(): void {
		parent::setUp();
		Utils::clear_cron_table();
	}

	function tearDown(): void {
		Utils::clear_cron_table();
		parent::tearDown();
	}

	function test_pre_schedule_event() {
		// Test single/one-time event.
		$this->run_schedule_test( (object) [
			'timestamp' => time() + 500,
			'hook'      => 'test_pre_schedule_event_single',
			'schedule'  => false,
			'args'      => [],
		] );

		// Test recurring events.
		$this->run_schedule_test( (object) [
			'timestamp' => time() + 500,
			'hook'      => 'test_pre_schedule_event_recurring',
			'args'      => [],
			'schedule'  => 'hourly',
			'interval'  => HOUR_IN_SECONDS,
		] );
	}

	private function run_schedule_test( $event_args ) {
		// Make sure the return value is what core expects
		$result = Cron_Control\pre_schedule_event( null, $event_args );
		$this->assertTrue( $result, 'scheduling was successful' );

		// Ensure the event made it's way to the DB.
		$event = Event::find( [ 'action' => $event_args->hook ] );
		$this->assertEquals( $event_args->timestamp, $event->get_timestamp() );

		// Try to register again, and we get a duplicate event error.
		$fail_result = Cron_Control\pre_schedule_event( null, $event_args );
		$this->assertEquals( 'cron-control:wp:duplicate-event', $fail_result->get_error_code() );
	}

	function test_pre_reschedule_event() {
		$event_args = (object) [
			'timestamp' => time() - 500, // Past "due" keeps the calculation simple
			'hook'      => 'test_pre_reschedule_event',
			'args'      => [],
			'schedule'  => 'hourly',
			'interval'  => HOUR_IN_SECONDS,
		];

		// Schedule the event for the first time.
		Cron_Control\pre_schedule_event( null, $event_args );

		// Make sure the successful return value is what core expects
		$result = Cron_Control\pre_reschedule_event( null, $event_args );
		$this->assertTrue( $result, 'rescheduling was successful' );

		// Ensure the event update made it's way to the DB.
		$event = Event::find( [ 'action' => $event_args->hook ] );
		$this->assertEquals( $event_args->timestamp + HOUR_IN_SECONDS, $event->get_timestamp() );

		// Should fail if it can't find the event.
		$event_args->action = 'test_pre_reschedule_event_missing';
		$fail_result = Cron_Control\pre_reschedule_event( null, $event_args );
		$this->assertEquals( 'cron-control:wp:event-not-found', $fail_result->get_error_code() );
	}

	function test_pre_unschedule_event() {
		$event = (object) [
			'timestamp' => time(),
			'hook'      => 'test_pre_unschedule_event',
			'args'      => [],
			'schedule'  => false,
		];

		// Schedule the event for the first time.
		Cron_Control\pre_schedule_event( null, $event );

		// Make sure the successful return value is what core expects
		$result = Cron_Control\pre_unschedule_event( null, $event->timestamp, $event->hook, $event->args );
		$this->assertTrue( $result, 'unscheduling was successful' );

		// Ensure the event update made it's way to the DB.
		$object = Event::find( [ 'action' => $event->hook, 'status' => Events_Store::STATUS_COMPLETED ] );
		$this->assertEquals( Events_Store::STATUS_COMPLETED, $object->get_status() );

		// Now that the event is "gone", it should fail to unschedule again.
		$fail_result = Cron_Control\pre_unschedule_event( null, $event->timestamp, $event->hook, $event->args );
		$this->assertEquals( 'cron-control:wp:event-not-found', $fail_result->get_error_code() );
	}

	function test_pre_clear_scheduled_hook() {
		$event_details = [
			'hook'      => 'test_pre_clear_scheduled_hook',
			'schedule'  => false,
			'timestamp' => time(),
		];

		$event_one = array_merge( $event_details, [ 'args' => [ 'default args' ] ] );

		// Same args (instance), but very different timestamps so they both register.
		$event_two = array_merge( $event_details, [ 'args' => [ 'other args' ], 'timestamp' => time() + 500 ] );
		$event_three  = array_merge( $event_details, [ 'args' => [ 'other args' ], 'timestamp' => time() + 1500 ] );

		// Schedule the events for the first time.
		Cron_Control\pre_schedule_event( null, (object) $event_one );
		Cron_Control\pre_schedule_event( null, (object) $event_two );
		Cron_Control\pre_schedule_event( null, (object) $event_three );

		// Unschedule the two similar events.
		$result = Cron_Control\pre_clear_scheduled_hook( null, 'test_pre_clear_scheduled_hook', [ 'other args' ] );
		$this->assertEquals( 2, $result, 'clearing was successful' );

		// Ensure the event update made it's way to the DB.
		$object = Event::find( [
			'action' => 'test_pre_clear_scheduled_hook',
			'timestamp' => $event_two['timestamp'],
			'status' => Events_Store::STATUS_COMPLETED,
		] );
		$this->assertEquals( Events_Store::STATUS_COMPLETED, $object->get_status() );

		// Empty args array should clear no events since we have not registered any w/ empty args.
		$result = Cron_Control\pre_clear_scheduled_hook( null, 'test_pre_clear_scheduled_hook', [] );
		$this->assertEquals( 0, $result, 'no events were cleared' );
	}

	function test_pre_unschedule_hook() {
		$event_details = [
			'hook'      => 'test_pre_unschedule_hook',
			'schedule'  => false,
			'timestamp' => time(),
		];

		$event_one   = array_merge( $event_details, [ 'args' => [] ] );
		$event_two   = array_merge( $event_details, [ 'args' => [ 'default args' ] ] );
		$event_three = array_merge( $event_details, [ 'args' => [ 'unique args' ] ] );

		// Schedule the events.
		Cron_Control\pre_schedule_event( null, (object) $event_one );
		Cron_Control\pre_schedule_event( null, (object) $event_two );
		Cron_Control\pre_schedule_event( null, (object) $event_three );

		// Should clear them all, even though args are different.
		$result = Cron_Control\pre_unschedule_hook( null, 'test_pre_unschedule_hook' );
		$this->assertEquals( 3, $result, 'clearing was successful' );

		// Ensure the event update made it's way to the DB.
		$object = Event::find( [
			'action' => 'test_pre_unschedule_hook',
			'status' => Events_Store::STATUS_COMPLETED,
		] );
		$this->assertEquals( Events_Store::STATUS_COMPLETED, $object->get_status() );

		// Nothing left to clear, returns 0 as WP expects.
		$result = Cron_Control\pre_unschedule_hook( null, 'test_pre_unschedule_hook' );
		$this->assertEquals( 0, $result, 'nothing was cleared' );
	}

	function test_pre_get_scheduled_event() {
		$event_details = (object) [
			'hook'      => 'test_pre_get_scheduled_event',
			'args'      => [],
			'timestamp' => time() + 2500,
		];

		// Event does not exist yet.
		$result = Cron_Control\pre_get_scheduled_event( null, $event_details->hook, $event_details->args, $event_details->timestamp );
		$this->assertFalse( $result, 'event does not exist, returns false as WP expected' );

		// Now it exists.
		Cron_Control\pre_schedule_event( null, $event_details );
		$result = Cron_Control\pre_get_scheduled_event( null, $event_details->hook, $event_details->args, $event_details->timestamp );
		$this->assertEquals( $result->hook, $event_details->hook );

		// Make a similar event but w/ an earlier timestamp.
		$event_details->timestamp = time() + 10;
		Cron_Control\pre_schedule_event( null, $event_details );

		// If we fetch w/o timestamp, it gets the (new) next occurring event.
		$result = Cron_Control\pre_get_scheduled_event( null, $event_details->hook, $event_details->args, null );
		$this->assertEquals( $result->timestamp, $event_details->timestamp, 'fetched the next occurring event' );
	}

	function test_pre_get_ready_cron_jobs() {
		$ready_jobs = Cron_Control\pre_get_ready_cron_jobs( null );
		$this->assertTrue( [] === $ready_jobs, 'returns no ready jobs' );

		// Schedule some events, two are due, two are in the future (one is recurring).
		$test_events = $this->create_test_events();

		// Should give us the two due jobs.
		$ready_jobs = Cron_Control\pre_get_ready_cron_jobs( null );
		$this->assertEquals( 2, count( $ready_jobs ), 'returns two ready jobs' );

		// Make it flat for easier testing (this flattening is tested individually elsewhere)
		$flat_jobs = array_values( Events::flatten_wp_events_array( $ready_jobs ) );
		$this->assert_event_matches_expected_args( $flat_jobs[0], $test_events['due_one'] );
		$this->assert_event_matches_expected_args( $flat_jobs[1], $test_events['due_two'] );
	}

	public function test_pre_get_cron_option() {
		$cron_option = Cron_Control\pre_get_cron_option( false );
		$this->assertEquals( [ 'version' => 2 ], $cron_option, 'cron option is effectively empty' );

		// Schedule some events.
		$test_events = $this->create_test_events();

		// Make sure we get the right response.
		$cron_option = Cron_Control\pre_get_cron_option( false );
		$this->assertEquals( 4 + 1, count( $cron_option ), 'cron option returned 4 events + version arg' );

		// Make it flat for easier testing (this flattening is tested individually elsewhere)
		$flat_option = array_values( Events::flatten_wp_events_array( $cron_option ) );
		$this->assert_event_matches_expected_args( $flat_option[0], $test_events['due_one'] );
		$this->assert_event_matches_expected_args( $flat_option[1], $test_events['due_two'] );
		$this->assert_event_matches_expected_args( $flat_option[2], $test_events['future'] );
		$this->assert_event_matches_expected_args( $flat_option[3], $test_events['recurring'] );
	}

	public function test_pre_update_cron_option() {
		// Ironically, if the function being tested here is broken,
		// the below will make it clear because test setup/teardown won't be able to clear things out :)
		// Though it probably breaks things in much earlier tests.
		$cron_option = Cron_Control\pre_get_cron_option( false );
		$this->assertEquals( [ 'version' => 2 ], $cron_option );

		// If given invalid data, just returns the old value it was given.
		$update_result = Cron_Control\pre_update_cron_option( 'not array', [ 'old array' ] );
		$this->assertEquals( [ 'old array' ], $update_result );

		// Schedule one event, and leave two unsaved.
		$default_args   = [ 'timestamp' => time() + 100, 'args' => [ 'some', 'args' ] ];
		$existing_event = Utils::create_unsaved_event( array_merge( $default_args, [ 'action' => 'test_pre_update_cron_option_existing' ] ) );
		$existing_event->save();

		$event_to_add           = Utils::create_unsaved_event( array_merge( $default_args, [ 'action' => 'test_pre_update_cron_option_new' ] ) );
		$recurring_event_to_add = Utils::create_unsaved_event( array_merge( $default_args, [
			'action' => 'test_pre_update_cron_option_new_recurring',
			'schedule' => 'hourly',
			'interval' => HOUR_IN_SECONDS,
		] ) );

		// Mock the scenario of sending a fresh events into the mix.
		$existing_option = Events::format_events_for_wp( [ $existing_event ] );
		$new_option      = Events::format_events_for_wp( [ $existing_event, $event_to_add, $recurring_event_to_add ] );
		$update_result   = Cron_Control\pre_update_cron_option( $new_option, $existing_option );

		$this->assertEquals( $existing_option, $update_result, 'return value is always the prev value' );
		$added_event = Event::find( [ 'action' => 'test_pre_update_cron_option_new' ] );
		$this->assertEquals( $event_to_add->get_action(), $added_event->get_action(), 'single event was registered' );
		$added_recurring_event = Event::find( [ 'action' => 'test_pre_update_cron_option_new_recurring' ] );
		$this->assertEquals( $recurring_event_to_add->get_schedule(), $added_recurring_event->get_schedule(), 'recurring event was registered' );

		// Mock the scenario of deleting an event from the mix.
		$existing_option = Events::format_events_for_wp( [ $existing_event, $added_event ] );
		$new_option      = Events::format_events_for_wp( [ $event_to_add ] );
		$update_result   = Cron_Control\pre_update_cron_option( $new_option, $existing_option );

		$this->assertEquals( $existing_option, $update_result, 'return value is always the prev value' );
		$removed_event = Event::find( [ 'action' => 'test_pre_update_cron_option_existing' ] );
		$this->assertEquals( null, $removed_event, 'event was removed' );
	}

	private function assert_event_matches_expected_args( $event, $args ) {
		$this->assertEquals( $event['timestamp'], $args['timestamp'], 'timestamp matches' );
		$this->assertEquals( $event['action'], $args['hook'], 'action matches' );
		$this->assertEquals( $event['args'], $args['args'], 'args match' );

		if ( ! empty( $args['schedule'] ) ) {
			$this->assertEquals( $event['schedule'], $args['schedule'], 'schedule matches' );
			$this->assertEquals( $event['interval'], $args['interval'], 'interval matches' );
		}
	}

	private function create_test_events() {
		$event_details  = [ 'hook' => 'test_create_test_events', 'schedule' => false ];
		$recurring_args = [ 'schedule' => 'hourly', 'interval' => HOUR_IN_SECONDS ];

		// Schedule some events.
		$due_event_one = array_merge( $event_details, [ 'timestamp' => time() - 1000, 'args' => [] ] );
		$due_event_two = array_merge( $event_details, [ 'timestamp' => time() - 10, 'args' => [ 'default args' ] ] );
		$future_event  = array_merge( $event_details, [ 'timestamp' => time() + 500, 'args' => [ 'unique args' ] ] );
		$recurring     = array_merge( $event_details, $recurring_args, [ 'timestamp' => time() + 1500, 'args' => [ 'recurring args' ] ] );
		Cron_Control\pre_schedule_event( null, (object) $due_event_one );
		Cron_Control\pre_schedule_event( null, (object) $due_event_two );
		Cron_Control\pre_schedule_event( null, (object) $future_event );
		Cron_Control\pre_schedule_event( null, (object) $recurring );

		return [
			'due_one'   => $due_event_one,
			'due_two'   => $due_event_two,
			'future'    => $future_event,
			'recurring' => $recurring,
		];
	}
}
