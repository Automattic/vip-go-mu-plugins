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
			if ( function_exists( 'get_sites' ) ) {
				$_sites = get_sites( array(
					'public'   => 1,
					'archived' => 0,
					'spam'     => 0,
					'deleted'  => 0,
					'fields'   => 'ids',
				) );
			} else {
				// Add support for 4.4 and 4.5, as `get_sites()` wasn't introduced until 4.6
				$_sites = array();
			}

			if ( is_array( $_sites ) ) {
				// Switch to the blog to ensure certain domain filtering is respected
				foreach ( $_sites as $_site ) {
					switch_to_blog( $_site );

					$sites[] = array(
						'domain_name' => parse_url( home_url(), PHP_URL_HOST ),
					);

					restore_current_blog();
				}
			} else {
				$sites = new WP_Error( 'no-sites-found', 'Failed to retrieve any sites for this multisite network.' );
			}
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
			$response['data']   = false;
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
