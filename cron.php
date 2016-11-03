<?php
/*
 Plugin Name: WP-Cron Control Revisited
 Plugin URI:
 Description: Take control of wp-cron execution.
 Author: Erick Hitter, Automattic
 Version: 0.1
 Text Domain: wp-cron-control-revisited
 */

// Enable in production only for specific sites
// Otherwise, class WP-Cron Control is loaded
$whitelisted_sites = array();
if ( VIP_GO_ENV && ! in_array( FILES_CLIENT_SITE_ID, $whitelisted_sites ) ) {
	return;
}

class WP_Cron_Control_Revisited {
	/**
	 * Class instance
	 */
	private static $__instance = null;

	public static function instance() {
		if ( ! is_a( self::$__instance, __CLASS__ ) ) {
			self::$__instance = new self;
		}

		return self::$__instance;
	}

	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Class properties
	 */
	private $namespace = 'wp-cron-control-revisited/v1';
	private $secret    = null;

	private $job_queue_size                  = 10;
	private $job_queue_window_in_seconds     = 60;
	private $job_execution_buffer_in_seconds = 15;
	private $job_timeout_in_minutes          = 10;
	private $job_concurrency_limit           = 10;

	private $internal_jobs           = array();
	private $internal_jobs_schedules = array();

	private $cache_key_lock           = 'wpccr_lock';
	private $cache_key_lock_timestamp = 'wpccr_lock_ts';

