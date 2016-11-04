<?php

namespace WP_Cron_Control_Revisited;

class Main extends Singleton {
	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Class properties
	 */
	public $job_queue_size                  = 10;
	public $job_queue_window_in_seconds     = 60;
	public $job_execution_buffer_in_seconds = 15;
	public $job_timeout_in_minutes          = 10;
	public $job_concurrency_limit           = 10;

	private $cache_key_lock           = 'wpccr_lock';
	private $cache_key_lock_timestamp = 'wpccr_lock_ts';

	/**
	 * Register hooks
	 */
	protected function class_init() {
		// For now, leave WP-CLI alone
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Bail when plugin conditions aren't met
		if ( ! defined( '\WP_CRON_CONTROL_SECRET' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
			return;
		}

		// Prime lock cache if not present
		wp_cache_add( $this->cache_key_lock, 0 );
		wp_cache_add( $this->cache_key_lock_timestamp, time() );

		// Load dependencies
		require __DIR__ . '/class-internal-events.php';
		require __DIR__ . '/class-rest-api.php';
		require __DIR__ . '/functions.php';

		// Block normal cron execution
		define( 'DISABLE_WP_CRON', true );
		define( 'ALTERNATE_WP_CRON', false );

		add_action( 'muplugins_loaded', array( $this, 'block_direct_cron' ) );
		remove_action( 'init', 'wp_cron' );

		add_filter( 'cron_request', array( $this, 'block_spawn_cron' ) );
	}

	/**
	 * Block direct cron execution as early as possible
	 */
	public function block_direct_cron() {
		if ( false !== strpos( $_SERVER['REQUEST_URI'], '/wp-cron.php' ) ) {
			status_header( 403 );
			wp_send_json_error( new \WP_Error( 'forbidden', sprintf( __( 'Normal cron execution is blocked when the %s plugin is active.', 'wp-cron-control-revisited' ), 'WP-Cron Control Revisited' ) ) );
		}
	}

	/**
	 * Block the `spawn_cron()` function
	 */
	public function block_spawn_cron( $spawn_cron_args ) {
		delete_transient( 'doing_cron' );

		$spawn_cron_args['url']  = '';
		$spawn_cron_args['key']  = '';
		$spawn_cron_args['args'] = array();

		return $spawn_cron_args;
	}

	/**
	 * Display an error if the plugin's conditions aren't met
	 */
	public function admin_notice() {
		?>
		<div class="notice notice-error">
			<p><?php printf( __( '<strong>%1$s</strong>: To use this plugin, define the constant %2$s.', 'wp-cron-control-revisited' ), 'WP-Cron Control Revisited', '<code>WP_CRON_CONTROL_SECRET</code>' ); ?></p>
		</div>
		<?php
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * List events pending for the current period
	 */
	public function get_events() {
		$events = get_option( 'cron' );

		// That was easy
		if ( ! is_array( $events ) || empty( $events ) ) {
			return array( 'events' => null, );
		}

		// To be safe, re-sort the array just as Core does when events are scheduled
		// Ensures events are sorted chronologically
		uksort( $events, 'strnatcasecmp' );

		// Select only those events to run in the next sixty seconds
		// Will include missed events as well
		$current_events = $internal_events = array();
		$current_window = strtotime( sprintf( '+%d seconds', $this->job_queue_window_in_seconds ) );

		foreach ( $events as $timestamp => $timestamp_events ) {
			// Skip non-event data that Core includes in the option
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}

			// Skip events whose time hasn't come
			if ( $timestamp > $current_window ) {
				break;
			}

			// Extract just the essentials needed to retrieve the full job later on
			foreach ( $timestamp_events as $action => $action_instances ) {
				foreach ( $action_instances as $instance => $instance_args ) {
					// There are some jobs we never care to run
					if ( is_blocked_event( $action ) ) {
						wp_unschedule_event( $timestamp, $action, $instance_args['args'] );
						continue;
					}

					// Necessary data to identify an individual event
					// `$action` is hashed to avoid information disclosure
					// Core hashes `$instance` for us
					$event = array(
						'timestamp' => $timestamp,
						'action'    => md5( $action ),
						'instance'  => $instance,
					);

					// Queue internal events separately to avoid them being blocked
					if ( is_internal_event( $action ) ) {
						$internal_events[] = $event;
					} else {
						$current_events[] = $event;
					}
				}
			}
		}

		// Limit batch size to avoid resource exhaustion
		if ( count( $current_events ) > $this->job_queue_size ) {
			$current_events = array_slice( $current_events, 0, $this->job_queue_size );
		}

		return array(
			'events'   => array_merge( $current_events, $internal_events ),
			'endpoint' => get_rest_url( null, REST_API_NAMESPACE . '/' . REST_API_ENDPOINT_RUN ),
		);
	}

	/**
	 * Execute a specific event
	 *
	 * @param $timestamp  int     Unix timestamp
	 * @param $action     string  md5 hash of the action used when the event is registered
	 * @param $instance   string  md5 hash of the event's arguments array, which Core uses to index the `cron` option
	 *
	 * @return array|\WP_Error
	 */
	public function run_event( $timestamp, $action, $instance ) {
		// Validate input data
		if ( empty( $timestamp ) || empty( $action ) || empty( $instance ) ) {
			return new \WP_Error( 'missing-data', __( 'Invalid or incomplete request data.', 'wp-cron-control-revisited' ) );
		}

		// Ensure we don't run jobs too far ahead
		if ( $timestamp > strtotime( sprintf( '+%d seconds', $this->job_execution_buffer_in_seconds ) ) ) {
			return new \WP_Error( 'premature', __( 'Event is not scheduled to be run yet.', 'wp-cron-control-revisited' ) );
		}

		// Find the event to retrieve the full arguments
		$event = $this->get_event( $timestamp, $action, $instance );
		unset( $timestamp, $action, $instance );

		// Nothing to do...
		if ( ! is_array( $event ) ) {
			return new \WP_Error( 'no-event', __( 'The specified event could not be found.', 'wp-cron-control-revisited' ) );
		}

		// And we're off!
		$time_start = microtime( true );

		// Limit how many events are processed concurrently
		if ( ! is_internal_event( $event['action'] ) && ! $this->check_lock() ) {
			return new \WP_Error( 'no-free-threads', __( 'No resources available to run this job.', 'wp-cron-control-revisited' ) );
		}

		// Prepare environment to run job
		ignore_user_abort( true );
		set_time_limit( $this->job_timeout_in_minutes * MINUTE_IN_SECONDS );
		define( 'DOING_CRON', true );

		// Remove the event, and reschedule if desired
		// Follows pattern Core uses in wp-cron.php
		if ( false !== $event['schedule'] ) {
			$reschedule_args = array( $event['timestamp'], $event['schedule'], $event['action'], $event['args'] );
			call_user_func_array( 'wp_reschedule_event', $reschedule_args );
		}

		wp_unschedule_event( $event['timestamp'], $event['action'], $event['args'] );
		Cron_Options_CPT::instance()->delete_event( $event['timestamp'], $event['action'], $event['instance'] );

		// Run the event
		do_action_ref_array( $event['action'], $event['args'] );

		// Free process for the next event
		if ( ! is_internal_event( $event['action'] ) ) {
			$this->free_lock();
		}

		$time_end = microtime( true );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Job with action `%1$s` and arguments `%2$s` completed in %3$d seconds.', 'wp-cron-control-revisited' ), $event['action'], serialize( $event['args'] ), $time_end - $time_start ),
		);
	}

