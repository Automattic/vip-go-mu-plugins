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
	 * The version of the VIP Governance plugin to load, that's set to the latest version.
	 * This should be higher than the lowestVersion set in "vip-governance" config (https://github.com/Automattic/vip-go-mu-plugins-ext/blob/trunk/config.json)
	 *
	 * @var string
	 */
	protected string $version = '1.0';

	/**
	 * Returns `true` if `VIP Governance` is already available e.g. via customer code. We will use
	 * this function to prevent activating of integration from platform side.
	 */
	public function is_loaded(): bool {
		return defined( 'VIP_BLOCK_GOVERNANCE_LOADED' );
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

			// Load the version of the plugin that should be set to the latest version, otherwise if it's not found deactivate the integration.
			$load_path = WPMU_PLUGIN_DIR . '/vip-integrations/vip-governance-' . $this->version . '/vip-governance.php';
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		} );
	}

	/**
	 * Configure `VIP Governance` for VIP Platform.
	 */
	public function configure(): void {}
}
