<?php

namespace Automattic\WP\Cron_Control;

use Automattic\WP\Cron_Control\Events_Store;
use WP_Error;

function register_adapter_hooks() {
	// Core filters added in WP 5.1, allowing us to fully use the custom event's store.
	add_filter( 'pre_schedule_event', __NAMESPACE__ . '\\pre_schedule_event', 10, 2 );
	add_filter( 'pre_reschedule_event', __NAMESPACE__ . '\\pre_reschedule_event', 10, 2 );
	add_filter( 'pre_unschedule_event', __NAMESPACE__ . '\\pre_unschedule_event', 10, 4 );
	add_filter( 'pre_clear_scheduled_hook', __NAMESPACE__ . '\\pre_clear_scheduled_hook', 10, 3 );
	add_filter( 'pre_unschedule_hook', __NAMESPACE__ . '\\pre_unschedule_hook', 10, 2 );
	add_filter( 'pre_get_scheduled_event', __NAMESPACE__ . '\\pre_get_scheduled_event', 10, 4 );
	add_filter( 'pre_get_ready_cron_jobs', __NAMESPACE__ . '\\pre_get_ready_cron_jobs', 10, 1 );

	// Backwards-compat filters in case anybody tries doing something directly w/ the 'cron' options.
	add_filter( 'pre_option_cron', __NAMESPACE__ . '\\pre_get_cron_option', 10 );
	add_filter( 'pre_update_option_cron', __NAMESPACE__ . '\\pre_update_cron_option', 10, 2 );
}

/**
 * Intercept event scheduling.
 *
 * @param null     $pre Null if the process has not been intercepted yet.
 * @param stdClass $event Event data object.
 * @return bool|WP_Error true on success, WP_Error on failure.
 */
function pre_schedule_event( $pre, $event ) {
	if ( null !== $pre ) {
		return $pre;
	}

	$query_args = [
		'action' => $event->hook,
		'args'   => $event->args,
	];

	$is_recurring = ! empty( $event->schedule );
	if ( $is_recurring ) {
		// Prevent exact duplicate recurring events altogether (ignoring timeframe).
		// This diverges a bit from core behavior, but preventing such duplicates comes with good benefits.
		$query_args['schedule'] = $event->schedule;
	} else {
		// Prevent one-time event duplicates if there is already another one like it within a 10m time span.
		// Same logic as that in wp_schedule_single_event().
		$ten_minutes  = 10 * MINUTE_IN_SECONDS;
		$current_time = time();

		$query_args['timestamp'] = [
			'from' => ( $current_time + $ten_minutes ) > $event->timestamp ? 0 : $event->timestamp - $ten_minutes,
			'to'   => $current_time > $event->timestamp ? $current_time + $ten_minutes : $event->timestamp + $ten_minutes,
		];
	}

	$existing = Event::find( $query_args );
	if ( ! is_null( $existing ) ) {
		return new WP_Error( 'cron-control:wp:duplicate-event' );
	}

	// TODO: Maybe re-query the duplicate check with SRTM first before we do the INSERT? (also would need a cache bypass flag)

	/** This filter is documented in wordpress/wp-includes/cron.php */
	$event = apply_filters( 'schedule_event', $event );
	if ( ! isset( $event->hook, $event->timestamp, $event->args ) ) {
		return new WP_Error( 'cron-control:wp:schedule-event-prevented' );
	}

	// Passed duplicate checks, all clear to create an event.
	$new_event = new Event();
	$new_event->set_action( $event->hook );
	$new_event->set_timestamp( $event->timestamp );
	$new_event->set_args( $event->args );

	if ( ! empty( $event->schedule ) && ! empty( $event->interval ) ) {
		$new_event->set_schedule( $event->schedule, $event->interval );
	}

	return $new_event->save();
}

/**
 * Intercept event rescheduling.
 * Should be unused if core cron is disabled (using cron control runner for example).
 *
 * @param null     $pre Null if the process has not been intercepted yet.
 * @param stdClass $event Event object.
 * @return bool|WP_Error true on success, WP_Error on failure.
 */
function pre_reschedule_event( $pre, $event ) {
	if ( null !== $pre ) {
		return $pre;
	}

	$event = Event::find( [
		'timestamp' => $event->timestamp,
		'action'    => $event->hook,
		'args'      => $event->args,
	] );

	if ( is_null( $event ) ) {
		return new WP_Error( 'cron-control:wp:event-not-found' );
	}

	return $event->reschedule();
}

/**
 * Intercept event unscheduling.
 *
 * @param null   $pre Null if the process has not been intercepted yet.
 * @param int    $timestamp Event timestamp.
 * @param string $hook Event action.
 * @param array  $args Event arguments.
 * @return bool|WP_Error true on success, WP_Error on failure.
 */
function pre_unschedule_event( $pre, $timestamp, $hook, $args ) {
	if ( null !== $pre ) {
		return $pre;
	}

	$event = Event::find( [
		'timestamp' => $timestamp,
		'action'    => $hook,
		'args'      => $args,
	] );

	if ( is_null( $event ) ) {
		return new WP_Error( 'cron-control:wp:event-not-found' );
	}

	return $event->complete();
}

/**
 * Clear all actions for a given hook with specific event args.
 *
 * @param null       $pre Null if the process has not been intercepted yet.
 * @param string     $hook Event action.
 * @param null|array $args Event arguments. Passing null will delete all events w/ the hook.
 * @return int|WP_Error Number of unscheduled events on success (could be 0), WP_Error on failure.
 */
