<?php
/**
 * Integration: Parse.ly.
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
	 * @private
	 */
	public function load(): void {
		// Do not load plugin if already loaded by customer code.
		if ( class_exists( 'Parsely\Parsely' ) ) {
			return;
		}

		// Activating the integration via setting constant whose implementation is already
		// handled via Automattic\VIP\WP_Parsely_Integration (ideally we should move
		// all the implementation here such that there will be only one way of managing
		// the plugin).
		define( 'VIP_PARSELY_ENABLED', true );

		add_filter( 'wp_parsely_credentials', array( $this, 'wp_parsely_credentials_callback' ) );
	}

	/**
	 * Callback for `wp_parsely_credentials` filter.
	 *
	 * @param array $original_credentials Credentials coming from plugin.
	 *
	 * @return array
	 */
	protected function wp_parsely_credentials_callback( $original_credentials ) {
		$config = $this->get_config();
		
		if ( empty( $config ) ) {
			return $original_credentials;
		}

		return array(
			'site_id'    => $config['site_id'] ?? null,
			'api_secret' => $config['api_secret'] ?? null,
		);
	}
}
