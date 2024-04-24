<?php
/**
 * Integration: Enterprise Search.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Enterprise Search VIP Integration.
 */
class EnterpriseSearchIntegration extends Integration {
	/**
	 * Returns `true` if Enterprise Search is already available e.g. customer code. We will use
	 * this function to prevent loading of integration again from platform side.
	 */
	public function is_loaded(): bool {
		return class_exists( \Automattic\VIP\Search\Search::class );
	}

	/**
	 * Loads the plugin.
	 */
	public function load(): void {
		// Return if the integration is already loaded.
		//
		// In activate() method we do make sure to not activate the integration if its already loaded
		// but still adding it here as a safety measure i.e. if load() is called directly.
		if ( $this->is_loaded() ) {
			return;
		}

		require_once __DIR__ . '/../search/search.php';
	}
}
