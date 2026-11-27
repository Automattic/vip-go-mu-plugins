<?php
/**
 * Generic Integration Configuration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * VIP configuration for a GenericIntegration.
 *
 * Falls back to `VIP_INTEGRATIONS[ $slug ]` when `<slug>-config.php` gives no config. Other
 * integrations keep reading only their file.
 *
 * @private
 */
class GenericIntegrationVipConfig extends IntegrationVipConfig {
	/**
	 * Config from `<slug>-config.php`, or from the `VIP_INTEGRATIONS` constant when the file
	 * gives none.
	 *
	 * @param string $slug A unique identifier for the integration.
	 *
	 * @return null|mixed
	 */
	protected function get_vip_config_from_file( string $slug ) {
		$config = parent::get_vip_config_from_file( $slug );

		// A file that doesn't return gives 1, not null.
		if ( is_array( $config ) || ! defined( 'VIP_INTEGRATIONS' ) ) {
			return $config;
		}

		$integrations = constant( 'VIP_INTEGRATIONS' );

		return is_array( $integrations ) && isset( $integrations[ $slug ] ) && is_array( $integrations[ $slug ] )
			? $integrations[ $slug ]
			: $config;
	}
}
