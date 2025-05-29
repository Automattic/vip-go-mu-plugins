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
	 * This should be higher than the lowestVersion set in "vip-security-boost" config (https://github.com/Automattic/vip-go-mu-plugins-ext/blob/trunk/config.json)
	 *
	 * @var string
	 */
	public string $version = 'latest';

	public function is_loaded(): bool {
		return defined( 'VIP_SECURITY_BOOST__LOADED' );
	}

	public function configure(): void {
		$configs = $this->get_env_config();

		if ( ! defined( 'VIP_SECURITY_BOOST_CONFIGS' ) ) {
			define( 'VIP_SECURITY_BOOST_CONFIGS', $configs );
		}

		if ( isset( $configs['version'] ) && is_string( $configs['version'] ) ) {
			$this->version = $configs['version'];
		}
	}

	public function load(): void {
		if ( $this->is_loaded() ) {
			return;
		}

		// Get all the entries in the path of WPVIP_MU_PLUGIN_DIR/vip-integrations/vip-security-boost-<version>/
		// and check what versions are available.
		$versions = $this->get_versions();

		// if no versions are found, return early.
		if ( empty( $versions ) ) {
			$this->is_active = false;
			return;
		}

		// Load the selected version of the plugin.
		$selected_version_folder = $this->get_selected_version_folder( $versions );
		$load_path               = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $selected_version_folder . '/vip-security-boost.php';

		// This check isn't strictly necessary, but better safe than sorry.
		if ( file_exists( $load_path ) ) {
			require_once $load_path;
		} else {
			$this->is_active = false;
		}

		if ( ! defined( 'VIP_SECURITY_BOOST__LOADED' ) ) {
			define( 'VIP_SECURITY_BOOST__LOADED', true );
		}
	}

	/**
	 * Get the available versions of Security Boost in descending order.
	 *
	 * @return array<string, string> An associative array of available versions, where the key is the
	 *                               directory name and the value is the version number. The versions
	 *                               are sorted in descending order.
	 */
	public function get_versions() {
		return get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'vip-security-boost', 'vip-security-boost.php' );
	}

	/**
	 * Get the folder name for the selected version of the integration.
	 *
	 * @return string The folder name for the selected version of the integration.
	 */
	public function get_selected_version_folder( array $versions ) {
		if ( 'latest' === $this->version ) {
			return array_key_first( $versions );
		}

		// find the desired version in the versions array.
		$desired_version = array_search( $this->version, $versions );

		if ( $desired_version ) {
			return $desired_version;
		}

		// if the desired version is not found, return the latest version.
		return array_key_first( $versions );
	}
}
