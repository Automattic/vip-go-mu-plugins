<?php
/**
 * Integrations Configuration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Org_Integration_Status;
use Env_Integration_Status;

/**
 * Class for managing configuration of integrations provided by VIP.
 *
 * @private
 */
class VipIntegrationsConfig {
	/**
	 * Instance of the class.
	 *
	 * @var VipIntegrationsConfig
	 */
	private static $instance = null;

	/**
	 * Configurations provided by VIP.
	 *
	 * @var array <string, array (
	 *   array (
	 *    'label'         => string,
	 *    'org'           => array<string, string>,
	 *    'env'           => array<string, mixed>,
	 *    'network_sites' => array<number, array<string, mixed>>,
	 *  ),
	 * )>
	 *
	 * @example
	 * array(
	 *   'block-data-api' => array(
	 *     array(
	 *       'env'        => array(
	 *         'status' => 'enabled',
	 *         'config'  => array(),
	 *       ),
	 *     ),
	 *   ),
	 *   'parsely' => array(
	 *     array(
	 *       'org'        => array( 'status' => 'blocked' ),
	 *       'env'        => array(
	 *         'status' => 'enabled',
	 *         'config'  => array(),
	 *       ),
	 *       'network_sites' => array (
	 *         1 => array (
	 *           'status' => 'disabled',
	 *           'config'  => array(),
	 *         ),
	 *         2 => array (
	 *           'status' => 'enabled',
	 *           'config'  => array(),
	 *         ),
	 *       ),
	 *     ),
	 *   ),
	 * );
	 */
	private array $configs = [];

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->read_configs();
	}

	/**
	 * Get instance of the class.
	 *
	 * @return VipIntegrationsConfig
	 */
	public static function get_instance(): VipIntegrationsConfig {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Read configs provided by VIP.
	 */
	private function read_configs(): void {
		$config_file_directory = defined( 'WPVIP_INTEGRATIONS_CONFIG_DIR' )
			? constant( 'WPVIP_INTEGRATIONS_CONFIG_DIR' )
			: ABSPATH . 'config/integrations-config';

		$file_names = scandir( $config_file_directory );

		foreach ( $file_names as $file_name ) {
			if ( '.' === $file_name || '..' === $file_name ) {
				continue;
			}

			$config_file_path = $config_file_directory . '/' . $file_name;
			
			/**
			 * Clear cache to always read data from latest config file.
			 *
			 * Kubernetes ConfigMap updates the file via symlink instead of actually replacing the file and
			 * PHP cache can hold a reference to the old symlink that can cause fatal if we use require
			 * on it.
			 */
			clearstatcache( true, $config_file_directory . '/' . $file_name );
			// Clears cache for files created by k8s ConfigMap.
			clearstatcache( true, $config_file_directory . '/..data' );
			clearstatcache( true, $config_file_directory . '/..data/' . $file_name );

			if ( ! is_readable( $config_file_path ) ) {
				continue;
			}

			$config = require $config_file_path;

			if ( is_array( $config ) && isset( $config['type'] ) ) {
				$type = $config['type'];
				unset( $config['type'] );

				if ( ! isset( $result[ $type ] ) ) {
					$this->configs[ $type ] = $config;
				}
			}
		}
	}

	/**
	 * Returns `true` if the integration is enabled in VIP config else `false`.
	 *
	 * @param string $slug A unique identifier for the integration.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function is_active_via_vip( string $slug ): bool {
		return Env_Integration_Status::ENABLED === $this->get_site_status( $slug );
	}

	/**
	 * Get site status.
	 *
	 * @param string $slug A unique identifier for the integration.
	 *
	 * @return string|null
	 */
	private function get_site_status( string $slug ): ?string {
		$vip_config = $this->get_vip_config( $slug );

		if ( $this->get_value_from_config( $vip_config, 'org', 'status' ) === Org_Integration_Status::BLOCKED ) {
			return Org_Integration_Status::BLOCKED;
		}

		// Look into network_sites config before and then fallback to env config.
		return $this->get_value_from_config( $vip_config, 'network_sites', 'status' ) ??
			$this->get_value_from_config( $vip_config, 'env', 'status' );
	}

	/**
	 * Get site config.
	 *
	 * @param string $slug A unique identifier for the integration.
	 *
	 * @return array
	 */
	public function get_site_config( string $slug ) {
		$vip_config = $this->get_vip_config( $slug );

		if ( is_multisite() ) {
			$config = $this->get_value_from_config( $vip_config, 'network_sites', 'config' );
			// If network site config is not found then fallback to env config if it exists.
			if ( empty( $config ) && true === $this->get_value_from_config( $config, 'env', 'cascade_config' ) ) {
				$config = $this->get_value_from_config( $vip_config, 'env', 'config' );
			}
		} else {
			$config = $this->get_value_from_config( $vip_config, 'env', 'config' );
		}

		return $config ?? array(); // To keep function signature consistent.
	}

	/**
	 * Get config value based on given type and key.
	 *
	 * @param array<string, array<string, mixed> $vip_config Configurations provided by VIP.
	 * @param string                             $config_type Type of the config whose data is needed i.e. org, env, network-sites etc.
	 * @param string                             $key Key of the config from which we have to extract the data.
	 *
	 * @return null|string|array Returns `null` if key is not found, `string` if key is "status" and `array` if key is "config".
	 */
	private function get_value_from_config( array $vip_config, string $config_type, string $key ) {

		if ( ! in_array( $config_type, [ 'org', 'env', 'network_sites' ], true ) ) {
			trigger_error( 'config_type param (' . esc_html( $config_type ) . ') must be one of org, env or network_sites.', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return null;
		}

		if ( ! isset( $vip_config[ $config_type ] ) ) {
			return null;
		}

		// Look for key inside org or env config.
		if ( 'network_sites' !== $config_type && isset( $vip_config[ $config_type ][ $key ] ) ) {
			return $vip_config[ $config_type ][ $key ];
		}

		// Look for key inside network-sites config.
		$network_site_id = get_current_blog_id();
		if ( 'network_sites' === $config_type && isset( $vip_config[ $config_type ][ $network_site_id ] ) ) {
			if ( isset( $vip_config[ $config_type ][ $network_site_id ][ $key ] ) ) {
				return $vip_config[ $config_type ][ $network_site_id ][ $key ];
			}
		}

		return null;
	}

	/**
	 * Get integration configuration provided by VIP.
	 *
	 * @param string $slug A unique identifier for the integration.
	 *
	 * @return array
	 */
	public function get_vip_config( string $slug ): array {
		return $this->configs[ $slug ] ?? [];
	}
}
