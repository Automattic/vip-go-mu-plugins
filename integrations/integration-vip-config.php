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
		$config_file_path_orig = $config_file_directory . '/' . $config_file_name;

		$config_file_path = apply_filters( 'vip_integrations_config_file_path', $config_file_path_orig, $slug );
		$config_data      = apply_filters( 'vip_integrations_pre_load_config', null, $config_file_path, $slug );

		if ( is_null( $config_data ) ) {
			if ( $config_file_path_orig === $config_file_path ) {
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
			}

			if ( ! is_readable( $config_file_path ) ) {
				return null;
			}

			$config_data = require $config_file_path;
		}

		return $config_data;
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
	 * Get integration status for site.
	 *
	 * For single sites simply return global status.
	 * For multisites, try to get status based on current blog id,
	 * if not found then fallback to global environment status.
	 *
	 * @return string|null
	 */
	public function get_site_status() {
		if ( $this->get_value_from_config( 'org', 'status' ) === Org_Integration_Status::BLOCKED ) {
			return Org_Integration_Status::BLOCKED;
		}

		if ( is_multisite() ) {
			$network_site_status = $this->get_value_from_config( 'network_sites', 'status' );

			if ( $network_site_status ) {
				return $network_site_status;
			}

			$env_config = $this->get_env_config();
			if ( isset( $env_config['network_wide_enable'] ) && 'false' === $env_config['network_wide_enable'] ) {
				// If network_wide_enable is false, then return disabled status (rather than inheriting) since $network_site_status wasn't found.
				return Env_Integration_Status::DISABLED;
			}
		}

		return $this->get_value_from_config( 'env', 'status' );
	}

	public function get_env_config(): array {
		$config = $this->get_value_from_config( 'env', 'config' );
		return is_array( $config ) ? $config : [];
	}

	public function get_network_site_config(): array {
		if ( ! is_multisite() ) {
			return [];
		}

		$config = $this->get_value_from_config( 'network_sites', 'config' );
		return is_array( $config ) ? $config : [];
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
		if ( ! isset( $this->config[ $config_type ] ) ) {
			return null;
		}

		// Look for key inside org or env config.
		if ( in_array( $config_type, [ 'env', 'org' ], true ) ) {
			return $this->config[ $config_type ][ $key ] ?? null;
		}

		// Look for key inside network-sites config.
		if ( 'network_sites' === $config_type ) {
			$network_site_id = get_current_blog_id();
			return $this->config[ $config_type ][ $network_site_id ][ $key ] ?? null;
		}

		return null;
	}
}
