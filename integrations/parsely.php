<?php
/**
 * Integration: Parse.ly
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Parse.ly VIP Integration.
 *
 * @private
 */
class ParselyIntegration extends Integration {
	/**
	 * Loads the plugin.
	 *
	 * @param array $config Configuration for this integration.
	 *
	 * @private
	 */
	public function load( array $config ): void {
		// Do not load plugin if already loaded by customer code.
		if ( class_exists( 'Parsely\Parsely' ) ) {
			return;
		}

		// Activating the integration via setting constant whose implementation is already
		// handled via Automattic\VIP\WP_Parsely_Integration (ideally we should move
		// all the implementation here such that there will be only one way of managing
		// the plugin).
		define( 'VIP_PARSELY_ENABLED', true );
	}

	/**
	 * Adds filter which setup vip config of a site or network site.
	 *
	 * @return void
	 */
	public function setup_config(): void {
		add_filter( 'wp_parsely_credentials', function() {
			if ( is_multisite() ) {
				return $this->get_vip_config_of_current_network_site();
			}

			return $this->get_vip_config_of_site();
		} );
	}
}
