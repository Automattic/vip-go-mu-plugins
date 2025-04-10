<?php
/**
 * Internal events to manage the plugin and resolve common WP cron complaints.
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

class Internal_Events extends Singleton {

	private array $internal_events = [];
	private array $internal_events_schedules = [];

	protected function class_init() {
		$this->prepare_internal_events();
		$this->prepare_internal_events_schedules();

		// Schedule the internal events once our custom store is in place.
		if ( Events_Store::is_installed() ) {
			$is_cron_or_cli = wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
			$is_admin = is_admin() && ! wp_doing_ajax();

			if ( $is_cron_or_cli || $is_admin ) {
				add_action( 'wp_loaded', [ $this, 'schedule_internal_events' ] );
			}
		}

		// Register schedules and callbacks.
		add_filter( 'cron_schedules', [ $this, 'register_internal_events_schedules' ] );
		foreach ( $this->internal_events as $internal_event ) {
			add_action( $internal_event['action'], $internal_event['callback'] );
		}
	}

	/**
	 * Populate internal events, allowing for additions.
	 */
	private function prepare_internal_events() {
		$internal_events = [
			[
				'schedule' => 'a8c_cron_control_minute',
				'action'   => 'a8c_cron_control_force_publish_missed_schedules',
				'callback' => [ $this, 'force_publish_missed_schedules' ],
			],
			[
				'schedule' => 'a8c_cron_control_ten_minutes',
				'action'   => 'a8c_cron_control_confirm_scheduled_posts',
				'callback' => [ $this, 'confirm_scheduled_posts' ],
			],
			[
				'schedule' => 'hourly',
				'action'   => 'a8c_cron_control_purge_completed_events',
				'callback' => [ $this, 'purge_completed_events' ],
			],
			[
				'schedule' => 'daily',
				'action'   => 'a8c_cron_control_clean_legacy_data',
				'callback' => [ $this, 'clean_legacy_data' ],
			],
		];

		// Allow additional internal events to be specified, ensuring the above cannot be overwritten.
		if ( defined( 'CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS' ) && is_array( \CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS ) ) {
			$internal_actions = wp_list_pluck( $internal_events, 'action' );

			foreach ( \CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS as $additional ) {
				if ( in_array( $additional['action'], $internal_actions, true ) ) {
					continue;
				}

				if ( ! array_key_exists( 'schedule', $additional ) || ! array_key_exists( 'action', $additional ) || ! array_key_exists( 'callback', $additional ) ) {
					continue;
				}

				$internal_events[] = $additional;
			}
		}

		$this->internal_events = $internal_events;
	}

	/**
	 * Allow custom internal events to provide their own schedules.
	 */
	private function prepare_internal_events_schedules() {
		$internal_events_schedules = [
			'a8c_cron_control_minute' => [
				'interval' => 2 * MINUTE_IN_SECONDS,
				'display'  => __( 'Cron Control internal job - every 2 minutes (used to be 1 minute)', 'automattic-cron-control' ),
			],
			'a8c_cron_control_ten_minutes' => [
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => __( 'Cron Control internal job - every 10 minutes', 'automattic-cron-control' ),
			],
		];

		// Allow additional schedules for custom events, ensuring the above cannot be overwritten.
		if ( defined( 'CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS_SCHEDULES' ) && is_array( \CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS_SCHEDULES ) ) {
			foreach ( \CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS_SCHEDULES as $name => $attrs ) {
				if ( array_key_exists( $name, $internal_events_schedules ) ) {
					continue;
				}

				if ( ! array_key_exists( 'interval', $attrs ) || ! array_key_exists( 'display', $attrs ) ) {
					continue;
				}

				$internal_events_schedules[ $name ] = $attrs;
			}
		}

		$this->internal_events_schedules = $internal_events_schedules;
	}

	public function register_internal_events_schedules( array $schedules ): array {
		return array_merge( $schedules, $this->internal_events_schedules );
	}

	public function schedule_internal_events() {
		foreach ( $this->internal_events as $event_args ) {
			if ( ! wp_next_scheduled( $event_args['action'] ) ) {
				wp_schedule_event( time(), $event_args['schedule'], $event_args['action'] );
			}
		}
	}

	/**
	 * Check if an action belongs to an internal event.
	 *
	 * @param string $action Event action.
	 */
	public function is_internal_event( $action ): bool {
		return in_array( $action, wp_list_pluck( $this->internal_events, 'action' ), true );
	}

	/*
	|--------------------------------------------------------------------------
	| Internal Event Callbacks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Publish scheduled posts that miss their schedule.
	 */
	public function force_publish_missed_schedules() {
		global $wpdb;

		$missed_posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} p JOIN (SELECT DISTINCT post_type FROM {$wpdb->posts}) AS t ON p.post_type = t.post_type WHERE post_status = 'future' AND post_date <= %s LIMIT 0,100;", current_time( 'mysql', false ) ) );

		foreach ( $missed_posts as $missed_post ) {
			$missed_post = absint( $missed_post );
			wp_publish_post( $missed_post );
			wp_clear_scheduled_hook( 'publish_future_post', array( $missed_post ) );

			do_action( 'a8c_cron_control_published_post_that_missed_schedule', $missed_post );
		}
	}

	/**
	 * Ensure scheduled posts have a corresponding cron job to publish them.
	 */
	public function confirm_scheduled_posts() {
		global $wpdb;

		$page     = 1;
		$quantity = 100;

		do {
			$offset       = max( 0, $page - 1 ) * $quantity;
			$future_posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM {$wpdb->posts} p JOIN (SELECT DISTINCT post_type FROM {$wpdb->posts}) AS t ON p.post_type = t.post_type WHERE post_status = 'future' AND post_date > %s LIMIT %d,%d", current_time( 'mysql', false ), $offset, $quantity ) );

			if ( ! empty( $future_posts ) ) {
				foreach ( $future_posts as $future_post ) {
					$future_post->ID = absint( $future_post->ID );
					$gmt_time        = strtotime( get_gmt_from_date( $future_post->post_date ) . ' GMT' );
					$timestamp       = wp_next_scheduled( 'publish_future_post', array( $future_post->ID ) );

					if ( false === $timestamp ) {
						wp_schedule_single_event( $gmt_time, 'publish_future_post', array( $future_post->ID ) );

						do_action( 'a8c_cron_control_publish_scheduled', $future_post->ID );
					} elseif ( (int) $timestamp !== $gmt_time ) {
						wp_clear_scheduled_hook( 'publish_future_post', array( $future_post->ID ) );
						wp_schedule_single_event( $gmt_time, 'publish_future_post', array( $future_post->ID ) );

						do_action( 'a8c_cron_control_publish_rescheduled', $future_post->ID );
					}
				}
			}

			$page++;

			if ( count( $future_posts ) < $quantity || $page > 5 ) {
				break;
			}
		} while ( ! empty( $future_posts ) );
	}

	/**
	 * Delete event objects for events that have run.
	 */
	public function purge_completed_events() {
		Events_Store::instance()->purge_completed_events();
	}

	/**
	 * Handles legacy data and general cleanup.
	 */
	public function clean_legacy_data() {
		$this->migrate_legacy_cron_events();

		// Now that we've migrated events, can delete the cron option to save space in alloptions.
		delete_option( 'cron' );

		// While this plugin doesn't use this locking mechanism, other code may check the value.
		delete_transient( 'doing_cron' );

		$this->prune_duplicate_events();
		$this->ensure_internal_events_have_correct_schedule();
	}

	private function migrate_legacy_cron_events() {
		global $wpdb;

		// Grab directly from the database to avoid our special filtering.
		$cron_row = $wpdb->get_row( "SELECT * FROM $wpdb->options WHERE option_name = 'cron'" );
		if ( ! isset( $cron_row->option_value ) ) {
			return;
		}

		$cron_array = maybe_unserialize( $cron_row->option_value );
		if ( ! is_array( $cron_array ) ) {
			return;
		}

		$legacy_events     = Events::flatten_wp_events_array( $cron_array );
		$registered_events = Events::flatten_wp_events_array( pre_get_cron_option( false ) );

		// Add any legacy events that are not registered in our custom table yet.
		$events_to_add = array_diff_key( $legacy_events, $registered_events );
		foreach ( $events_to_add as $event_to_add ) {
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
	}

	// Recurring events that have the same action/args/schedule are unnecessary. We can safely remove them.
	private function prune_duplicate_events() {
		$events = Events::query( [ 'limit' => -1, 'orderby' => 'ID', 'order' => 'ASC' ] );

		$original_events  = [];
		$duplicate_events = [];
		foreach ( $events as $event ) {
			if ( ! $event->is_recurring() ) {
				// Only interested in recurring events.
				continue;
			}

			$unique_key = sha1( "{$event->get_action()}:{$event->get_instance()}:{$event->get_schedule()}" );
			if ( ! isset( $original_events[ $unique_key ] ) ) {
				// The first occurrence, will also be the oldest (lowest ID).
				$original_events[ $unique_key ] = true;
			} else {
				// Found a duplicate!
				$duplicate_events[] = $event;
			}
		}

		foreach ( $duplicate_events as $duplicate_event ) {
			// For now we'll just complete them.
			$duplicate_event->complete();
		}
	}

	private function ensure_internal_events_have_correct_schedule() {
		$schedules = wp_get_schedules();

		foreach ( $this->internal_events as $internal_event ) {
			$timestamp = wp_next_scheduled( $internal_event['action'] );

			// Will reschedule on its own.
			if ( false === $timestamp ) {
				continue;
			}

			$event = Event::find( [
				'timestamp' => $timestamp,
				'action'    => $internal_event['action'],
				'instance'  => md5( maybe_serialize( [] ) ),
			] );

			if ( ! is_null( $event ) && $event->get_schedule() !== $internal_event['schedule'] ) {
				// Update to the new schedule.
				$event->set_schedule( $internal_event['schedule'], $schedules[ $internal_event['schedule'] ]['interval'] );
				$event->save();
			}
		}
	}
}