	/**
	 * Register hooks
	 */
	private function __construct() {
		// For now, leave WP-CLI alone
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Load plugin functionality, when conditions are met
		if ( defined( 'WP_CRON_CONTROL_SECRET' ) ) {
			// Block normal cron execution
			define( 'DISABLE_WP_CRON', true );
			define( 'ALTERNATE_WP_CRON', false );

			add_action( 'muplugins_loaded', array( $this, 'block_direct_cron' ) );
			remove_action( 'init', 'wp_cron' );

			add_filter( 'cron_request', array( $this, 'block_spawn_cron' ) );

			// Core plugin functionality
			$this->prepare();
			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
			add_filter( 'cron_schedules', array( $this, 'register_internal_events_schedules' ) );
			add_action( 'muplugins_loaded', array( $this, 'schedule_internal_events' ), 11 );
			add_action( 'wpccrij_force_publish_missed_schedules', array( $this, 'force_publish_missed_schedules' ) );
			add_action( 'wpccrij_confirm_scheduled_posts', array( $this, 'confirm_scheduled_posts' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		}
	}

	/**
	 * Set additional variables required for plugin functionality
	 */
	private function prepare() {
		// Authentication
		$this->secret = WP_CRON_CONTROL_SECRET;

		// Internal jobs
		$this->internal_jobs = array(
			array(
				'schedule' => 'wpccrij_minute',
				'action'   => 'wpccrij_force_publish_missed_schedules',
			),
			array(
				'schedule' => 'wpccrij_ten_minutes',
				'action'   => 'wpccrij_confirm_scheduled_posts',
			),
		);

		$this->internal_jobs_schedules = array(
			'wpccrij_minute' => array(
				'interval' => 1 * MINUTE_IN_SECONDS,
				'display' => __( 'WP Cron Control Revisited internal job - every minute', 'wp-cron-control-revisited' ),
			),
			'wpccrij_ten_minutes' => array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display' => __( 'WP Cron Control Revisited internal job - every 10 minutes', 'wp-cron-control-revisited' ),
			),
		);

		// Prime lock cache if not present
		wp_cache_add( $this->cache_key_lock, 0 );
		wp_cache_add( $this->cache_key_lock_timestamp, time() );
	}

	/**
	 * Block direct cron execution as early as possible
	 */
	public function block_direct_cron() {
		if ( false !== strpos( $_SERVER['REQUEST_URI'], '/wp-cron.php' ) ) {
			status_header( 403 );
			wp_send_json_error( new WP_Error( 'forbidden', sprintf( __( 'Normal cron execution is blocked when the %s plugin is active.', 'wp-cron-control-revisited' ), 'WP-Cron Control Revisited' ) ) );
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
	 * Register API routes
	 */
	public function rest_api_init() {
		register_rest_route( $this->namespace, '/events/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_events' ),
			'permission_callback' => array( $this, 'check_secret' ),
			'show_in_index'       => false,
		) );

		register_rest_route( $this->namespace, '/event/', array(
			'methods'             => 'PUT',
			'callback'            => array( $this, 'run_event' ),
			'permission_callback' => array( $this, 'check_secret' ),
			'show_in_index'       => false,
		) );
	}

	/**
	 * Include custom schedules used for internal jobs
	 */
	public function register_internal_events_schedules( $schedules ) {
		return array_merge( $schedules, $this->internal_jobs_schedules );
	}

	/**
	 * Schedule internal jobs
	 */
	public function schedule_internal_events() {
		$when = strtotime( sprintf( '+%d seconds', 2 * $this->job_queue_window_in_seconds ) );

		foreach ( $this->internal_jobs as $job_args ) {
			if ( ! wp_next_scheduled( $job_args['action'] ) ) {
				wp_schedule_event( $when, $job_args['schedule'], $job_args['action'] );
			}
		}
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
			return rest_ensure_response( array( 'events' => null, ) );
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
					if ( $this->is_blocked_event( $action ) ) {
						wp_unschedule_event( $timestamp, $action, $instance_args['args'] );
						continue;
					}

					// Queue internal events separately to avoid them being blocked
					$queue = $this->is_internal_event( $action ) ? 'internal_events' : 'current_events';

					array_push( $$queue, array(
						'timestamp' => $timestamp,
						'action'    => md5( $action ),
						'instance'  => $instance,
					) );
				}
			}
		}

		// Limit batch size to avoid resource exhaustion
		if ( count( $current_events ) > $this->job_queue_size ) {
			$current_events = array_slice( $current_events, 0, $this->job_queue_size );
		}

		return rest_ensure_response( array(
			'events'          => $current_events,
			'internal_events' => $internal_events,
			'endpoint'        => get_rest_url( null, $this->namespace . '/event/' ),
		) );
	}

	/**
	 * Execute a specific event
	 */
	public function run_event( $request ) {
		// Parse request
		$event     = $request->get_json_params();
		$timestamp = isset( $event['timestamp'] ) ? absint( $event['timestamp'] ) : null;
		$action    = isset( $event['action'] ) ? trim( sanitize_text_field( $event['action'] ) ) : null;
		$instance  = isset( $event['instance'] ) ? trim( sanitize_text_field( $event['instance'] ) ) : null;
		unset( $event );

		// Validate input data
		if ( empty( $timestamp ) || empty( $action ) || empty( $instance ) ) {
			return new WP_Error( 'missing-data', __( 'Invalid or incomplete request data.', 'wp-cron-control-revisited' ) );
		}

		// Ensure we don't run jobs too far ahead
		if ( $timestamp > strtotime( sprintf( '+%d seconds', $this->job_execution_buffer_in_seconds ) ) ) {
			return new WP_Error( 'premature', __( 'Event is not scheduled to be run yet.', 'wp-cron-control-revisited' ) );
		}

		// Find the event to retrieve the full arguments
		$event = $this->get_event( $timestamp, $action, $instance );
		unset( $timestamp, $action, $instance );

		// Nothing to do...
		if ( ! is_array( $event ) ) {
			return new WP_Error( 'no-event', __( 'The specified event could not be found.', 'wp-cron-control-revisited' ) );
		}

		// And we're off!
		$time_start = microtime( true );

		// Limit how many events are processed concurrently
		if ( ! $this->is_internal_event( $event['action'] ) && ! $this->check_lock() ) {
			return new WP_Error( 'no-free-threads', __( 'No resources available to run this job.', 'wp-cron-control-revisited' ) );
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

		// Run the event
		do_action_ref_array( $event['action'], $event['args'] );

		// Free process for the next event
		if ( ! $this->is_internal_event( $event['action'] ) ) {
			$this->free_lock();
		}

		$time_end = microtime( true );

		return rest_ensure_response( array(
			'success' => true,
			'message' => sprintf( __( 'Job with action `%1$s` and arguments `%2$s` completed in %3$d seconds.', 'wp-cron-control-revisited' ), $event['action'], serialize( $event['args'] ), $time_end - $time_start ),
		) );
	}

	/**
	 * Find an event's data using its hashed representations
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
	 * Check if request is authorized
	 */
	public function check_secret( $request ) {
		$body = $request->get_json_params();

		// For now, mimic original plugin's "authentication" method. This needs to be better.
		if ( ! isset( $body['secret'] ) || ! hash_equals( $this->secret, $body['secret'] ) ) {
			return new WP_Error( 'no-secret', __( 'Secret must be specified with all requests', 'wp-cron-control-revisited' ) );
		}

		return true;
	}

	/**
	 * Events that are always run, regardless of how many jobs are queued
	 */
	private function is_internal_event( $action ) {
		return in_array( $action, wp_list_pluck( $this->internal_jobs, 'action' ) );
	}

	/**
	 * Allow specific events to be blocked perpetually
	 */
	private function is_blocked_event( $action ) {
		$blocked_hooks = array();

		return in_array( $action, $blocked_hooks );
	}

	/**
	 * Set a lock and limit how many concurrent jobs are permitted
	 */
	private function check_lock() {
		// Prevent deadlock
		if ( (int) wp_cache_get( $this->cache_key_lock_timestamp, null, true ) < time() - $this->job_timeout_in_minutes * MINUTE_IN_SECONDS ) {
			wp_cache_set( $this->cache_key_lock, 0 );
			wp_cache_set( $this->cache_key_lock_timestamp, time() );
			return true;
		}

		// Check if process can run
		if ( (int) wp_cache_get( $this->cache_key_lock, null, true ) >= $this->job_concurrency_limit ) {
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
		if ( (int) wp_cache_get( $this->cache_key_lock, null, true ) > 1 ) {
			wp_cache_decr( $this->cache_key_lock );
		} else {
			wp_cache_set( $this->cache_key_lock, 0 );
		}

		wp_cache_set( $this->cache_key_lock_timestamp, time() );

		return true;
	}

	/**
	 * Published scheduled posts that miss their schedule
	 */
	public function force_publish_missed_schedules() {
		global $wpdb;

		$missed_posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'future' AND post_date <= %s LIMIT 100;", current_time( 'mysql', false ) ) );

		if ( is_array( $missed_posts ) && ! empty( $missed_posts ) ) {
			foreach ( $missed_posts as $missed_post ) {
				check_and_publish_future_post( $missed_post );

				do_action( 'wpccr_published_post_that_missed_schedule', $missed_post );
			}
		}
	}

	/**
	 * Ensure scheduled posts have a corresponding cron job to publish them
	 */
	public function confirm_scheduled_posts() {
		global $wpdb;

		$future_posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM {$wpdb->posts} WHERE post_status = 'future' AND post_date > %s LIMIT 100;", current_time( 'mysql', false ) ) );

		if ( is_array( $future_posts ) && ! empty( $future_posts ) ) {
			foreach ( $future_posts as $future_post ) {
				$future_post->ID = absint( $future_post->ID );
				$gmt_time        = strtotime( get_gmt_from_date( $future_post->post_date ) . ' GMT' );
				$timestamp       = wp_next_scheduled( 'publish_future_post', array( $future_post->ID ) );

				if ( false === $timestamp ) {
					wp_schedule_single_event( $gmt_time, 'publish_future_post', array( $future_post->ID ) );

					do_action( 'wpccr_publish_scheduled', $future_post->ID );
				} elseif ( (int) $timestamp !== $gmt_time ) {
					wp_clear_scheduled_hook( 'publish_future_post', array( (int) $future_post->ID ) );
					wp_schedule_single_event( $gmt_time, 'publish_future_post', array( $future_post->ID ) );

					do_action( 'wpccr_publish_rescheduled', $future_post->ID );
				}
			}
		}
	}
}

WP_Cron_Control_Revisited::instance();
