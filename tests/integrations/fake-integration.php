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
	 * Dummy implementation of load method which is an abstract method in base class.
	 *
	 * @param array $config Config of the integration.
	 */
	public function load( array $config ): void { }
}
