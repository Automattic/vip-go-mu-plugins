<?php
/**
 * Base class for Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\VIP\Integrations\IntegrationVipConfig;

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
	protected array $options = [];

	/**
	 * A boolean indicating if this integration should be loaded. Defaults to false.
	 *
	 * @var bool
	 */
	protected bool $is_active = false;

	/**
	 * Instance of VipIntegrationConfig. It's useful to have full configuration info
	 * available inside each integration, we can use it for cases like multisite,
	 * tracking etc.
	 *
	 * Note: We don't use this property for activation of the integration.
	 *
	 * @var IntegrationVipConfig
	 */
	private IntegrationVipConfig $vip_config;

	/**
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;

		add_action( 'switch_blog', array( $this, 'switch_blog_callback' ), 10, 2 );
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
		// Don't do anything if integration is already loaded or activated.
		if ( $this->is_loaded() || $this->is_active() ) {
			return;
		}

		$this->is_active = true;
		$this->options   = $options;
	}

	/**
	 * Callback for `switch_blog` filter.
	 */
	public function switch_blog_callback(): void {
		// Updating config to make sure `get_config()` returns config of current blog instead of main site.
		if ( isset( $this->vip_config ) ) {
			$this->options['config'] = $this->vip_config->get_site_config();
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
	 * Set vip_config property.
	 *
	 * @param IntegrationVipConfig $vip_config Instance of IntegrationVipConfig.
	 *
	 * @return void
	 */
	public function set_vip_config( IntegrationVipConfig $vip_config ): void {
		if ( ! $this->is_active() ) {
			trigger_error( sprintf( 'Configuration info can only assigned if integration is active.' ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return;
		}

		$this->vip_config = $vip_config;
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
