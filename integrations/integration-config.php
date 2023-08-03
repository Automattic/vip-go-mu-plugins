<?php
/**
 * IntegrationConfig.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Client_Integration_Status;
use InvalidArgumentException;
use Site_Integration_Status;

/**
 * Class for managing configuration of integration provided by VIP.
 *
 * @private
 */
class IntegrationConfig {
	/**
	 * Configuration provided by VIP.
	 *
	 * @var array {
	 *   'client'        => array<string, string>,
	 *   'site'          => array<string, mixed>,
	 *   'network_sites' => array<string, array<number, mixed>>,
	 * }
	 *
	 * @example
	 * array(
	 *  'client'        => array( 'status' => 'blocked' ),
	 *  'site'          => array(
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
		$config_file_path = ABSPATH . 'config/integrations-config/' . $slug . '-config.php';

		if ( ! is_readable( $config_file_path ) ) {
			return null;
		}

		return require_once $config_file_path;
	}

	/**
	 * Returns `true` if the integration is enabled in VIP config else `false`.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function is_active_via_vip(): bool {
		// Return false if blocked on client.
		if ( $this->get_value_from_config( 'client', 'status' ) === Client_Integration_Status::BLOCKED ) {
			return false;
		}

		$site_status = $this->get_value_from_config( 'site', 'status' );

		// Return false if blocked on site.
		if ( Site_Integration_Status::BLOCKED === $site_status ) {
			return false;
		}

		// Look into network_sites config before because if not present we will fallback to site config.
		$network_site_status = $this->get_value_from_config( 'network_sites', 'status' );

		if ( Site_Integration_Status::ENABLED === $network_site_status ) {
			return true;
		}

		// Return false if status is defined but other than enabled. If status is not defined then fallback to site config.
		if ( null !== $network_site_status ) {
			return false;
		}

		// Return true if enabled on site.
		return Site_Integration_Status::ENABLED === $site_status;
	}

	/**
	 * Get site config.
	 *
	 * @return null|array
	 *
	 * @private
	 */
	public function get_site_config() {
		if ( is_multisite() ) {
			return $this->get_value_from_config( 'network_sites', 'config' );
		}

		return $this->get_value_from_config( 'site', 'config' );
	}

	/**
	 * Get config value based on given type and key.
	 *
	 * @param string $config_type Type of the config whose data is needed i.e. client, site, network-sites etc.
	 * @param string $key Key of the config from which we have to extract the data.
	 *
	 * @return null|array
	 *
	 * @throws InvalidArgumentException Exception if invalid argument is passed.
	 */
	protected function get_value_from_config( string $config_type, string $key ) {
		if ( ! in_array( $config_type, [ 'client', 'site', 'network_sites' ], true ) ) {
			throw new InvalidArgumentException( 'Config type must be one of client, site and network_sites.' );
		}

		if ( ! isset( $this->config[ $config_type ] ) ) {
			return null;
		}

		// Look for key inside client or site config.
		if ( 'network_sites' !== $config_type && isset( $this->config[ $config_type ][ $key ] ) ) {
			return $this->config[ $config_type ][ $key ];
		}

		// Look for key inside network-sites config.
		$blog_id = get_current_blog_id();
		if ( 'network_sites' === $config_type && isset( $this->config[ $config_type ][ $blog_id ] ) ) {
			if ( isset( $this->config[ $config_type ][ $blog_id ][ $key ] ) ) {
				return $this->config[ $config_type ][ $blog_id ][ $key ];
			}
		}

		return null;
	}
}
