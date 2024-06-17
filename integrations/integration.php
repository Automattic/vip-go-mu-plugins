<?php
/**
 * Base class for Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Org_Integration_Status;
use Env_Integration_Status;

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
	 * An optional options array for this integration, added during activation.
	 *
	 * In this array we will keep all the common parameters across all integrations
	 * as direct key/value pair e.g. `version` and we will keep the integration specific
	 * parameters in `config` as array.
	 *
	 * Note: Common parameters are NOT supported currently, we have just tried to
	 * future proof this common parameters case and related functionality will be
	 * added in future when we support it.
	 *
	 * @var array{
	 *     'version'?: string,
	 *     'config'?: array,
	 * }
	 */
	private array $options = [];

	/**
	 * A boolean indicating if this integration should be loaded. Defaults to false.
	 *
	 * @var bool
	 */
	protected bool $is_active = false;

	/**
	 * Array containing all configuration data. It's useful to have full configuration info
	 * available inside each integration, we can use it for cases like multisite,
	 * tracking etc.
	 *
	 * Note: We don't use this property for activation of the integration.
	 *
	 * @var array
	 */
	private array $vip_configs = [];

	/**
	 * A boolean indicating if the integration have multiple configs.
	 *
	 * @var bool
	 */
	protected bool $have_multiple_configs = false;

	/**
	 * When an integration doesn't have its own config and is dependent on configs
	 * of other integrations then we can use this property.
	 *
	 * Example:
	 *
	 * VIP Composable Blocks integration needs data sources which
	 * are dependent on Shopify, Airtable etc.
	 *
	 * @var array<Integration>
	 */
	protected array $child_integrations = [];

	/**
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;

		// Registers child integrations if any.
		foreach ( $this->child_integrations as $integration ) {
			IntegrationsSingleton::instance()->register( $integration );
		}

		add_action( 'switch_blog', array( $this, 'switch_blog_callback' ), 10 );
	}

	/**
	 * Activates this integration with given options array.
	 *
	 * @param array $options An associative options array for the integration.
	 *                       This can contain common parameters and integration specific parameters in `config` key.
	 *
	 * @private
	 */
	public function activate( array $options = [] ): void {
		// If integration is already available in customer code then don't activate it from platform side.
		if ( $this->is_loaded() ) {
			trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				sprintf( 'Prevented activating of integration with slug "%s" because it is already loaded.', esc_html( $this->slug ) ),
				E_USER_WARNING
			);
		}

		// Don't do anything if integration is already activated.
		if ( $this->is_active() ) {
			trigger_error( sprintf( 'VIP Integration with slug "%s" is already activated.', esc_html( $this->get_slug() ) ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		}

		$this->is_active = true;
		$this->options   = $options;
	}

	/**
	 * Callback for `switch_blog` filter.
	 *
	 * @private
	 */
	public function switch_blog_callback(): void {
		// Updating config to make sure `get_config()` returns config of current blog instead of main site.
		if ( isset( $this->vip_configs ) ) {
			$this->options['config'] = $this->get_site_configs( $this->slug );
		}
	}

	/**
	 * Returns true if this integration has been activated.
	 *
	 * @private
	 */
	public function is_active(): bool {
		return $this->is_active;
	}

	/**
	 * Returns `true` if the integration is enabled in VIP configs else `false`.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function is_active_via_vip(): bool {
		// Returns 'true` if any of the child integrations is active else get the related config for the integration.
		foreach ( $this->child_integrations as $integration ) {
			if ( $integration->is_active_via_vip() ) {
				return true;
			}
		}

		return in_array( Env_Integration_Status::ENABLED, $this->get_site_statuses() );
	}

	/**
	 * Return the configuration for this integration.
	 *
	 * @return array<string,array>
	 *
	 * @private
	 */
	public function get_config(): array {
		return isset( $this->options['config'] ) ? $this->options['config'] : array();
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
	 * Set `vip_configs` property.
	 *
	 * @param array $vip_configs Configurations provided by VIP.
	 *
	 * @return void
	 *
	 * @private
	 */
	public function set_vip_configs( array $vip_configs ): void {
		$this->vip_configs = $vip_configs;
	}

	/**
	 * Get statuses of the integration in context of current site.
	 *
	 * @return array<Env_Integration_Status>
	 */
	private function get_site_statuses() {
		/**
		 * Statuses of the integration.
		 *
		 * @var array<string>
		 */
		$statuses = [];

		foreach ( $this->vip_configs as $vip_config ) {
			if ( $this->get_value_from_config( $vip_config, 'org', 'status' ) === Org_Integration_Status::BLOCKED ) {
				return [ Org_Integration_Status::BLOCKED ];
			}
	
			// Look into network_sites config before and then fallback to env config.
			$statuses[] = $this->get_value_from_config( $vip_config, 'network_sites', 'status' ) ??
				$this->get_value_from_config( $vip_config, 'env', 'status' );
		}

		return $statuses;
	}

	/**
	 * Get configs of the integration in context of current site.
	 *
	 * @return array<array<mixed>> Returns an array of configs if the integration have multiple configs else single config object.
	 *
	 * @private
	 */
	public function get_site_configs() {
		/**
		 * Array containing configs of the integration.
		 *
		 * @var array<array<mixed>>
		 */
		$configs = [];

		// If integration have child integrations then merge the config of each child integration and return.
		if ( count( $this->child_integrations ) ) {
			foreach ( $this->child_integrations as $integration ) {
				$configs = array_merge( $configs, $integration->get_config() );
			}

			return $configs;
		}

		// Get configs of the integration from configurations provided by VIP.
		foreach ( $this->vip_configs as $vip_config ) {
			if ( is_multisite() ) {
				$config = $this->get_value_from_config( $vip_config, 'network_sites', 'config' );

				// If network site config is not found then fallback to env config if it exists.
				if ( empty( $config ) && true === $this->get_value_from_config( $vip_config, 'env', 'cascade_config' ) ) {
					$config = $this->get_value_from_config( $vip_config, 'env', 'config' );
				}
			} else {
				$config = $this->get_value_from_config( $vip_config, 'env', 'config' );
			}

			if ( ! isset( $config ) ) {
				continue;
			}

			$config['type'] = $this->slug; // Useful to have it available, specially when integration is dependent on child integrations.

			if ( isset( $vip_config['label'] ) ) {
				$config['label'] = $vip_config['label']; // Useful to differentiate between multiple configs of same integration.
			}

			$configs[] = $config;
		}

		// Return config object if integration have only one config else return all configs.
		return ( ! $this->have_multiple_configs && isset( $configs[0] ) ) ? $configs[0] : $configs;
	}

	/**
	 * Get config value based on given type and key.
	 *
	 * @param array  $vip_config  Configurations provided by VIP.
	 * @param string $config_type Type of the config whose data is needed i.e. org, env, network-sites etc.
	 * @param string $key Key of the config from which we have to extract the data.
	 *
	 * @return null|string|array Returns `null` if key is not found, `string` if key is "status" and `array` if key is "config".
	 */
	private function get_value_from_config( array $vip_config, string $config_type, string $key ) {
		$value = null;

		if ( ! in_array( $config_type, [ 'org', 'env', 'network_sites' ], true ) ) {
			trigger_error( 'config_type param (' . esc_html( $config_type ) . ') must be one of org, env or network_sites.', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		} elseif ( isset( $vip_config[ $config_type ] ) ) {

			// Look for key inside org or env config.
			if ( 'network_sites' !== $config_type && isset( $vip_config[ $config_type ][ $key ] ) ) {
				return $vip_config[ $config_type ][ $key ];
			}

			// Look for key inside network-sites config.
			$network_site_id = get_current_blog_id();
			if (
				'network_sites' === $config_type &&
				isset( $vip_config[ $config_type ][ $network_site_id ] ) &&
				isset( $vip_config[ $config_type ][ $network_site_id ][ $key ] )
			) {
				return $vip_config[ $config_type ][ $network_site_id ][ $key ];
			}
		}

		return $value;
	}

	/**
	 * Returns `true` if the integration is already available e.g. via customer code. We will use
	 * this function to prevent activating of integration again.
	 *
	 * @private
	 */
	abstract public function is_loaded(): bool;

	/**
	 * Implement custom action and filter calls to load integration here.
	 *
	 * For plugins / integrations that can be added to customer repos, 
	 * the implementation should hook into plugins_loaded and check if 
	 * the plugin is already loaded first.
	 *
	 * @private
	 */
	abstract public function load(): void;

	/**
	 * Configure the integration for VIP platform.
	 * 
	 * If we want to implement functionality only if the integration is enabled via VIP
	 * then we will use this function.
	 * 
	 * By default, the implementation of this function will be empty.
	 * 
	 * @private
	 */
	public function configure(): void {}
}
