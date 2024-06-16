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
	 * Directory path where configuration files are stored.
	 *
	 * @var string
	 */
	private $config_file_dir = ABSPATH . 'config/integrations-config';

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
	public function __construct() {
		if ( defined( 'WPVIP_INTEGRATIONS_CONFIG_DIR' ) ) {
			$this->config_file_dir = constant( 'WPVIP_INTEGRATIONS_CONFIG_DIR' );
		}

		$this->configs = $this->read_config_files();
	}

	/**
	 * Read configs provided by VIP.
	 *
	 * @return array<string, array>
	 */
	private function read_config_files(): array {
		$file_names = $this->get_config_file_names();
		$configs    = [];

		foreach ( $file_names as $file_name ) {
			$config = $this->get_config_file_content( $file_name );

			if ( is_array( $config ) && isset( $config['type'] ) ) {
				$type = $config['type'];
				unset( $config['type'] );

				if ( ! isset( $configs[ $type ] ) ) {
					$configs[ $type ] = [];
				}

				$configs[ $type ][] = $config;
			}
		}

		return $configs;
	}

	/**
	 * Return names of config files from directory.
	 *
	 * @return array<string>
	 */
	protected function get_config_file_names(): array {
		$file_names = [];

		if ( is_dir( $this->config_file_dir ) ) {
			foreach ( scandir( $this->config_file_dir ) as $file_name ) {
				if ( '.' === $file_name || '..' === $file_name ) {
					continue;
				}

				$file_names[] = $file_name;
			}
		}

		return $file_names;
	}

	/**
	 * Get content of a config file.
	 *
	 * @param string $file_name Name of the config file.
	 *
	 * @return array|null
	 */
	protected function get_config_file_content( $file_name ) {
		$config_file_path = $this->config_file_dir . '/' . $file_name;
			
		/**
		 * Clear cache to always read data from latest config file.
		 *
		 * Kubernetes ConfigMap updates the file via symlink instead of actually replacing the file and
		 * PHP cache can hold a reference to the old symlink that can cause fatal if we use require
		 * on it.
		 */
		clearstatcache( true, $this->config_file_dir . '/' . $file_name );
		// Clears cache for files created by k8s ConfigMap.
		clearstatcache( true, $this->config_file_dir . '/..data' );
		clearstatcache( true, $this->config_file_dir . '/..data/' . $file_name );

		if ( ! is_readable( $config_file_path ) ) {
			return null;
		}

		return require $config_file_path;
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