function pre_clear_scheduled_hook( $pre, $hook, $args ) {
	if ( null !== $pre ) {
		return $pre;
	}

	$query_args = [
		'action' => $hook,
		'args'   => $args,
		'limit'  => 500,
		'page'   => 1,
	];

	// First grab all the events before making any changes (avoiding pagination complexities).
	$all_events = [];
	do {
		$events     = Events::query( $query_args );
		$all_events = array_merge( $all_events, $events );

		$query_args['page']++;
	} while ( ! empty( $events ) );

	$all_successful = true;
	foreach ( $all_events as $event ) {
		$result = $event->complete();

		if ( true !== $result ) {
			$all_successful = false;
		}
	}

	return $all_successful ? count( $all_events ) : new WP_Error( 'cron-control:wp:failed-event-deleting' );
}

/**
 * Clear all actions for a given hook, regardless of event args.
 *
 * @param null   $pre  Null if the process has not been intercepted yet.
 * @param string $hook Event action.
 * @return int|WP_Error Number of unscheduled events on success (could be 0), WP_Error on failure.
 */
function pre_unschedule_hook( $pre, $hook ) {
	if ( null !== $pre ) {
		return $pre;
	}

	return pre_clear_scheduled_hook( $pre, $hook, null );
}

/**
 * Intercept event retrieval.
 *
 * @param null     $pre  Null if the process has not been intercepted yet.
 * @param string   $hook Event action.
 * @param array    $args Event arguments.
 * @param int|null $timestamp Event timestamp, null to just retrieve the next event.
 * @return object|false The event object. False if the event does not exist.
 */
function pre_get_scheduled_event( $pre, $hook, $args, $timestamp ) {
	if ( null !== $pre ) {
		return $pre;
	}

	$event = Event::find( [
		'timestamp' => $timestamp,
		'action'    => $hook,
		'args'      => $args,
	] );

	return is_null( $event ) ? false : $event->get_wp_event_format();
}

/**
 * Intercept "ready events" retrieval.
 * Should be unused if core cron is disabled (using cron control runner for example).
 *
 * @param null $pre Null if the process has not been intercepted yet.
 * @return array Cron events ready to be run.
 */
function pre_get_ready_cron_jobs( $pre ) {
	if ( null !== $pre ) {
		return $pre;
	}

	// 100 is more than enough here.
	// Also, we are unlikely to see this function ever used w/ core cron running disabled.
	$events = Events::query( [
		'timestamp' => 'due_now',
		'limit'     => 100,
	] );

	return Events::format_events_for_wp( $events );
}

/**
 * Intercepts requests for the entire 'cron' option.
 * Ideally this is never called any more, but we must support this for backwards compatability.
 *
 * @param false $pre False if the process has not been intercepted yet.
 * @return array Cron array, in the format WP expects.
 */
function pre_get_cron_option( $pre ) {
	if ( false !== $pre ) {
		return $pre;
	}

	// For maximum BC, we need to truly give all events here.
	// Stepping in increments of 500 to allow query caching to do it's job.
	$query_args = [ 'limit' => 500, 'page' => 1 ];
	$all_events = [];
	do {
		$events     = Events::query( $query_args );
		$all_events = array_merge( $all_events, $events );

		$query_args['page']++;
	} while ( ! empty( $events ) );

	$cron_array = Events::format_events_for_wp( $all_events );
	$cron_array['version'] = 2; // a legacy core thing
	return $cron_array;
}

/**
 * Intercepts 'cron' option update.
 * Ideally this is never called any more either, but we must support this for backwards compatability.
 *
 * @param array $new_value New cron array trying to be saved
 * @param array $old_value Existing cron array (already intercepted via pre_get_cron_option() above)
 * @return array Always returns $old_value to prevent update from occurring.
 */
function pre_update_cron_option( $new_value, $old_value ) {
	if ( ! is_array( $new_value ) ) {
		return $old_value;
	}

	$current_events = Events::flatten_wp_events_array( $old_value );
	$new_events     = Events::flatten_wp_events_array( $new_value );

	// Remove first, to prevent scheduling conflicts for the "added events" next.
	$removed_events = array_diff_key( $current_events, $new_events );
	foreach ( $removed_events as $event_to_remove ) {
		$existing_event = Event::find( [
			'timestamp' => $event_to_remove['timestamp'],
			'action'    => $event_to_remove['action'],
			'args'      => $event_to_remove['args'],
		] );

		if ( ! is_null( $existing_event ) ) {
			// Mark as completed (perhaps canceled in the future).
			$existing_event->complete();
		}
	}

	// Now add any new events.
	$added_events = array_diff_key( $new_events, $current_events );
	foreach ( $added_events as $event_to_add ) {
		$wp_event = [
			'timestamp' => $event_to_add['timestamp'],
			'hook'      => $event_to_add['action'],
			'args'      => $event_to_add['args'],
		];

		if ( ! empty( $event_to_add['schedule'] ) ) {
			$wp_event['schedule'] = $event_to_add['schedule'];
			$wp_event['interval'] = $event_to_add['interval'];
		}

		// Pass it up through this function so we can take advantage of duplicate prevention.
		pre_schedule_event( null, (object) $wp_event );
	}

	// Always just return the old value so we don't trigger a db update.
	return $old_value;
}
