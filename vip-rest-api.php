<?php
/*
 Plugin Name: VIP REST API Endpoints
 Plugin URI: https://vip.wordpress.com/
 Description: Add custom REST API endpoints for VIP requests; N.B. these endpoints are subject to change without notice, and should be considered "private".
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
	private $namespace = 'vip/v1';

	private $cached_sites_list = 'wpcom-vip-sites-list';

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

		add_filter( 'rest_authentication_errors', array( $this, 'disable_auth' ), 999 ); // hook in late to bypass any others that override our auth requirements
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Some `/vip/` endpoints need to be accessible unauthenticated (for now).
	 *
	 * This will be replaced with a proper auth scheme in the near future.
	 */
	public function disable_auth( $result ) {
		if ( 0 === strpos( $_SERVER['REQUEST_URI'], '/wp-json/vip/v1/sites' ) ) {
			return true;
		}

		return $result;
	}

	/**
	 * Build list of sites on a multisite network
	 *
	 * For consistency, will also return result on single-site
	 */
	public function list_sites() {
		$sites = array();

		if ( is_multisite() ) {
			$_sites = get_sites( array(
				'public'   => 1,
				'archived' => 0,
				'spam'     => 0,
				'deleted'  => 0,
				'fields'   => 'ids',
			) );

			// Inflate raw list of site IDs, if available
			if ( is_array( $_sites ) ) {
				// Switch to the blog to ensure certain domain filtering is respected
				foreach ( $_sites as $_site ) {
					switch_to_blog( $_site );

					$url_parts = wp_parse_args( parse_url( home_url() ), array(
						'host' => '',
						'path' => '',
					) );

					$url = $url_parts['host'];

					if ( strlen( $url_parts['path'] ) > 1 ) {
						$url .= $url_parts['path'];

						$url = untrailingslashit( $url );
					}

					$sites[] = array(
						'domain_name' => $url,
					);

					unset( $url_parts, $url );

					restore_current_blog();
				}
			} else {
				$sites = new WP_Error( 'no-sites-found', 'Failed to retrieve any sites for this multisite network.' );
			}
		} else {
			// Provided for consistency, even though this provides no insightful response
			$sites[] = array(
				'domain_name' => parse_url( home_url(), PHP_URL_HOST ),
			);
		}

		return new WP_REST_Response( $sites );
	}
}

WPCOM_VIP_REST_API_Endpoints::instance();
