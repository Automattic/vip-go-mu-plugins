<?php
/**
 * Integration: Airtable.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Airtable Integration.
 *
 * @private
 */
class AirtableIntegration extends Integration {
	/**
	 * A boolean indicating if the integration have multiple configs.
	 *
	 * @var boolean
	 */
	protected bool $have_multiple_configs = true;

	/**
	 * Returns status of the plugin.
	 */
	public function is_loaded(): bool {
		return false; // Returning `false` because the integration is a data source for Composable Blocks.
	}

	/**
	 * Loads the plugin. No implementation needed because the integration is a data source for Composable Blocks.
	 *
	 * @private
	 */
	public function load(): void {}
}
