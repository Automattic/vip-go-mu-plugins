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
	 * Returns `true` if `Parse.ly` is already available e.g. customer code. We will use
	 * this function to prevent loading of integration again from platform side.
	 */
	public function is_loaded(): bool {
		return class_exists( 'Parsely' ) || class_exists( 'Parsely\Parsely' );
	}

	/**
	 * Loads the plugin.
	 *
	 * @private
	 */
	public function load(): void {
		// Return if the integration is already loaded.
		//
		// In activate() method we do make sure to not activate the integration if its already loaded
		// but still adding it here as a safety measure i.e. if load() is called directly.
		if ( $this->is_loaded() ) {
			return;
		}

		// Activating the integration via setting constant whose implementation is already
		// handled via Automattic\VIP\WP_Parsely_Integration (ideally we should move
		// all the implementation here such that there will be only one way of managing
		// the plugin).
		if ( ! defined( 'VIP_PARSELY_ENABLED' ) ) {
			define( 'VIP_PARSELY_ENABLED', true );
		}
	}

	/**
	 * Configure `Parse.ly` for VIP Platform.
	 *
	 * @private
	 */
	public function configure(): void {
		add_filter( 'wp_parsely_credentials', array( $this, 'wp_parsely_credentials_callback' ) );
	}

	/**
	 * Callback for `wp_parsely_credentials` filter.
	 *
	 * @param array $original_credentials Credentials coming from plugin.
	 *
	 * @return array
	 */
	public function wp_parsely_credentials_callback( $original_credentials ) {
		$config = is_multisite() ? $this->get_network_site_config() : $this->get_env_config();

		// If config provided by VIP is empty then take original credentials else take
		// credentials from config.
		$credentials = empty( $config ) ? $original_credentials : array(
			'site_id'    => $config['site_id'] ?? null,
			'api_secret' => $config['api_secret'] ?? null,
		);

		// Adds `is_managed` flag to indicate that platform is managing this integration
		// and we have to hide the credential banner warning or more.
		return array_merge( array( 'is_managed' => true ), $credentials );
	}
}
