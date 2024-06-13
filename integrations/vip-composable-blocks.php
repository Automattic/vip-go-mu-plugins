<?php
/**
 * Integration: VIP Composable Blocks.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

require_once 'vip-composable-blocks/airtable.php';
require_once 'vip-composable-blocks/shopify.php';

/**
 * Loads VIP Composable Blocks Integration.
 *
 * @private
 */
class VipComposableBlocksIntegration extends Integration {
	/**
	 * The version of the "VIP Composable Blocks" plugin to load, that's set to the latest version.
	 * This should be higher than the `lowestVersion` set in "vip-composable-blocks" config (https://github.com/Automattic/vip-go-mu-plugins-ext/blob/trunk/config.json)
	 *
	 * @var string
	 */
	protected string $version = '1.0';

	/**
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		$this->child_integrations = [
			new AirtableIntegration( 'airtable' ),
			new ShopifyIntegration( 'shopify' ),
		];

		parent::__construct( $slug );
	}

	/**
	 * Returns `true` if `VIP Composable Blocks` is already available e.g. customer code. We will use
	 * this function to prevent loading of integration again from platform side.
	 */
	public function is_loaded(): bool {
		return defined( 'VIP_COMPOSABLE_BLOCKS__REST_NAMESPACE' );
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

		// Load the version of the plugin that should be set to the latest version, otherwise if it's not found deactivate the integration.
		$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/vip-composable-blocks-' . $this->version . '/vip-composable-blocks.php';
		if ( file_exists( $load_path ) ) {
			require_once $load_path;
		} else {
			$this->is_active = false;
		}
	}

	/**
	 * Configures the plugin.
	 *
	 * @private
	 */
	public function configure(): void {
		add_filter( 'vip_integration_composable_blocks_config', function () {
			return $this->get_config();
		});
	}
}
