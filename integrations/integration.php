<?php
/**
 * Base class for Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use LogicException;

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
	 * Activates this integration with an optional configuration value.
	 *
	 * @param array $config An associative array of configuration values for the integration.
	 *
	 * @throws LogicException Exception if integration is already activated.
	 *
	 * @private
	 */
	public function activate( array $config = [] ): void {
		if ( $this->is_active() ) {
			throw new LogicException( sprintf( 'VIP Integration with slug "%s" is already activated.', $this->get_slug() ) );
		}

		$this->is_active = true;
		$this->config    = $config;
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
		return $this->config;
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
	 * Implement custom action and filter calls to load integration here.
	 *
	 * For plugins / integrations that can be added to customer repos, 
	 * the implementation should hook into plugins_loaded and check if 
	 * the plugin is already loaded first.
	 *
	 * @private
	 */
	abstract public function load(): void;
}
