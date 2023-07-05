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
	 * The version of the parsely plugin to load.
	 *
	 * This should be the latest version available in `parsely` config https://github.com/Automattic/vip-go-mu-plugins-ext/blob/trunk/config.json
	 *
	 * @var string
	 */
	private string $version = '3.8'; // If updated then make sure to update SUPPORTED_VERSIONS in /wp-parsely.php (legacy support).

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

		// Load the version of the plugin that should be set to the latest version.
		$latest_plugin_path = WPMU_PLUGIN_DIR . '/wp-parsely-' . $this->version . '/wp-parsely.php';
		if ( file_exists( $latest_plugin_path ) ) {
			require_once $latest_plugin_path;
			return;
		}

		// Load submodule if specified version does not exist.
		$plugin_path = WPMU_PLUGIN_DIR . '/wp-parsely/wp-parsely.php';
		if ( file_exists( $plugin_path ) ) {
			require_once $plugin_path;
			return;
		}

		// Deactivate the integration if not found.
		$this->is_active_by_customer = false;
	}
}