	/**
	 * Find an event's data using its hashed representations
	 *
	 * The `$instance` argument is hashed for us by Core, while we hash the action to avoid information disclosure
	 */
	private function get_event( $timestamp, $action_hashed, $instance ) {
		$events = get_option( 'cron' );
		$event  = false;

		if ( isset( $events[ $timestamp ] ) ) {
			foreach ( $events[ $timestamp ] as $action => $action_events ) {
				if ( hash_equals( md5( $action ), $action_hashed ) && isset( $action_events[ $instance ] ) ) {
					$event              = $action_events[ $instance ];
					$event['timestamp'] = $timestamp;
					$event['action']    = $action;
					$event['instance']  = $instance;
					break;
				}
			}
		}

		return $event;
	}

	/**
	 * PLUGIN UTILITY METHODS
	 */

	/**
	 * Set a lock and limit how many concurrent jobs are permitted
	 */
	private function check_lock() {
		// Prevent deadlock
		$lock_timestamp = (int) wp_cache_get( $this->cache_key_lock_timestamp, null, true );

		if ( $lock_timestamp < time() - $this->job_timeout_in_minutes * MINUTE_IN_SECONDS ) {
			wp_cache_set( $this->cache_key_lock, 0 );
			wp_cache_set( $this->cache_key_lock_timestamp, time() );
			return true;
		}

		// Check if process can run
		$lock = (int) wp_cache_get( $this->cache_key_lock, null, true );

		if ( $lock >= $this->job_concurrency_limit ) {
			return false;
		} else {
			wp_cache_incr( $this->cache_key_lock );
			return true;
		}
	}

	/**
	 * When event completes, allow another
	 */
	private function free_lock() {
		$lock = (int) wp_cache_get( $this->cache_key_lock, null, true );

		if ( $lock > 1 ) {
			wp_cache_decr( $this->cache_key_lock );
		} else {
			wp_cache_set( $this->cache_key_lock, 0 );
		}

		wp_cache_set( $this->cache_key_lock_timestamp, time() );

		return true;
	}
}
