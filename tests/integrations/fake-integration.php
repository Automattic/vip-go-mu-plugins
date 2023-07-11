<?php
/**
 * Fake Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use function Automattic\Test\Utils\get_private_method_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing

class FakeIntegration extends Integration {
	/**
	 * Name of the filter which we will be used to pass the config from platform to integration.
	 *
	 * @var string
	 */
	protected string $vip_config_filter_name = 'fake_vip_config_filter';

	protected function is_active_by_vip(): bool {
		return get_private_method_as_public( Integration::class, 'is_active_by_vip' )->invoke( $this );
	}

	protected function get_vip_config_from_file() {
		return get_private_method_as_public( Integration::class, 'get_vip_config_from_file' )->invoke( $this );
	}

	protected function get_value_from_vip_config( $config_type, $key ) {
		return get_private_method_as_public( Integration::class, 'get_value_from_vip_config' )->invoke( $this, $config_type, $key );
	}

	/**
	 * Dummy implementation of load method which is an abstract method in base class.
	 *
	 * @param array $config Config of the integration.
	 */
	public function load( array $config ): void { }
}
