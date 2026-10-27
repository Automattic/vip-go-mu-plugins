<?php
/**
 * Integration: SourcePoint.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Exposes the SourcePoint CMP configuration delivered by VIP to site code.
 *
 * @private
 */
class SourcepointIntegration extends Integration {

	public function is_loaded(): bool {
		return defined( 'VIP_SOURCEPOINT_CONFIGS' );
	}

	/**
	 * Define the delivered configuration. On multisites each network site carries
	 * its own configuration, so the network-site config is used there.
	 */
	public function configure(): void {
		$configs = is_multisite() ? $this->get_network_site_config() : $this->get_env_config();

		if ( ! defined( 'VIP_SOURCEPOINT_CONFIGS' ) ) {
			define( 'VIP_SOURCEPOINT_CONFIGS', $configs );
		}
	}

	/**
	 * No bundled plugin is loaded and no CMP script tag is emitted.
	 *
	 * The configuration is exposed in configure(): site code reads
	 * `VIP_SOURCEPOINT_CONFIGS` (`account_id`, `property_id`, `privacy_manager_id`)
	 * or calls `wpvip_get_integration_info( 'sourcepoint' )`.
	 */
	public function load(): void {}
}
