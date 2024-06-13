<?php
/**
 * Integrations Configuration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

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

				if ( ! isset( $this->configs[ $type ] ) ) {
					$this->configs[ $type ] = [];
				}

				$this->configs[ $type ][] = $config;
			}
		}
	}

	/**
	 * Get integration configuration provided by VIP.
	 *
	 * @param string $slug A unique identifier for the integration.
	 *
	 * @return array
	 */
	public function get_vip_configs( string $slug ): array {
		return $this->configs[ $slug ] ?? [];
	}
}
