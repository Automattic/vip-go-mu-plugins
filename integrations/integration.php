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
	 *   client => array<mixed>,
	 *   site   => array<mixed>,
	 * }
	 */
	private array $vip_configs = [];

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
		
		$this->set_vip_configs();
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
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Get setup configs provided by VIP.
	 */
	private function set_vip_configs(): void {
		$config_file_path = ABSPATH . 'config/integrations-config/' . $this->slug . '-config.php';

		if ( ! is_readable( $config_file_path ) ) {
			return;
		}
	
		$configs = require_once $config_file_path;

		if ( is_array( $configs ) ) {
			$this->vip_configs = $configs;
		}
	}

	/**
	 * Returns true if the integration is active from VIP.
	 */
	private function is_active_by_vip(): bool {
		// Return false if client is blocked.
		if ( $this->get_config_status( 'client' ) === Client_Integration_Status::BLOCKED ) {
			return false;
		}

		return $this->get_config_status( 'site' ) === Site_Integration_Status::ENABLED;
	}

	/**
	 * Get config status of given type i.e. client, site.
	 *
	 * @param string $config_type Type of the config whose status is needed.
	 */
	private function get_config_status( string $config_type ): string {
		if ( ! isset( $this->vip_configs[ $config_type ] ) ) {
			return '';
		}

		if ( isset( $this->vip_configs[ $config_type ]['status'] ) ) {
			return $this->vip_configs[ $config_type ]['status'];
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
