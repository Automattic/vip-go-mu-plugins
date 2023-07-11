<?php
/**
 * Tests: Fake Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use function Automattic\Test\Utils\get_private_method_as_public;
use function Automattic\Test\Utils\get_private_property_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing

class FakeIntegration extends Integration {
	/**
	 * Constructor.
	 *
	 * Copying the implementation of parent constructor so that PHPUnit is able to mock the methods correctly.
	 *
	 * @param string $slug Integration's slug.
	 */
	public function __construct( string $slug ) {
		get_private_property_as_public( Integration::class, 'slug' )->setValue( $this, $slug );
		
		$config = $this->get_vip_config_from_file();
		if ( is_array( $config ) ) {
			get_private_property_as_public( Integration::class, 'vip_config' )->setValue( $this, $config );
			$this->set_is_active_by_vip();
		}
	}

	public function get_is_active_by_vip(): bool {
		return get_private_method_as_public( Integration::class, 'get_is_active_by_vip' )->invoke( $this );
	}

	public function get_vip_config_from_file() {
		return get_private_method_as_public( Integration::class, 'get_vip_config_from_file' )->invoke( $this );
	}

	public function set_is_active_by_vip() {
		return get_private_method_as_public( Integration::class, 'set_is_active_by_vip' )->invoke( $this );
	}

	public function get_value_from_vip_config( $config_type, $key ) {
		return get_private_method_as_public( Integration::class, 'get_value_from_vip_config' )->invoke( $this, $config_type, $key );
	}

	/**
	 * Dummy implementation of load method which is an abstract method in base class.
	 *
	 * @param array $config Config of the integration.
	 */
	public function load( array $config ): void { }
}
