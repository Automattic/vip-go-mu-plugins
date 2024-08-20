<?php
/**
 * Integration: Remote Data Blocks.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads "Remote Data Blocks" Integration.
 *
 * @private
 */
class RemoteDataBlocksIntegration extends Integration {
	/**
	 * The version of the "Remote Data Blocks" plugin to load, that's set to the latest version.
	 * This should be higher than the `lowestVersion` set in "remote-data-blocks" config (https://github.com/Automattic/vip-go-mu-plugins-ext/blob/trunk/config.json)
	 *
	 * @var string
	 */
	protected string $version = '0.1.0';

	/**
	 * "Remote Data Blocks" integration doesn't have its own config and is dependent on configs provided by data source integrations.
	 *
	 * @var array<Integration>
	 */
	protected array $data_source_integrations = [];

	/**
	 * List of data sources. A new `DataSourceIntegration` object will be instantiated for each data source.
	 *
	 * @var array<string>
	 */
	protected array $data_source_slugs = [ 'airtable', 'shopify' ];

	/**
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		foreach ( $this->data_source_slugs as $data_source_slug ) {
			$integration                      = new DataSourceIntegration( $data_source_slug );
			$this->data_source_integrations[] = $integration;

			IntegrationsSingleton::instance()->register( $integration );
		}

		parent::__construct( $slug );
	}

	/**
	 * Returns `true` if any data source integration is enabled else `false`.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function is_active_via_vip(): bool {
		// Returns 'true` if any of the data source integration is active.
		foreach ( $this->data_source_integrations as $integration ) {
			if ( $integration->is_active_via_vip() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get configs of all data source integrations in context of current site.
	 *
	 * @return array<array<mixed>> Returns an array of configs.
	 *
	 * @private
	 */
	public function get_site_configs() {
		/**
		 * Array containing configs of the all data source integrations.
		 *
		 * @var array<array<mixed>>
		 */
		$all_configs = [];

		// Merge configs of all data source integrations.
		foreach ( $this->data_source_integrations as $integration ) {
			$all_configs = array_merge( $all_configs, $integration->get_config() );
		}

		return $all_configs;
	}

	/**
	 * Returns `true` if `Remote Data Blocks` is already available e.g. customer code. We will use
	 * this function to prevent loading of integration again from platform side.
	 */
	public function is_loaded(): bool {
		return defined( 'REMOTE_DATA_BLOCKS__PLUGIN_VERSION' );
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
		$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/remote-data-blocks-' . $this->version . '/remote-data-blocks.php';
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
		add_filter( 'vip_integration_remote_data_blocks_config', function () {
			return $this->get_config();
		});
	}
}


/**
 * Data Source Integration.
 *
 * It's a generic class that can be used to create data source integrations.
 *
 * @private
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Commenting.ClassComment.Missing
class DataSourceIntegration extends Integration {
	/**
	 * A boolean indicating if the integration have multiple setups of same integration.
	 *
	 * @var bool
	 */
	protected bool $have_multiple_setups = true;

	/**
	 * Returns status of the plugin.
	 */
	public function is_loaded(): bool {
		return false; // Returning `false` because the integration is a data source for "Remote Data Blocks".
	}

	/**
	 * Loads the plugin. No implementation needed because the integration is a data source for "Remote Data Blocks".
	 *
	 * @private
	 */
	public function load(): void {}
}
