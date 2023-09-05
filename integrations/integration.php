<?php
/**
 * Base class for Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

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
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;
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
		if ( $this->is_integration_already_available_via_customer() ) {
			trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				sprintf( 'Prevented activating of integration with slug "%s" because it is already available via customer code.', esc_html( $this->slug ) ),
				E_USER_WARNING
			);
			return;
		}

		// Don't do anything if integration is already activated.
		if ( $this->is_active() ) {
			trigger_error( sprintf( 'VIP Integration with slug "%s" is already activated.', esc_html( $this->get_slug() ) ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return;
		}

		$this->is_active = true;
		$this->options   = $options;
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
	 * Returns `true` if the integration is already available via customer code. We will use
	 * this function to prevent activating of integration from platform side.
	 *
	 * @private
	 */
	abstract public function is_integration_already_available_via_customer(): bool;

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
	 * @private
	 */
	abstract public function configure_for_vip(): void;
}
