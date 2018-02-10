<?php
/**
 * Plugin's REST API
 *
 * @package a8c_Cache_Manager
 */

require_once( __DIR__ . '/class-singleton.php' );

/**
 * REST API class
 */
class REST_API extends Singleton {

	/**
	 * API SETUP
	 */
	const API_NAMESPACE  = 'cache-manager/v1';
	const ENDPOINT_PURGE = 'purge'; // purge URL(s)
	const ENDPOINT_BAN   = 'ban'; // ban URL(s)
	const URLS_KEY       = 'urls';

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

		register_rest_route( self::API_NAMESPACE, '/' . self::ENDPOINT_PURGE, array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'purge_urls' ),
			'permission_callback' => function() {
				return wpcom_vip_go_rest_api_request_allowed( self::API_NAMESPACE );
			},
			'exoskeleton' 				=> [ 'window' => 10, 'limit'	=> 3, 'lockout' => 10 ],
			'show_in_index'       => false
		) );

	}

	/**
	 * Testing function
	 */
	public function debug_request( $request ) {

		$json_params = $request->get_json_params()['urls'];

		var_dump($json_params);
	}

	/**
	 *
	 * Executes purge on all provided URLs
	 *
	 * @param request JSON, must contain an array url
	 */
	public function purge_urls( WP_REST_Request $request ) {

		// Set up response_array with defaults
		$response_array = array(
			'purged_urls'	=> array(),
			'response'		=> false
		);

		$json_params = $request->get_json_params();

		// Checks for existence of mandatory urls key
		if (array_key_exists( self::URLS_KEY, $json_params )) {
			$urls_to_purge = $json_params = $request->get_json_params()[ self::URLS_KEY ];
		} else {
			array_push( $response_array[ 'purged_urls' ], 'ERROR: Missing urls array' );
			return rest_ensure_response( $response_array );
		}

		// Actual purging process
		foreach ($urls_to_purge as $url) {
			$url = esc_url( $url );
			// We may also invoke API function here
			WPCOM_VIP_Cache_Manager::instance()->queue_purge_url( $url );
			array_push( $response_array['purged_urls'], $url );
		}

		// As purges have not been executed yet
		// and there is no way to know whether purging
		// will be successful or not,
		// we assume all is OK if we have made it so far
		$response_array['response'] = true;

		// Returns a REST response for sake of consistency
		return rest_ensure_response( $response_array );
	}

	/**
	 * Check if request is authorized
	 *
	 * @param object $request REST API request object.
	 * @return bool|\WP_Error
	 */
	public function check_secret( $request ) {
		$body = $request->get_json_params();

		// For now, mimic original plugin's "authentication" method. This needs to be better.
		if ( ! isset( $body['secret'] ) || ! hash_equals( \WP_CACHE_MANAGER_SECRET, $body['secret'] ) ) {
			return new \WP_Error( 'no-secret', __( 'Secret must be specified with all requests', 'automattic-cache-manager' ), array(
				'status' => 400,
			) );
		}

		return true;
	}
}

REST_API::instance();
