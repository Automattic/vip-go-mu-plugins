<?php
/*
 Plugin Name: WP-Cron Control Revisited
 Plugin URI:
 Description: Take control of wp-cron execution.
 Author: Erick Hitter, Automattic
 Version: 0.1
 Text Domain: wp-cron-control-revisited
 */

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

	/**
	 * Register hooks
	 */
	private function __construct() {
		// Block normal cron execution
		define( 'DISABLE_WP_CRON', true );
		define( 'ALTERNATE_WP_CRON', false );

		add_action( 'muplugins_loaded', array( $this, 'block_direct_cron' ) );
		remove_action( 'init', 'wp_cron' );

		add_filter( 'cron_request', array( $this, 'block_spawn_cron' ) );

		// Load plugin functionality, when conditions are met
		if ( defined( 'WP_CRON_CONTROL_SECRET' ) ) {
			$this->secret = WP_CRON_CONTROL_SECRET;

			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		}
	}

	/**
	 * Block direct cron execution as early as possible
	 */
	public function block_direct_cron() {
		if ( false !== strpos( $_SERVER['REQUEST_URI'], '/wp-cron.php' ) ) {
			status_header( 403 );
			exit;
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
	 * Display an error if the plugin's conditions aren't met
	 */
	public function admin_notice() {
		$error = sprintf( __( '<strong>%1$s</strong>: To use this plugin, define the constant %2$s.', 'wp-cron-control-revisited' ), 'WP-Cron Control Revisited', '<code>WP_CRON_CONTROL_SECRET</code>' );

		?>
		<div class="notice notice-error">
			<p><?php echo $error; ?></p>
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
		$current_events = array();
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
					$current_events[] = array(
						'timestamp' => $timestamp,
						'action'    => md5( $action ),
						'instance'  => $instance,
					);
				}
			}
		}

		// Limit batch size to avoid resource exhaustion
		if ( count( $current_events ) > $this->job_queue_size ) {
			$current_events = array_slice( $current_events, 0, $this->job_queue_size );
		}

		return rest_ensure_response( array(
			'events'   => $current_events,
			'endpoint' => get_rest_url( null, $this->namespace . '/event/' ),
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
			return new WP_Error( 'missing-data', __( 'Invalid or incomplete request data', 'wp-cron-control-revisited' ) );
		}

		// Ensure we don't run jobs too far ahead
		if ( $timestamp > strtotime( sprintf( '+%d seconds', $this->job_execution_buffer_in_seconds ) ) ) {
			return new WP_Error( 'premature', __( 'Event is not scheduled to be run yet.', 'wp-cron-control-revisited' ) );
		}

		// Find the event to retrieve the full arguments
		$event = $this->get_event( $timestamp, $action, $instance );

		if ( is_array( $event ) ) {
			// Prepare environment to run job
			ignore_user_abort( true );
			set_time_limit( $this->job_timeout_in_minutes * MINUTE_IN_SECONDS );
			define( 'DOING_CRON', true );

			// Remove the event, and reschedule if desired
			// Follows pattern Core uses in wp-cron.php
			if ( false !== $event['schedule'] ) {
				wp_reschedule_event( $timestamp, $event['schedule'], $action, $event['args'] );
			}

			wp_unschedule_event( $timestamp, $action, $event['args'] );

			// Run the event
			do_action_ref_array( $action, $event['args'] );

			return rest_ensure_response( true );
		} else {
			return new WP_Error( 'no-event', __( 'The specified event could not be found.', 'wp-cron-control-revisited' ) );
		}
	}

	/**
	 * Check if request is authorized
	 */
	public function check_secret( $request ) {
		$body = $request->get_json_params();

		// For now, mimic original plugin's "authentication" method. This needs to be better.
		if ( ! isset( $body['secret'] ) || $this->secret !== $body['secret'] ) {
			return new WP_Error( 'no-secret', __( 'Secret must be specified with all requests', 'wp-cron-control-revisited' ) );
		}

		return true;
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
					$event = $action_events[ $instance ];
					break;
				}
			}
		}

		return $event;
	}
}

WP_Cron_Control_Revisited::instance();
