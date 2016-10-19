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

	/**
	 * Register hooks
	 */
	private function __construct() {
		add_action( 'muplugins_loaded', array( $this, 'block_direct_cron' ) );

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
	 * Register API routes
	 */
	public function rest_api_init() {
		register_rest_route( $this->namespace, '/events/', array(
			'methods'   => 'GET',
			'callback' => array( $this, 'get_events' ),
		) );

		register_rest_route( $this->namespace, '/event/', array(
			'methods'   => 'PUT',
			'callback' => array( $this, 'run_event' ),
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
		// For now, mimic original plugin's "authentication" method. This needs to be better.
		if ( ! isset( $_GET[ $this->secret ] ) ) {
			return new WP_Error( 'no-secret', __( 'Secret must be specified with all requests', 'wp-cron-control-revisited' ) );
		}

		$events = get_option( 'cron' );

		// That was easy
		if ( ! is_array( $events ) || empty( $events ) ) {
			return new WP_REST_Response( array( 'events' => null, ) );
		}

		// Select only those events to run in the next sixty seconds
		// Will include missed events as well
		$current_events = array();
		$current_window = strtotime( '+60 seconds' );

		foreach ( $events as $timestamp => $timestamp_events ) {
			// Skip non-event data that Core includes in the option
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}

			// Skip events whose time hasn't come
			if ( $timestamp > $current_window ) {
				continue;
			}

			// Extract just the essentials needed to retrieve the full job later on
			foreach ( $timestamp_events as $action => $action_instances ) {
				foreach ( $action_instances as $instance => $instance_args ) {
					$current_events[] = array(
						'timestamp' => $timestamp,
						'action'    => $action,
						'instance'  => $instance,
					);
				}
			}
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
		// For now, mimic original plugin's "authentication" method. This needs to be better.
		if ( ! isset( $_GET[ $this->secret ] ) ) {
			return new WP_Error( 'no-secret', __( 'Secret must be specified with all requests', 'wp-cron-control-revisited' ) );
		}

		// Parse request
		$event     = $request->get_json_params();
		$timestamp = isset( $event['timestamp'] ) ? $event['timestamp'] : null;
		$action    = isset( $event['action'] ) ? $event['action'] : null;
		$instance  = isset( $event['instance'] ) ? $event['instance'] : null;

		// Validate input data
		if ( ! is_int( $timestamp ) || empty( $action ) || empty( $instance ) ) {
			return new WP_Error( 'missing-data', __( 'Invalid or incomplete request data', 'wp-cron-control-revisited' ) );
		}

		// Ensure we don't run jobs too far ahead
		if ( $timestamp > strtotime( '+15 seconds' ) ) {
			return new WP_Error( 'premature', __( 'Event is not scheduled to be run yet.', 'wp-cron-control-revisited' ) );
		}

		// Find the event to retrieve the full arguments
		$events = get_option( 'cron' );

		if ( isset( $events[ $timestamp ][ $action ][ $instance ] ) ) {
			define( 'DOING_CRON', true );

			$event_data = $events[ $timestamp ][ $action ][ $instance ];

			// Remove the event, and reschedule if desired
			// Follows pattern Core uses in wp-cron.php
			if ( false !== $event_data['schedule'] ) {
				wp_reschedule_event( $timestamp, $event_data['schedule'], $action, $event_data['args'] );
			}

			wp_unschedule_event( $timestamp, $action, $event_data['args'] );

			// Run the event
			do_action_ref_array( $action, $event_data['args'] );

			return rest_ensure_response( true );
		} else {
			return new WP_Error( 'no-event', __( 'The specified event could not be found.', 'wp-cron-control-revisited' ) );
		}
	}
}

WP_Cron_Control_Revisited::instance();