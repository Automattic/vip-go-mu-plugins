<?php

namespace WP_Cron_Control_Revisited;

class REST_API {
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
	 * Register hooks
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Register API routes
	 */
	public function rest_api_init() {
		register_rest_route( Main::instance()->namespace, '/events/', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_events' ),
			'permission_callback' => array( $this, 'check_secret' ),
			'show_in_index'       => false,
		) );

		register_rest_route( Main::instance()->namespace, '/event/', array(
			'methods'             => 'PUT',
			'callback'            => array( $this, 'run_event' ),
			'permission_callback' => array( $this, 'check_secret' ),
			'show_in_index'       => false,
		) );
	}

	/**
	 * List events pending for the current period
	 */
	public function get_events() {
		return rest_ensure_response( Main::instance()->get_events() );
	}

	/**
	 * Execute a specific event
	 */
	public function run_event( $request ) {
		// Parse request for details needed to identify the event to execute
		// `$timestamp` is, unsurprisingly, the Unix timestamp the event is scheduled for
		// `$action` is the md5 hash of the action used when the event is registered
		// `$instance` is the md5 hash of the event's arguments array, which Core uses to index the `cron` option
		$event     = $request->get_json_params();
		$timestamp = isset( $event['timestamp'] ) ? absint( $event['timestamp'] ) : null;
		$action    = isset( $event['action'] ) ? trim( sanitize_text_field( $event['action'] ) ) : null;
		$instance  = isset( $event['instance'] ) ? trim( sanitize_text_field( $event['instance'] ) ) : null;

		return rest_ensure_response( Main::instance()->run_event( $timestamp, $action, $instance ) );
	}

	/**
	 * Check if request is authorized
	 */
	public function check_secret( $request ) {
		$body = $request->get_json_params();

		// For now, mimic original plugin's "authentication" method. This needs to be better.
		if ( ! isset( $body['secret'] ) || ! hash_equals( Main::instance()->secret, $body['secret'] ) ) {
			return new WP_Error( 'no-secret', __( 'Secret must be specified with all requests', 'wp-cron-control-revisited' ) );
		}

		return true;
	}
}

REST_API::instance();
