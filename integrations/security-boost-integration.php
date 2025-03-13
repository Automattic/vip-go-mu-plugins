<?php
/**
 * Integration: Security Boost.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the Security Boost integration.
 *
 * @private
 */
class SecurityBoostIntegration extends \Automattic\VIP\Integrations\Integration {

	/**
	 * The version of the Security Boost plugin to load, that's set to the latest version.
	 * This should be higher than the lowestVersion set in "vip-block-data-api" config (https://github.com/Automattic/vip-go-mu-plugins-ext/blob/trunk/config.json)
	 *
	 * @var string
	 */
	protected string $version = '1.0';

	public function is_loaded(): bool {
		return defined( 'VIP_SECURITY_BUNDLE_LOADED' );
	}

	public function configure(): void {
		$configs = $this->get_env_config();

		if ( ! defined( 'VIP_SECURITY_BUNDLE_CONFIGS' ) ) {
			define( 'VIP_SECURITY_BUNDLE_CONFIGS', $configs );
		}
	}

	public function load(): void {
		if ( $this->is_loaded() ) {
			return;
		}

		// Load the version of the plugin that should be set to the latest version, otherwise if it's not found, flag the integration as inactive.
		$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/vip-security-boost-' . $this->version . '/src/vip-security-boost.php';
		if ( file_exists( $load_path ) ) {
			require_once $load_path;
		} else {
			$this->is_active = false;
		}

		if ( ! defined( 'VIP_SECURITY_BUNDLE_LOADED' ) ) {
			define( 'VIP_SECURITY_BUNDLE_LOADED', true );
		}
	}
}
