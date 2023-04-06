<?php
/**
 * Manage event execution
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

use WP_Error;

/**
 * Events class
 */
class Events extends Singleton {
	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Class constants
	 */
	const LOCK = 'run-events';

	const DISABLE_RUN_OPTION = 'a8c_cron_control_disable_run';

	/**
	 * List of actions whitelisted for concurrent execution
	 *
	 * @var array
	 */
	private $concurrent_action_whitelist = array();

	/**
	 * The event currently being executed.
	 *
	 * @var null|Event
	 */
	private $running_event = null;

	/**
	 * Register hooks
	 */
	protected function class_init() {
		// Prime lock cache if not present.
		Lock::prime_lock( self::LOCK );

		// Prepare environment as early as possible.
		$earliest_action = did_action( 'muplugins_loaded' ) ? 'plugins_loaded' : 'muplugins_loaded';
		add_action( $earliest_action, array( $this, 'prepare_environment' ) );

		// Allow code loaded as late as the theme to modify the whitelist.
		add_action( 'after_setup_theme', array( $this, 'populate_concurrent_action_whitelist' ) );
	}

	/**
	 * Prepare environment to run job
	 *
	 * Must run as early as possible, particularly before any client code is loaded
	 * This also runs before Core has parsed the request and set the \REST_REQUEST constant
	 */
	public function prepare_environment() {
		// Limit to plugin's endpoints.
		$endpoint = get_endpoint_type();
		if ( false === $endpoint ) {
			return;
		}

		// Flag is used in many contexts, so should be set for all of our requests, regardless of the action.
		set_doing_cron();

		// When running events, allow for long-running ones, and non-blocking trigger requests.
		if ( REST_API::ENDPOINT_RUN === $endpoint ) {
			ignore_user_abort( true );
			set_time_limit( JOB_TIMEOUT_IN_MINUTES * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Allow certain events to be run concurrently
	 *
	 * By default, multiple events of the same action cannot be run concurrently, due to alloptions and other data-corruption issues
	 * Some events, however, are fine to run concurrently, and should be whitelisted for such
	 */
	public function populate_concurrent_action_whitelist() {
		$concurrency_whitelist = apply_filters( 'a8c_cron_control_concurrent_event_whitelist', array() );

		if ( is_array( $concurrency_whitelist ) && ! empty( $concurrency_whitelist ) ) {
			$this->concurrent_action_whitelist = $concurrency_whitelist;
		}
	}

	/**
	 * List events pending for the current period.
	 *
	 * @param null|int $job_queue_size   Maximum number of events to return (excludes internal events).
	 * @param null|int $job_queue_window How many seconds into the future events should be fetched.
	 * @return array Events to be run in the next batch.
	 */
	public function get_events( $job_queue_size = null, $job_queue_window = null ): array {
		$job_queue_size   = is_null( $job_queue_size ) ? JOB_QUEUE_SIZE : $job_queue_size;
		$job_queue_window = is_null( $job_queue_window ) ? JOB_QUEUE_WINDOW_IN_SECONDS : $job_queue_window;

		// Grab relevant events that are due, or soon will be.
		$current_time = time();
		$events = self::query( [
			'timestamp' => [ 'from' => 0, 'to' => $current_time + $job_queue_window ],
			'status'    => Events_Store::STATUS_PENDING,
			'limit'     => -1, // Need to get all, to ensure we grab internals even when queue is backed up.
		] );

		// That was easy.
		if ( empty( $events ) ) {
			return [ 'events' => null ];
		}

		$current_events  = [];
		$internal_events = [];
		foreach ( $events as $event ) {
			// action is hashed to avoid information disclosure.
			$event_data_public = [
				'timestamp' => $event->get_timestamp(),
				'action'    => md5( $event->get_action() ),
				'instance'  => $event->get_instance(),
			];

			// Queue internal events separately to avoid them being blocked.
			if ( $event->is_internal() ) {
				$internal_events[] = $event_data_public;
			} else {
				$current_events[] = $event_data_public;
			}
		}

		// Limit batch size to avoid resource exhaustion.
		if ( count( $current_events ) > $job_queue_size ) {
			$current_events = $this->reduce_queue( $current_events, $job_queue_size );
		}

		// Combine with Internal Events.
		// TODO: un-nest array, which is nested for legacy reasons.
		return [ 'events' => array_merge( $current_events, $internal_events ) ];
	}

	/**
	 * Trim events queue down to a specific limit.
	 *
	 * @param array $events         List of events to be run in the current period.
	 * @param array $max_queue_size Maximum number of events to return.
	 * @return array
	 */
	private function reduce_queue( $events, $max_queue_size ): array {
		// Loop through events, adding one of each action during each iteration.
		$reduced_queue = array();
		$action_counts = array();

		$i = 1; // Intentionally not zero-indexed to facilitate comparisons against $action_counts members.

		do {
			// Each time the events array is iterated over, move one instance of an action to the current queue.
			foreach ( $events as $key => $event ) {
				$action = $event['action'];

				// Prime the count.
				if ( ! isset( $action_counts[ $action ] ) ) {
					$action_counts[ $action ] = 0;
				}

				// Check and do the move.
				if ( $action_counts[ $action ] < $i ) {
					$reduced_queue[] = $event;
					$action_counts[ $action ]++;
					unset( $events[ $key ] );
				}
			}

			// When done with an iteration and events remain, start again from the beginning of the $events array.
			if ( empty( $events ) ) {
				break;
			} else {
				$i++;
				reset( $events );

				continue;
			}
		} while ( $i <= 15 && count( $reduced_queue ) < $max_queue_size && ! empty( $events ) );

		/**
		 * IMPORTANT: DO NOT re-sort the $reduced_queue array from this point forward.
		 * Doing so defeats the preceding effort.
		 *
		 * While the events are now out of order with respect to timestamp, they're ordered
		 * such that one of each action is run before another of an already-run action.
		 * The timestamp mis-ordering is trivial given that we're only dealing with events
		 * for the current $job_queue_window.
		 */

		// Finally, ensure that we don't have more than we need.
		if ( count( $reduced_queue ) > $max_queue_size ) {
			$reduced_queue = array_slice( $reduced_queue, 0, $max_queue_size );
		}

		return $reduced_queue;
	}

	/**
	 * Execute a specific event
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $action md5 hash of the action used when the event is registered.
	 * @param string $instance  md5 hash of the event's arguments array, which Core uses to index the `cron` option.
	 * @param bool   $force Run event regardless of timestamp or lock status? eg, when executing jobs via wp-cli.
	 * @return array|WP_Error
	 */
	public function run_event( $timestamp, $action, $instance, $force = false ) {
		// Validate input data.
		if ( empty( $timestamp ) || empty( $action ) || empty( $instance ) ) {
			return new WP_Error( 'missing-data', __( 'Invalid or incomplete request data.', 'automattic-cron-control' ), [ 'status' => 400 ] );
		}

		// Ensure we don't run jobs ahead of time.
		if ( ! $force && $timestamp > time() ) {
			/* translators: 1: Job identifier */
			$error_message = sprintf( __( 'Job with identifier `%1$s` is not scheduled to run yet.', 'automattic-cron-control' ), "$timestamp-$action-$instance" );
			return new WP_Error( 'premature', $error_message, [ 'status' => 404 ] );
		}

		$event = Event::find( [
			'timestamp'     => $timestamp,
			'action_hashed' => $action,
			'instance'      => $instance,
			'status'        => Events_Store::STATUS_PENDING,
		] );

		// Nothing to do...
		if ( is_null( $event ) ) {
			/* translators: 1: Job identifier */
			$error_message = sprintf( __( 'Job with identifier `%1$s` could not be found.', 'automattic-cron-control' ), "$timestamp-$action-$instance" );
			return new WP_Error( 'no-event', $error_message, [ 'status' => 404 ] );
		}

		unset( $timestamp, $action, $instance );

		// Limit how many events are processed concurrently, unless explicitly bypassed.
		if ( ! $force ) {
			// Prepare event-level lock.
			$this->prime_event_action_lock( $event );

			if ( ! $this->can_run_event( $event ) ) {
				/* translators: 1: Event action, 2: Event arguments */
				$error_message = sprintf( __( 'No resources available to run the job with action `%1$s` and arguments `%2$s`.', 'automattic-cron-control' ), $event->get_action(), maybe_serialize( $event->get_args() ) );
				return new WP_Error( 'no-free-threads', $error_message, [ 'status' => 429 ] );
			}

			// Free locks later in case event throws an uncatchable error.
			$this->running_event = $event;
			add_action( 'shutdown', array( $this, 'do_lock_cleanup_on_shutdown' ) );
		}

		// Core reschedules/conpletes an event before running it, so we respect that.
		if ( $event->is_recurring() ) {
			$event->reschedule();
		} else {
			$event->complete();
		}

		try {
			$event->run();
		} catch ( \Throwable $t ) {
			/**
			 * Note that timeouts and memory exhaustion do not invoke this block.
			 * Instead, those locks are freed in `do_lock_cleanup_on_shutdown()`.
			 */

			do_action( 'a8c_cron_control_event_threw_catchable_error', $event->get_legacy_event_format(), $t );

			$return = array(
				'success' => false,
				/* translators: 1: Event action, 2: Event arguments, 3: Throwable error, 4: Line number that raised Throwable error */
				'message' => sprintf( __( 'Callback for job with action `%1$s` and arguments `%2$s` raised a Throwable - %3$s in %4$s on line %5$d.', 'automattic-cron-control' ), $event->get_action(), maybe_serialize( $event->get_args() ), $t->getMessage(), $t->getFile(), $t->getLine() ),
			);
		}

		// Free locks for the next event, unless they weren't set to begin with.
		if ( ! $force ) {
			// If we got this far, there's no uncaught error to handle.
			$this->running_event = null;
			remove_action( 'shutdown', array( $this, 'do_lock_cleanup_on_shutdown' ) );

			$this->do_lock_cleanup( $event );
		}

		// Callback didn't trigger a Throwable, indicating it succeeded.
		if ( ! isset( $return ) ) {
			$return = array(
				'success' => true,
				/* translators: 1: Event action, 2: Event arguments */
				'message' => sprintf( __( 'Job with action `%1$s` and arguments `%2$s` executed.', 'automattic-cron-control' ), $event->get_action(), maybe_serialize( $event->get_args() ) ),
			);
		}

		return $return;
	}

	private function prime_event_action_lock( Event $event ): void {
		Lock::prime_lock( $this->get_lock_key_for_event_action( $event ), JOB_LOCK_EXPIRY_IN_MINUTES * \MINUTE_IN_SECONDS );
	}

	// Checks concurrency locks, deciding if the event can be run at this moment.
	private function can_run_event( Event $event ): bool {
		// Limit to one concurrent execution of a specific action by default.
		$limit = 1;

		if ( isset( $this->concurrent_action_whitelist[ $event->get_action() ] ) ) {
			$limit = absint( $this->concurrent_action_whitelist[ $event->get_action() ] );
			$limit = min( $limit, JOB_CONCURRENCY_LIMIT );
		}

		if ( ! Lock::check_lock( $this->get_lock_key_for_event_action( $event ), $limit, JOB_LOCK_EXPIRY_IN_MINUTES * \MINUTE_IN_SECONDS ) ) {
			return false;
		}

		// Internal Events aren't subject to the global lock.
		if ( $event->is_internal() ) {
			return true;
		}

		// Check if any resources are available to execute this job.
		// If not, the individual-event lock must be freed, otherwise it's deadlocked until it times out.
		if ( ! Lock::check_lock( self::LOCK, JOB_CONCURRENCY_LIMIT ) ) {
			$this->reset_event_lock( $event );
			return false;
		}

		// Let's go!
		return true;
	}

	private function do_lock_cleanup( Event $event ): void {
		// Site-level lock isn't set when event is Internal, so we don't want to alter it.
		if ( ! $event->is_internal() ) {
			Lock::free_lock( self::LOCK );
		}

		// Reset individual event lock.
		$this->reset_event_lock( $event );
	}

	private function reset_event_lock( Event $event ): bool {
		$lock_key = $this->get_lock_key_for_event_action( $event );
		$expires  = JOB_LOCK_EXPIRY_IN_MINUTES * \MINUTE_IN_SECONDS;

		if ( isset( $this->concurrent_action_whitelist[ $event->get_action() ] ) ) {
			return Lock::free_lock( $lock_key, $expires );
		} else {
			return Lock::reset_lock( $lock_key, $expires );
		}
	}

	/**
	 * Turn the event action into a string that can be used with a lock
	 *
	 * @param Event|stdClass $event
	 * @return string
	 */
	public function get_lock_key_for_event_action( $event ): string {
		// Hashed solely to constrain overall length.
		$action = method_exists( $event, 'get_action' ) ? $event->get_action() : $event->action;
		return md5( 'ev-' . $action );
	}

	/**
	 * If event execution throws uncatchable error, free locks
	 * Covers situations such as timeouts and memory exhaustion, which aren't \Throwable errors
	 * Under normal conditions, this callback isn't hooked to `shutdown`
	 */
	public function do_lock_cleanup_on_shutdown() {
		$event = $this->running_event;

		if ( is_null( $event ) ) {
			return;
		}

		do_action( 'a8c_cron_control_freeing_event_locks_after_uncaught_error', $event->get_legacy_event_format() );

		$this->do_lock_cleanup( $event );
	}

	/**
	 * Return status of automatic event execution
	 *
	 * @return int 0 if run is enabled, 1 if run is disabled indefinitely, otherwise timestamp when execution will resume
	 */
	public function run_disabled() {
		$disabled = (int) get_option( self::DISABLE_RUN_OPTION, 0 );

		if ( $disabled <= 1 || $disabled > time() ) {
			return $disabled;
		}

		$this->update_run_status( 0 );
		return 0;
	}

	/**
	 * Set automatic execution status
	 *
	 * @param int $new_status 0 if run is enabled, 1 if run is disabled indefinitely, otherwise timestamp when execution will resume.
	 * @return bool
	 */
	public function update_run_status( $new_status ) {
		$new_status = absint( $new_status );

		// Don't store a past timestamp.
		if ( $new_status > 1 && $new_status < time() ) {
			return false;
		}

		return update_option( self::DISABLE_RUN_OPTION, $new_status );
	}

	/**
	 * Query for multiple events.
	 *
	 * @param array $query_args Event query args.
	 * @return array An array of Event objects.
	 */
	public static function query( array $query_args = [] ): array {
		$event_db_rows = Events_Store::instance()->_query_events_raw( $query_args );
		$events = array_map( fn( $db_row ) => Event::get_from_db_row( $db_row ), $event_db_rows );
		return array_filter( $events, fn( $event ) => ! is_null( $event ) );
	}

	/**
	 * Format multiple events the way WP expects them.
	 *
	 * @param array $events Array of Event objects that need formatting.
	 * @return array Array of event data in the deeply nested format WP expects.
	 */
	public static function format_events_for_wp( array $events ): array {
		$crons = [];

		foreach ( $events as $event ) {
			// Level 1: Ensure the root timestamp node exists.
			$timestamp = $event->get_timestamp();
			if ( ! isset( $crons[ $timestamp ] ) ) {
				$crons[ $timestamp ] = [];
			}

			// Level 2: Ensure the action node exists.
			$action = $event->get_action();
			if ( ! isset( $crons[ $timestamp ][ $action ] ) ) {
				$crons[ $timestamp ][ $action ] = [];
			}

			// Finally, add the rest of the event details.
			$formatted_event = [
				'schedule' => empty( $event->get_schedule() ) ? false : $event->get_schedule(),
				'args'     => $event->get_args(),
			];

			$interval = $event->get_interval();
			if ( ! empty( $interval ) ) {
				$formatted_event['interval'] = $interval;
			}

			$instance = $event->get_instance();
			$crons[ $timestamp ][ $action ][ $instance ] = $formatted_event;
		}

		// Re-sort the array just as core does when events are scheduled.
		uksort( $crons, 'strnatcasecmp' );
		return $crons;
	}

	/**
	 * Flatten the WP events array.
	 * Each event will have a unique key for quick comparisons.
	 *
	 * @param array $events Deeply nested array of event data in the format WP core uses.
	 * @return array Flat array that is easier to compare and work with :)
	 */
	public static function flatten_wp_events_array( array $events ): array {
		// Core legacy thing, we don't need this.
		unset( $events['version'] );

		$flattened = [];
		foreach ( $events as $timestamp => $ts_events ) {
			foreach ( $ts_events as $action => $action_instances ) {
				foreach ( $action_instances as $instance => $event_details ) {
					$unique_key = "$timestamp:$action:$instance";

					$flat_event = [
						'timestamp' => $timestamp,
						'action'    => $action,
						'instance'  => $instance,
						'args'      => $event_details['args'],
					];

					if ( ! empty( $event_details['schedule'] ) ) {
						$unique_key = "$unique_key:{$event_details['schedule']}:{$event_details['interval']}";

						$flat_event['schedule'] = $event_details['schedule'];
						$flat_event['interval'] = $event_details['interval'];
					}

					$flattened[ sha1( $unique_key ) ] = $flat_event;
				}
			}
		}

		return $flattened;
	}
}
