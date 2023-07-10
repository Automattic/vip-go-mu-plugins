<?php
/**
 * Base class for Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Disabling due to enums.

/**
 * Enum which represent all possible status for the client integration via VIP.
 *
 * These should be in sync with the statuses on the backend.
 */
abstract class Client_Integration_Status {
	const BLOCKED = 'blocked';
}

/**
 * Enum which represent all possible status for the site integration via VIP.
 *
 * These should be in sync with the statuses on the backend.
 */
abstract class Site_Integration_Status {
	const ENABLED  = 'enabled';
	const DISABLED = 'disabled';
	const BLOCKED  = 'blocked';
}

/**
 * Abstract base class for all integration implementations.
 *
 * @private
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
	 * Configurations provided by VIP for setup.
	 *
	 * @var array {
	 *   'client'        => array<string, string>,
	 *   'site'          => array<string, mixed>,
	 *   'network_sites' => array<string, array<string, mixed>>,
	 * }
	 *
	 * @example
	 * array(
	 *  'client'        => array( 'status' => 'blocked' ),
	 *  'site'          => array(
	 *      'status' => 'enabled',
	 *      'configs' => array(),
	 *   ),
	 *  'network_sites' => array (
	 *      1 => array (
	 *          'status' => 'disabled',
	 *          'configs' => array(),
	 *      ),
	 *      2 => array (
	 *          'status' => 'enabled',
	 *          'configs' => array(),
	 *      ),
	 *      3 => array (
	 *          'status' => 'blocked',
	 *          'configs' => array(),
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
	protected bool $is_active_by_customer = false;

	/**
	 * A boolean indicating if this integration is activated by VIP.
	 *
	 * @var bool
	 */
	private bool $is_active_by_vip = false;

	/**
	 * Name of the filter which we will use to setup the integration configs.
	 *
	 * As of now there is no default so each integration will define its own filter in their class.
	 *
	 * @var string
	 */
	protected string $integration_configs_filter_name = '';

	/**
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;
		
		$configs = $this->get_vip_config_from_file();
		if ( is_array( $configs ) ) {
			$this->vip_config = $configs;
			$this->set_is_active_by_vip();
		}
	}

	/**
	 * Activates this integration with an optional configuration value.
	 *
	 * @param array $config An associative array of configuration values for the integration.
	 *
	 * @private
	 */
	public function activate( array $config = [] ): void {
		$this->is_active_by_customer = true;
		$this->config                = $config;
	}

	/**
	 * Returns true if this integration has been activated.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function is_active(): bool {
		if ( $this->is_active_by_customer ) {
			return true;
		}

		if ( $this->get_is_active_by_vip() ) {
			return true;
		}

		return false;
	}

	/**
	 * Return the activation configuration for this integration.
	 *
	 * @return array<mixed>
	 *
	 * @private
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Return the value of `is_active_by_vip` property.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function get_is_active_by_vip(): bool {
		return $this->is_active_by_vip;
	}

	/**
	 * Get setup configs provided by VIP.
	 *
	 * @return null|mixed
	 *
	 * @private
	 */
	public function get_vip_config_from_file() {
		$config_file_path = ABSPATH . 'config/integrations-config/' . $this->slug . '-config.php';

		if ( ! is_readable( $config_file_path ) ) {
			return null;
		}

		return require_once $config_file_path;
	}

	/**
	 * Returns true if the integration is active by VIP and setup plugin configs which are provided by VIP.
	 *
	 * @return void
	 *
	 * @private
	 */
	public function set_is_active_by_vip() {
		// Return false if client is blocked.
		if ( $this->get_value_from_vip_config( 'client', 'status' ) === Client_Integration_Status::BLOCKED ) {
			$this->is_active_by_vip = false;
		}

		$site_status = $this->get_value_from_vip_config( 'site', 'status' );

		// Return false if site is blocked.
		if ( Site_Integration_Status::BLOCKED === $site_status ) {
			$this->is_active_by_vip = false;
		}

		// Check network site enablement if multisite.
		if ( is_multisite() ) {
			if ( is_network_admin() ) {
				$this->is_active_by_vip = false;
			}

			// If enabled on network site then set credentials via filter and return true.
			if ( $this->get_value_from_vip_config( 'network_sites', 'status' ) === Site_Integration_Status::ENABLED ) {
				if ( '' !== $this->integration_configs_filter_name ) {
					add_filter( $this->integration_configs_filter_name, function() {
						return $this->get_value_from_vip_config( 'network_sites', 'configs' );
					} );
				}

				$this->is_active_by_vip = true;
			}
		}

		// If enabled on site then set credentials via filter and return true.
		if ( Site_Integration_Status::ENABLED === $site_status ) {
			if ( '' !== $this->integration_configs_filter_name ) {
				add_filter( $this->integration_configs_filter_name, function() {
					return $this->get_value_from_vip_config( 'site', 'configs' );
				} );
			}

			$this->is_active_by_vip = true;
		}
	}

	/**
	 * Get config value based on given type and key.
	 *
	 * @param string $config_type Type of the config whose data is needed i.e. client, site, network-sites etc.
	 * @param string $key Key of the config from which we have to extract the data.
	 *
	 * @return string|array
	 */
	private function get_value_from_vip_config( string $config_type, string $key ) {
		if ( ! isset( $this->vip_config[ $config_type ] ) ) {
			return '';
		}

		// Look for key inside client or site config.
		if ( 'network_sites' !== $config_type && isset( $this->vip_config[ $config_type ][ $key ] ) ) {
			return $this->vip_config[ $config_type ][ $key ];
		}

		// Look for key inside network-sites config.
		if ( 'network_sites' === $config_type && isset( $this->vip_config[ $config_type ][ get_current_blog_id() ] ) ) {
			if ( isset( $this->vip_config[ $config_type ][ get_current_blog_id() ][ $key ] ) ) {
				return $this->vip_config[ $config_type ][ get_current_blog_id() ][ $key ];
			}
		}

		return '';
	}

	/**
	 * Get slug of the integration.
	 *
	 * @private
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
	 *
	 * @private
	 */
	abstract public function load( array $config ): void;
}
