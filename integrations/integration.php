<?php
/**
 * Base class for Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Disabling due to enums.

/**
 * Enum which represent all possible status for the client integration.
 */
abstract class Client_Integration_Status {
	const BLOCKED = 'blocked';
}

/**
 * Enum which represent all possible status for the site integration.
 */
abstract class Site_Integration_Status {
	const ENABLED  = 'enabled';
	const DISABLED = 'disabled';
	const BLOCKED  = 'blocked';
}

/**
 * Abstract base class for all integration implementations.
 */
abstract class Integration {
	/**
	 * Slug of the integration.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * An optional configuration array for this integration, added during activation.
	 *
	 * @var array
	 */
	private array $config = [];

	/**
	 * Configurations provided by VIP to enable, disable or block the integration.
	 *
	 * @var array {
	 *   'client'        => array<string, string>,
	 *   'site'          => array<string, string>,
	 *   'network-sites' => array<string, array<string, string>>,
	 * }
	 *
	 * @example
	 * array(
	 *  'client'        => array( 'status' => 'blocked' ),
	 *  'site'          => array( 'status' => 'disabled' ),
	 *  'network-sites' => array (
	 *      1 => array (
	 *          'status' => 'disabled',
	 *      ),
	 *      2 => array (
	 *          'status' => 'enabled',
	 *      ),
	 *      3 => array (
	 *          'status' => 'blocked',
	 *      ),
	 *  )
	 * );
	 */
	private array $vip_config = [];

	/**
	 * A boolean indicating if this integration is activated by customer.
	 *
	 * @var bool
	 */
	private bool $is_active_by_customer = false;

	/**
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;
		
		$this->set_vip_config();
	}

	/**
	 * Activates this integration with an optional configuration value.
	 *
	 * @param array $config An associative array of configuration values for the integration.
	 */
	public function activate( array $config = [] ): void {
		$this->is_active_by_customer = true;
		$this->config                = $config;
	}

	/**
	 * Returns true if this integration has been activated.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		if ( $this->is_active_by_customer ) {
			return true;
		}

		if ( $this->is_active_by_vip() ) {
			return true;
		}

		return false;
	}

	/**
	 * Return the activation configuration for this integration.
	 *
	 * @return array<mixed>
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Get setup configs provided by VIP.
	 */
	private function set_vip_config(): void {
		$config_file_path = ABSPATH . 'config/integrations-config/' . $this->slug . '-config.php';

		if ( ! is_readable( $config_file_path ) ) {
			return;
		}
	
		$configs = require_once $config_file_path;

		if ( is_array( $configs ) ) {
			$this->vip_config = $configs;
		}
	}

	/**
	 * Returns true if the integration is active from VIP.
	 *
	 * @return bool
	 */
	private function is_active_by_vip(): bool {
		// Return false if client is blocked.
		if ( $this->get_value_from_vip_config( 'client', 'status' ) === Client_Integration_Status::BLOCKED ) {
			return false;
		}

		$site_status = $this->get_value_from_vip_config( 'site', 'status' );

		// Return false if site is blocked.
		if ( Site_Integration_Status::BLOCKED === $site_status ) {
			return false;
		}

		// Check for network-site enablement if multisite.
		if ( is_multisite() ) {
			if ( is_network_admin() ) {
				return false;
			}

			return $this->get_value_from_vip_config( 'network-sites', 'status' ) === Site_Integration_Status::ENABLED;
		}

		return Site_Integration_Status::ENABLED === $site_status; // Return site status if not multisite.
	}

	/**
	 * Get config status of given type i.e. client, site, network-sites etc
	 *
	 * @param string $config_type Type of the config whose data is needed.
	 * @param string $key Key of the config from which we have to extract the data.
	 *
	 * @return string
	 */
	private function get_value_from_vip_config( string $config_type, string $key ): string {
		if ( ! isset( $this->vip_config[ $config_type ] ) ) {
			return '';
		}

		// Look for $key inside client or site config.
		if ( 'network-sites' !== $config_type && isset( $this->vip_config[ $config_type ][ $key ] ) ) {
			return $this->vip_config[ $config_type ][ $key ];
		}

		// Look for $key inside network-sites config.
		if ( 'network-sites' === $config_type && isset( $this->vip_config[ $config_type ][ get_current_blog_id() ] ) ) {
			if ( isset( $this->vip_config[ $config_type ][ get_current_blog_id() ][ $key ] ) ) {
				return $this->vip_config[ $config_type ][ get_current_blog_id() ][ $key ];
			}
		}

		return '';
	}

	/**
	 * Get slug of the integration.
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Abstract base for integration functionality.
	 * Implement custom action and filter calls to load integration here.
	 *
	 * For plugins / integrations that can be added to customer repos, 
	 * the implementation should hook into plugins_loaded and check if 
	 * the plugin is already loaded first.
	 * 
	 * @param array $config Configuration for this integration.
	 */
	abstract public function load( array $config ): void;
}
