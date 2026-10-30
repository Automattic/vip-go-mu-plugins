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
 * Reads `<slug>-config.php` like any integration. On WP Cloud those files are committed to
 * vip-wpcloud-externals one per hand-written integration, each returning its slice of the
 * `VIP_INTEGRATIONS` constant, and a manifest-backed integration has none. So when the file gives
 * no config, this falls back to `VIP_INTEGRATIONS[ $slug ]`. K8s doesn't define the constant.
 *
 * Only used for GenericIntegration, so hand-written integrations keep reading only their file.
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

		// A file that doesn't reach a `return` gives 1, not null.
		if ( is_array( $config ) || ! defined( 'VIP_INTEGRATIONS' ) ) {
			return $config;
		}

		$integrations = constant( 'VIP_INTEGRATIONS' );

		return is_array( $integrations ) && isset( $integrations[ $slug ] ) && is_array( $integrations[ $slug ] )
			? $integrations[ $slug ]
			: $config;
	}
}
