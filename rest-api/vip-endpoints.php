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
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_sites' ),
			'permission_callback' => function() {
				return wpcom_vip_go_rest_api_request_allowed( $this->namespace );
			},
		) );

		register_rest_route( $this->namespace, '/plugins/', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_plugins' ),
			'permission_callback' => function() {
				return wpcom_vip_go_rest_api_request_allowed( $this->namespace );
			},
		) );

		add_filter( 'rest_authentication_errors', array( $this, 'force_authorized_access' ), 999 ); // hook in late to bypass any others that override our auth requirements
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Some `/vip/` endpoints need to be accessible to requests from WordPress.com
	 */
	public function force_authorized_access( $result ) {
		if ( 0 === strpos( $_SERVER['REQUEST_URI'], '/wp-json/vip/v1/sites' ) && wpcom_vip_go_rest_api_request_allowed( $this->namespace ) ) {
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
				'public'   => null,
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

	/**
	 * Build list of active plugins on site ()
	 */
	public function list_plugins() {
		$all_plugins = array();
		$standard_plugins = array();
		$shared_plugins = array();
		$mu_plugins = array();
		$client_mu_plugins = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// array of all standard plugins
		$standard_plugins = get_plugins();
		$tmp_plugins = array();
		foreach ( $standard_plugins as $key => $plugin ) {
			if ( is_plugin_active( $key ) ) {
				$tmp_plugins[ $key ] = array(
					'name' => $plugin['Name'],
					'version' => $plugin['Version'],
					'description' => $plugin['Description'],
					'type' => 'standard',
				);
			}
		}
		$all_plugins['standard'] = $tmp_plugins;

		// array of all mu plugins
		$mu_plugins = get_mu_plugins();
		$tmp_plugins = array();
		foreach ( $mu_plugins as $key => $plugin ) {
			$tmp_plugins[ $key ] = array(
				'name' => $plugin['Name'],
				'version' => $plugin['Version'],
				'description' => $plugin['Description'],
				'type' => 'mu-plugin',
			);
		}
		$all_plugins['mu-plugin'] = $tmp_plugins;

		// array of all client mu plugins
		$client_mu_plugins = wpcom_vip_get_client_mu_plugins();
		$tmp_plugins = array();
		foreach ( $client_mu_plugins as $key => $plugin ) {
			$tmp_plugins[ $key ] = array(
				'name' => $plugin['Name'],
				'version' => $plugin['Version'],
				'description' => $plugin['Description'],
				'type' => 'client-mu-plugin',
			);
		}
		$all_plugins['client-mu-plugin'] = $tmp_plugins;

		// array of all shared plugins (activated via code and via UI)
		if ( class_exists( 'WPCOM_VIP_Plugins_UI' ) ) {
			$tmp_ui_plugins = array();
			$tmp_code_plugins = array();
			$vip_plugins = WPCOM_VIP_Plugins_UI::instance();
			$shared_plugins = $vip_plugins->get_shared_plugins();

			foreach ( $vip_plugins->get_shared_plugins() as $key => $plugin ) {
				if ( $active_plugin_type = $vip_plugins->is_plugin_active( basename( dirname( $key ) ) ) ) {
					if ( 'manual' === $active_plugin_type ) {
						$tmp_code_plugins[ $key ] = array(
							'name' => $plugin['Name'],
							'version' => $plugin['Version'],
							'description' => $plugin['Description'],
							'type' => 'vip-shared-code',
						);
					} else {
						$tmp_ui_plugins[ $key ] = array(
							'name' => $plugin['Name'],
							'version' => $plugin['Version'],
							'description' => $plugin['Description'],
							'type' => 'vip-shared-ui',
						);
					}
				}
			}
			$all_plugins['vip-shared-code'] = $tmp_code_plugins;
			$all_plugins['vip-shared-ui'] = $tmp_ui_plugins;
		}

		return new WP_REST_Response( $all_plugins );
	}
}

WPCOM_VIP_REST_API_Endpoints::instance();
