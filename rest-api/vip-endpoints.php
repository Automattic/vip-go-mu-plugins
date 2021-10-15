<?php
/*
Plugin Name: VIP REST API Endpoints
Plugin URI: https://wpvip.com
Description: Add custom REST API endpoints for VIP requests; N.B. these endpoints are subject to change without notice, and should be considered "private".
Author: Erick Hitter, Automattic
Version: 0.1
*/

class WPCOM_VIP_REST_API_Endpoints {
	/**
	 * SINGLETON
	 */
	private static $instance = null;

	public static function instance() {
		if ( ! is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self();
		}

		return self::$instance;
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
				return wpcom_vip_go_rest_api_request_allowed( $this->namespace, 'manage_sites' );
			},
		) );

		register_rest_route( $this->namespace, '/plugins/', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_plugins' ),
			'permission_callback' => function() {
				return wpcom_vip_go_rest_api_request_allowed( $this->namespace );
			},
		) );

		register_rest_route( $this->namespace, '/jetpack/', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_jetpack_details' ),
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
	 * Ensure `/vip/` endpoints are accessible to authenticated requests
	 *
	 * Circumvents any other authentication blocks and gives our method preference
	 */
	public function force_authorized_access( $result ) {
		global $wp_rewrite;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		if ( $wp_rewrite->using_permalinks() ) {
			$rest_prefix = rest_get_url_prefix();

			// Expected request.
			$expected_namespace = get_rest_url( null, $this->namespace );
			$expected_namespace = trailingslashit( $expected_namespace );
			$expected_namespace = wp_parse_url( $expected_namespace, PHP_URL_PATH );

			// Actual request.
			$request_parts = explode( '/', $request_uri );

			// Drop undesirable leading bits to rebuild namespace from request.
			foreach ( $request_parts as $key => $part ) {
				if ( empty( $part ) || $rest_prefix === $part ) {
					unset( $request_parts[ $key ] );
				}
			}

			$request_parts = array_values( $request_parts );

			// Rebuild namespace from request as a basic check.
			$namespace = '';
			if ( isset( $request_parts[0] ) && isset( $request_parts[1] ) ) {
				$namespace = sprintf( '%1$s/%2$s', $request_parts[0], $request_parts[1] );
			}

			// Don't intercept requests not for our namespace.
			if ( $namespace !== $this->namespace ) {
				return $result;
			}

			$slashed_request = trailingslashit( $request_uri );

			if ( 0 === strpos( $slashed_request, $expected_namespace ) && wpcom_vip_go_rest_api_request_allowed( $this->namespace ) ) {
				return true;
			}
		} else {
			$query_args   = array();
			$query_string = wp_parse_url( $request_uri, PHP_URL_QUERY );
			wp_parse_str( $query_string, $query_args );

			if ( ! isset( $query_args['rest_route'] ) ) {
				return $result;
			}

			$requested_route    = trailingslashit( $query_args['rest_route'] );
			$expected_namespace = sprintf( '/%s/', $this->namespace );

			if ( 0 === strpos( $requested_route, $expected_namespace ) && wpcom_vip_go_rest_api_request_allowed( $this->namespace ) ) {
				return true;
			}
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

					$url_parts = wp_parse_args( wp_parse_url( home_url() ), array(
						'host' => '',
						'path' => '',
					) );

					$url = $url_parts['host'];

					if ( strlen( $url_parts['path'] ) > 1 ) {
						$url .= $url_parts['path'];

						$url = untrailingslashit( $url );
					}

					$sites[] = array(
						'ID'          => $_site,
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
				'ID'          => 1,
				'domain_name' => wp_parse_url( home_url(), PHP_URL_HOST ),
			);
		}

		return new WP_REST_Response( $sites );
	}

	/**
	 * Endpoint to return active plugins on site
	 */
	public function list_plugins() {
		$plugins = $this->get_all_plugins();

		return new WP_REST_Response( $plugins );
	}

	/**
	 * List Jetpack Cache site details.
	 *
	 * @return WP_REST_Response
	 */
	public function list_jetpack_details(): WP_REST_Response {
		$details = [];

		if ( is_multisite() ) {
			$_sites = get_sites( array(
				'public'   => null,
				'archived' => 0,
				'spam'     => 0,
				'deleted'  => 0,
				'fields'   => 'ids',
			) );

			if ( is_array( $_sites ) ) {
				// Switch to the blog to ensure certain domain filtering is respected.
				foreach ( $_sites as $_site ) {
					switch_to_blog( $_site );
					$details[] = $this->get_jetpack_details_for_site();

					restore_current_blog();
				}
			} else {
				$details = new WP_Error( 'no-sites-found', 'Failed to retrieve any sites for this multisite network.' );
			}
		} else {
			$details[] = $this->get_jetpack_details_for_site();
		}

		return new WP_REST_Response( $details );
	}

	/**
	 * Get Jetpack Cache Site ID, Home URL and the connection status for the current site.
	 *
	 * @return array
	 */
	protected function get_jetpack_details_for_site(): array {
		$connection = new Automattic\Jetpack\Connection\Manager();
		$data       = [
			'site_id'       => get_current_blog_id(),
			'cache_site_id' => Jetpack::get_option( 'id' ),
			'home_url'      => home_url(),
			'is_active'     => $connection->is_active(),
		];

		return $data;
	}

	/**
	 * Get all the plugins
	 */
	protected function get_all_plugins() {
		global $vip_loaded_plugins;
		$all_plugins = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// array of all standard plugins
		$standard_plugins = get_plugins();
		$tmp_plugins      = array();
		foreach ( $standard_plugins as $key => $plugin ) {
			$vip_plugin_slug = 'plugins/' . dirname( $key );
			$active          = is_plugin_active( $key );
			if ( $active || ! in_array( $vip_plugin_slug, $vip_loaded_plugins, true ) ) {
				$tmp_plugins[ $key ] = array(
					'name'        => $plugin['Name'],
					'version'     => $plugin['Version'],
					'description' => $plugin['Description'],
					'type'        => 'standard',
					'active'      => $active,
				);
			}
		}
		$all_plugins['standard'] = $tmp_plugins;

		// array of all code activated standard plugins
		$tmp_plugins = array();
		foreach ( $standard_plugins as $key => $plugin ) {
			$vip_plugin_slug = 'plugins/' . dirname( $key );
			if ( in_array( $vip_plugin_slug, $vip_loaded_plugins, true ) ) {
				$tmp_plugins[ $key ] = array(
					'name'        => $plugin['Name'],
					'version'     => $plugin['Version'],
					'description' => $plugin['Description'],
					'type'        => 'standard-code',
					'active'      => true,
				);
			}
		}
		$all_plugins['standard-code'] = $tmp_plugins;

		// array of all mu plugins
		$mu_plugins  = get_mu_plugins();
		$tmp_plugins = array();
		foreach ( $mu_plugins as $key => $plugin ) {
			$tmp_plugins[ $key ] = array(
				'name'        => $plugin['Name'],
				'version'     => $plugin['Version'],
				'description' => $plugin['Description'],
				'type'        => 'mu-plugin',
				'active'      => true,
			);
		}
		$all_plugins['mu-plugin'] = $tmp_plugins;

		// array of all client mu plugins
		$client_mu_plugins = wpcom_vip_get_client_mu_plugins_data();
		$tmp_plugins       = array();
		foreach ( $client_mu_plugins as $key => $plugin ) {
			$tmp_plugins[ $key ] = array(
				'name'        => $plugin['Name'],
				'version'     => $plugin['Version'],
				'description' => $plugin['Description'],
				'type'        => 'client-mu-plugin',
				'active'      => true,
			);
		}
		$all_plugins['client-mu-plugin'] = $tmp_plugins;

		// array of all shared plugins (activated via code and via UI)
		// once the remaining shared plugins are retired we can remove this section
		$tmp_ui_plugins   = array();
		$tmp_code_plugins = array();
		foreach ( get_plugins( '/../mu-plugins/shared-plugins' ) as $key => $plugin ) {
			$active_plugin_type = $this->legacy_is_plugin_active( basename( dirname( $key ) ) );
			if ( $active_plugin_type ) {
				$entry = [
					'name'        => $plugin['Name'],
					'version'     => $plugin['Version'],
					'description' => $plugin['Description'],
					'type'        => 'manual' === $active_plugin_type ? 'vip-shared-code' : 'vip-shared-ui',
					'active'      => true,
				];

				if ( 'manual' === $active_plugin_type ) {
					$tmp_code_plugins[ $key ] = $entry;
				} else {
					$tmp_ui_plugins[ $key ] = $entry;
				}
			}
		}
		$all_plugins['vip-shared-code'] = $tmp_code_plugins;
		$all_plugins['vip-shared-ui']   = $tmp_ui_plugins;

		// add constant to endpoint
		$all_plugins['disable-shared-plugins'] = ( defined( 'WPCOM_VIP_DISABLE_SHARED_PLUGINS' ) && true === WPCOM_VIP_DISABLE_SHARED_PLUGINS ) ? true : false;

		return $all_plugins;
	}

	protected function legacy_is_plugin_active( $plugin ) {
		$option = get_option( 'wpcom_vip_active_plugins', array() );
		if ( in_array( $plugin, $option, true ) ) {
			return 'option';
		} elseif ( in_array( 'shared-plugins/' . $plugin . '/' . $plugin . '.php', wpcom_vip_get_loaded_plugins(), true ) ) {
			return 'manual';
		} else {
			return false;
		}
	}
}

WPCOM_VIP_REST_API_Endpoints::instance();
