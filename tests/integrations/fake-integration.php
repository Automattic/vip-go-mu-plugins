<?php
/**
 * Fake Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing

class FakeIntegration extends Integration {
	/**
	 * Name of the filter which we will be used to pass the config from platform to integration.
	 *
	 * @var string
	 */
	protected string $vip_config_filter_name = 'fake_vip_config_filter';

	/**
	 * Dummy implementation of load method which is an abstract method in base class.
	 *
	 * @param array $config Config of the integration.
	 */
	public function load( array $config ): void { }
}
