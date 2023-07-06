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
	 * Name of the filter which we will use to setup the plugin configs.
	 *
	 * @var string
	 */
	protected string $integration_configs_filter_name = 'wp_parsely_credentials';

	/**
	 * Applies hooks to load plugin.
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
}