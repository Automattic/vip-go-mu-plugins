<?php
/**
 * Integration: VIP Governance.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads VIP Governance.
 *
 * @private
 */
class VipGovernanceIntegration extends Integration {

	/**
	 * Returns `true` if `VIP Governance` is already available e.g. via customer code. We will use
	 * this function to prevent activating of integration from platform side.
	 */
	public function is_loaded(): bool {
		return defined( 'VIP_GOVERNANCE_LOADED' );
	}

	/**
	 * Applies hooks to load VIP Governance plugin.
	 *
	 * @private
	 */
	public function load(): void {
		// Wait until plugins_loaded to give precedence to the plugin in the customer repo.
		add_action( 'plugins_loaded', function () {
			// Return if the integration is already loaded.
			//
			// In activate() method we do make sure to not activate the integration if its already loaded
			// but still adding it here as a safety measure i.e. if load() is called directly.
			if ( $this->is_loaded() ) {
				return;
			}

			// Get all the entries in the path of WPVIP_MU_PLUGIN_DIR/vip-integrations/vip-governance-<version>/
			// and check what versions are available.
			$versions = $this->get_versions();

			// if no versions are found, return early.
			if ( empty( $versions ) ) {
				$this->is_active = false;
				return;
			}

			// Load the latest version of the plugin.
			$latest_directory = array_key_first( $versions );
			$load_path        = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/vip-governance.php';

			// This check isn't strictly necessary, but better safe than sorry.
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		} );
	}

	/**
	 * Get the available versions of VIP Governance in descending order.
	 *
	 * @return array<string, string> An associative array of available versions, where the key is the
	 *                               directory name and the value is the version number. The versions
	 *                               are sorted in descending order.
	 */
	public function get_versions() {
		return get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'vip-governance', 'vip-governance.php' );
	}
}
