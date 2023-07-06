<?php
/**
 * Tests: Fake Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Fake Integration class.
 */
class FakeIntegration extends Integration {
	/**
	 * Test integration is loaded via FakeIntegration::class registration.
	 *
	 * @var bool
	 */
	public static $is_loaded_class = false;

	/**
	 * Test integration is loaded via a new FakeIntegration() instance registration.
	 *
	 * @var bool
	 */
	public $is_loaded_instance = false;

	/**
	 * Set flags to indicate this integration's load() method was called.
	 *
	 * @param array $config Config of the integration.
	 */
	public function load( array $config ): void {
		self::$is_loaded_class    = true;
		$this->is_loaded_instance = true;
	}
}
