<?php

namespace Automattic\VIP\Integrations;

/**
 * Abstract base class for all integration implementations.
 */
abstract class Integration {
	/**
	 * An optional configuration array for this integration, added during activation.
	 *
	 * @var array
	 */
	public $config = [];

	/**
	 * A boolean indicating if this integration should be loaded. Defaults to false.
	 *
	 * @var bool
	 */
	protected $active = false;

	/**
	 * Activates this integration with an optional configuration value.
	 *
	 * @param array  $config An associative array of configuration values for the integration.
	 */
	public function activate( $config = [] ): void {
		$this->active = true;
		$this->config = $config;
	}

	/**
	 * Returns true if this integration has been activated.
	 */
	public function is_active(): bool {
		return $this->active;
	}

	/**
	 * Abstract base for integration functionality.
	 * Implement custom action and filter calls to load integration here.
	 */
	abstract public function integrate( $config ): void;
}
