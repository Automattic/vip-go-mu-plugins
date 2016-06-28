<?php
/*
 Plugin Name: VIP REST API Endpoints
 Plugin URI: https://vip.wordpress.com/
 Description: Add custom REST API endpoints for VIP requests
 Author: Erick Hitter, Automattic
 Version: 0.1
 */

class WPCOM_VIP_REST_API_Endpoints {
	/**
	 * SINGLETON
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
	private $namespace = 'wpcom-vip/v1';

	/**
	 * Register hooks
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Register API routes
	 */
	public function rest_api_init() {
		register_rest_route( $this->namespace, '/sites/', array(
			'methods' => 'GET',
			'callback' => array( $this, 'list_sites' ),
		) );
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 *
	 */
	public function list_sites() {
		$sites = array();

		if ( is_multisite() ) {
			//
		} else {
			$sites[] = array(
				'domain_name' => parse_url( home_url(), PHP_URL_HOST ),
			);
		}

		$response = new WP_REST_Response( $this->format_response( $sites ) );
		return $response;
	}

	/**
	 * Mimic response format from VIP Go API
	 *
	 * Consistency simplifies incorporating data from the VIP Go API with this API
	 */
	private function format_response( $data ) {
		$response = array();

		if ( is_wp_error( $data ) ) {
			$response['status'] = 'error';
			$response['data']   = array();
		} else {
			$response['status'] = 'success';
			$response['page']      = 1;
			$response['pagesize']  = 1000;
			$response['totalrecs'] = count( $data );
			$response['result']    = $response['totalrecs'];

			$response['data'] = $data;
		}

		return $response;
	}
}

WPCOM_VIP_REST_API_Endpoints::instance();
