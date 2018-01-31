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
	const API_NAMESPACE = 'cache-manager/v1';
	const ENDPOINT_PURGE = 'purge'; // purge sites
	const ENDPOINT_BAN = 'ban'; // ban resources

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
			'permission_callback' => array( $this, 'check_secret' ),
			'show_in_index'       => false,
		) );

		register_rest_route( self::API_NAMESPACE, '/' . self::ENDPOINT_BAN, array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'debug_request' ),
			'permission_callback' => array( $this, 'check_secret' ),
			'show_in_index'       => false,
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
	public function purge_urls( $request ) {

		// MEGATODO: here we will call either:
		// - To purge:
		//   WPCOM_VIP_Cache_Manager::instance()->queue_purge_url( $url );
		// - To ban:
		//   To be defined. If we really really wants to pursue this.
		//
		// Check and sanitize input before doing anything!
		$urls_to_purge = $json_params = $request->get_json_params()['urls'];

		foreach ($urls_to_purge as $url) {
			wpcom_vip_purge_edge_cache_for_url($url);
		}

		//return rest_ensure_response( $response_array );
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
