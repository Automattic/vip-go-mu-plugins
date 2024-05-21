<?php
/**
 * Integration Configuration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Org_Integration_Status;
use Env_Integration_Status;

/**
 * Class for managing configuration of integration provided by VIP.
 *
 * @private
 */
class IntegrationVipConfig {
	/**
	 * Configuration provided by VIP.
	 *
	 * @var array {
	 *   'org'           => array<string, string>,
	 *   'env'           => array<string, mixed>,
	 *   'network_sites' => array<string, array<number, mixed>>,
	 * }
	 *
	 * @example
	 * array(
	 *  'org'        => array( 'status' => 'blocked' ),
	 *  'env'        => array(
	 *      'status' => 'enabled',
	 *      'config'  => array(),
	 *   ),
	 *  'network_sites' => array (
	 *      1 => array (
	 *          'status' => 'disabled',
	 *          'config'  => array(),
	 *      ),
	 *      2 => array (
	 *          'status' => 'enabled',
	 *          'config'  => array(),
	 *      ),
	 *  )
	 * );
	 */
	private array $config = [];

	/**
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		$this->set_config( $slug );
	}

	/**
	 * Set config provided by VIP from file.
	 *
	 * @param string $slug A unique identifier for the integration.
	 */
	private function set_config( string $slug ): void {
		$config = $this->get_vip_config_from_file( $slug );

		if ( ! is_array( $config ) ) {
			return;
		}

		$this->config = $config;
	}

	/**
	 * Get config provided by VIP from file.
	 *
	 * @param string $slug A unique identifier for the integration.
	 *
	 * @return null|mixed
	 */
	protected function get_vip_config_from_file( string $slug ) {
		$config_file_directory = defined( 'WPVIP_INTEGRATIONS_CONFIG_DIR' )
			? constant( 'WPVIP_INTEGRATIONS_CONFIG_DIR' )
			: ABSPATH . 'config/integrations-config';
		$config_file_name      = $slug . '-config.php';
		$config_file_path      = $config_file_directory . '/' . $config_file_name;

		/**
		 * Clear cache to always read data from latest config file.
		 *
		 * Kubernetes ConfigMap updates the file via symlink instead of actually replacing the file and
		 * PHP cache can hold a reference to the old symlink that can cause fatal if we use require
		 * on it.
		 */
		clearstatcache( true, $config_file_directory . '/' . $config_file_name );
		// Clears cache for files created by k8s ConfigMap.
		clearstatcache( true, $config_file_directory . '/..data' );
		clearstatcache( true, $config_file_directory . '/..data/' . $config_file_name );

		if ( ! is_readable( $config_file_path ) ) {
			return null;
		}

		return require $config_file_path;
	}

	/**
	 * Returns `true` if the integration is enabled in VIP config else `false`.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function is_active_via_vip(): bool {
		return Env_Integration_Status::ENABLED === $this->get_site_status();
	}

	/**
	 * Get site status.
	 *
	 * @return string|null
	 *
	 * @private
	 */
	public function get_site_status() {
		if ( $this->get_value_from_config( 'org', 'status' ) === Org_Integration_Status::BLOCKED ) {
			return Org_Integration_Status::BLOCKED;
		}

		// Look into network_sites config before and then fallback to env config.
		return $this->get_value_from_config( 'network_sites', 'status' ) ??
			$this->get_value_from_config( 'env', 'status' );
	}

	/**
	 * Get site config.
	 *
	 * @return array
	 */
	public function get_site_config() {
		if ( is_multisite() ) {
			$config = $this->get_value_from_config( 'network_sites', 'config' );
			// If network site config is not found then fallback to env config if it exists
			if ( empty( $config ) && true === $this->get_value_from_config( 'env', 'cascade_config' ) ) {
				$config = $this->get_value_from_config( 'env', 'config' );
			}
		} else {
			$config = $this->get_value_from_config( 'env', 'config' );
		}

		return $config ?? array(); // To keep function signature consistent.
	}

	/**
	 * Get config value based on given type and key.
	 *
	 * @param string $config_type Type of the config whose data is needed i.e. org, env, network-sites etc.
	 * @param string $key Key of the config from which we have to extract the data.
	 *
	 * @return null|string|array Returns `null` if key is not found, `string` if key is "status" and `array` if key is "config".
	 */
	protected function get_value_from_config( string $config_type, string $key ) {
		if ( ! in_array( $config_type, [ 'org', 'env', 'network_sites' ], true ) ) {
			trigger_error( 'config_type param (' . esc_html( $config_type ) . ') must be one of org, env or network_sites.', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return null;
		}

		if ( ! isset( $this->config[ $config_type ] ) ) {
			return null;
		}

		// Look for key inside org or env config.
		if ( 'network_sites' !== $config_type && isset( $this->config[ $config_type ][ $key ] ) ) {
			return $this->config[ $config_type ][ $key ];
		}

		// Look for key inside network-sites config.
		$network_site_id = get_current_blog_id();
		if ( 'network_sites' === $config_type && isset( $this->config[ $config_type ][ $network_site_id ] ) ) {
			if ( isset( $this->config[ $config_type ][ $network_site_id ][ $key ] ) ) {
				return $this->config[ $config_type ][ $network_site_id ][ $key ];
			}
		}

		return null;
	}
}
