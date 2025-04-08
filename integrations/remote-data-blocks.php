<?php
/**
 * Integration: Remote Data Blocks.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Remote Data Blocks VIP Integration.
 *
 * @private
 */
class RemoteDataBlocksIntegration extends Integration {

	/**
	 * The version of Remote Data Blocks to load.
	 *
	 * @var string
	 */
	protected string $version = '0.11';

	/**
	 * Returns `true` if Remote Data Blocks is already available e.g. via customer code. We will use
	 * this function to prevent loading of integration again from platform side.
	 */
	public function is_loaded(): bool {
		// Check for the existence of the plugin version constant defined in the main plugin file.
		return defined( 'REMOTE_DATA_BLOCKS__LOADED' );
	}

	/**
	 * Loads the plugin.
	 *
	 * This is called after the integration is activated and configured.
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
			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/remote-data-blocks-' . $this->version . '/remote-data-blocks.php';
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		} );
	}

	/**
	 * Configure Remote Data Blocks for VIP Platform.
	 *
	 * This is called after the integration is activated but before the plugin is loaded.
	 *
	 * @private
	 */
	public function configure(): void {
		if ( ! defined( 'REMOTE_DATA_BLOCKS_CONFIGS' ) ) {
			define( 'REMOTE_DATA_BLOCKS_CONFIGS', [] );
		}
	}
}
