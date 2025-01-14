<?php
/**
 * Plugin's REST API
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

/**
 * REST API class
 */
class REST_API extends Singleton {
	/**
	 * API SETUP
	 */
	const API_NAMESPACE = 'cron-control/v1';
	const ENDPOINT_LIST = 'events';
	const ENDPOINT_RUN  = 'event';

	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Register hooks
	 */
	protected function class_init() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Register API routes
	 */
	public function rest_api_init() {
		register_rest_route(
			self::API_NAMESPACE,
			'/' . self::ENDPOINT_LIST,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_events' ),
				'permission_callback' => array( $this, 'check_secret' ),
				'show_in_index'       => false,
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/' . self::ENDPOINT_RUN,
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'run_event' ),
				'permission_callback' => array( $this, 'check_secret' ),
				'show_in_index'       => false,
			)
		);
	}

	/**
	 * List events pending for the current period
	 *
	 * For monitoring and alerting, also provides the total number of pending events
	 */
	public function get_events() {
		$response_array = array(
			'events'               => array(),
			'orchestrate_disabled' => Events::instance()->run_disabled(),
		);

		// Include events only when automatic execution is enabled.
		if ( 0 === $response_array['orchestrate_disabled'] ) {
			$response_array = array_merge( $response_array, Events::instance()->get_events() );
		}

		$response_array['endpoint'] = get_rest_url( null, self::API_NAMESPACE . '/' . self::ENDPOINT_RUN );

		// Provide pending event count for monitoring etc.
		$response_array['total_events_pending'] = count_events_by_status( Events_Store::STATUS_PENDING );

		return rest_ensure_response( $response_array );
	}

	/**
	 * Execute a specific event
	 *
	 * @param object $request REST API request object.
	 * @return object
	 */
	public function run_event( $request ) {
		// Stop if event execution is .
		$run_disabled = Events::instance()->run_disabled();
		if ( 0 !== $run_disabled ) {
			if ( 1 === $run_disabled ) {
				$message = __( 'Automatic event execution is disabled indefinitely.', 'automattic-cron-control' );
			} else {
				/* translators: 1: Time automatic execution is disabled until, 2: Unix timestamp */
				$message = sprintf( __( 'Automatic event execution is disabled until %1$s UTC (%2$d).', 'automattic-cron-control' ), date_i18n( TIME_FORMAT, $run_disabled ), $run_disabled );
			}

			return rest_ensure_response(
				new \WP_Error(
					'automatic-execution-disabled',
					$message,
					array(
						'status' => 403,
					)
				)
			);
		}

		// Parse request for details needed to identify the event to execute.
		// `$timestamp` is, unsurprisingly, the Unix timestamp the event is scheduled for.
		// `$action` is the md5 hash of the action used when the event is registered.
		// `$instance` is the md5 hash of the event's arguments array, which Core uses to index the `cron` option.
		$event     = $request->get_json_params();
		$timestamp = isset( $event['timestamp'] ) ? absint( $event['timestamp'] ) : null;
		$action    = isset( $event['action'] ) ? trim( sanitize_text_field( $event['action'] ) ) : null;
		$instance  = isset( $event['instance'] ) ? trim( sanitize_text_field( $event['instance'] ) ) : null;

		return rest_ensure_response( run_event( $timestamp, $action, $instance ) );
	}

	/**
	 * Check if request is authorized
	 *
	 * @param object $request REST API request object.
	 * @return bool|\WP_Error
	 */
	public function check_secret( $request ) {
		if ( false === \WP_CRON_CONTROL_SECRET ) {
			return new \WP_Error(
				'api-disabled',
				__( 'Cron Control REST API endpoints are disabled', 'automattic-cron-control' ),
				array(
					'status' => 403,
				)
			);
		}

		$body = $request->get_json_params();

		// For now, mimic original plugin's "authentication" method. This needs to be better.
		if ( ! isset( $body['secret'] ) || ! hash_equals( \WP_CRON_CONTROL_SECRET, $body['secret'] ) ) {
			return new \WP_Error(
				'no-secret',
				__( 'Secret must be specified with all requests', 'automattic-cron-control' ),
				array(
					'status' => 400,
				)
			);
		}

		return true;
	}
}
