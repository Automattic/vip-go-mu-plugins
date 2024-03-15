<?php
/**
 * Utility functions for tests.
 */

namespace Automattic\WP\Cron_Control\Tests;

use Automattic\WP\Cron_Control\Events_Store;
use Automattic\WP\Cron_Control\Event;

class Utils {

	public static function get_table_name(): string {
		return Events_Store::instance()->get_table_name();
	}

	public static function clear_cron_table(): void {
		_set_cron_array( [] );
	}

	public static function create_test_event( array $args = [] ): Event {
		$event = self::create_unsaved_event( $args );
		$event->save();
		return $event;
	}

	public static function create_unsaved_event( array $args = [] ) {
		if ( empty( $args ) ) {
			$args = array(
				'timestamp' => time(),
				'action'    => 'test_unsaved_event_action',
				'args'      => [],
			);
		}

		$event = new Event();
		self::apply_event_props( $event, $args );
		return $event;
	}

	public static function apply_event_props( Event $event, array $props ): void {
		$props_to_set = array_keys( $props );

		if ( in_array( 'status', $props_to_set, true ) ) {
			$event->set_status( $props['status'] );
		}

		if ( in_array( 'action', $props_to_set, true ) ) {
			$event->set_action( $props['action'] );
		}

		if ( in_array( 'args', $props_to_set, true ) ) {
			$event->set_args( $props['args'] );
		}

		if ( in_array( 'schedule', $props_to_set, true ) ) {
			$event->set_schedule( $props['schedule'], $props['interval'] );
		}

		if ( in_array( 'timestamp', $props_to_set, true ) ) {
			$event->set_timestamp( $props['timestamp'] );
		}
	}

	public static function assert_event_object_matches_database( Event $event, array $expected_data, $context ): void {
		$raw_event = Events_Store::instance()->_get_event_raw( $event->get_id() );

		$context->assertEquals( $raw_event->ID, $expected_data['id'], 'id matches' );
		$context->assertEquals( $raw_event->status, $expected_data['status'], 'status matches' );
		$context->assertEquals( $raw_event->action, $expected_data['action'], 'action matches' );
		$context->assertEquals( $raw_event->action_hashed, md5( $expected_data['action'] ), 'action_hash matches' );
		$context->assertEquals( $raw_event->args, serialize( $expected_data['args'] ), 'args match' );
		$context->assertEquals( $raw_event->instance, md5( serialize( $expected_data['args'] ) ), 'instance matches' );
		$context->assertEquals( $raw_event->schedule, $expected_data['schedule'], 'schedule matches' );
		$context->assertEquals( $raw_event->interval, $expected_data['interval'], 'interval matches' );
		$context->assertEquals( $raw_event->timestamp, $expected_data['timestamp'], 'timestamp matches' );

		// Just make sure these were set.
		$context->assertNotNull( $raw_event->created, 'created date was set' );
		$context->assertNotNull( $raw_event->last_modified, 'last modified date was set' );
	}

	public static function assert_event_object_has_correct_props( Event $event, array $expected_data, $context ): void {
		$context->assertEquals( $event->get_id(), $expected_data['id'], 'id matches' );
		$context->assertEquals( $event->get_status(), $expected_data['status'], 'status matches' );
		$context->assertEquals( $event->get_action(), $expected_data['action'], 'action matches' );
		$context->assertEquals( $event->get_args(), $expected_data['args'], 'args match' );
		$context->assertEquals( $event->get_instance(), Event::create_instance_hash( $expected_data['args'] ), 'instance matches' );
		$context->assertEquals( $event->get_schedule(), $expected_data['schedule'], 'schedule matches' );
		$context->assertEquals( $event->get_timestamp(), $expected_data['timestamp'], 'timestamp matches' );

		// Special case: In the db it's "0", but in our class we keep as null.
		$expected_interval = 0 === $expected_data['interval'] ? null : $expected_data['interval'];
		$context->assertEquals( $event->get_interval(), $expected_interval, 'interval matches' );
	}
}
