<?php
/**
 * Integration: Content for Agents.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the Content for Agents integration.
 *
 * @private
 */
class ContentForAgentsIntegration extends Integration {
	/**
	 * Returns `true` if Content for Agents is already available, for example via customer code.
	 * This prevents the platform from loading the plugin again.
	 */
	public function is_loaded(): bool {
		return defined( 'CONTENT_FOR_AGENTS_LOADED' );
	}

	/**
	 * Applies hooks to load the Content for Agents plugin.
	 *
	 * @private
	 */
	public function load(): void {
		// Wait until plugins_loaded to give precedence to the plugin in the customer repo.
		// Use priority 1 so the plugin can initialize at plugins_loaded priority 10.
		add_action( 'plugins_loaded', function (): void {
			if ( $this->is_loaded() ) {
				return;
			}

			$latest_directory = $this->get_latest_version();
			if ( null === $latest_directory ) {
				$this->is_active = false;
				return;
			}

			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/content-for-agents.php';
			if ( file_exists( $load_path ) ) {
				// Let the plugin check its requirements and show notices when they are unmet.
				require_once $load_path;

				if ( ! $this->is_loaded() ) {
					$this->is_active = false;
				}
			} else {
				$this->is_active = false;
			}
		}, 1 );
	}

	/**
	 * Get the latest version of Content for Agents.
	 *
	 * @return string|null The directory for the latest version, or null if no versions are found.
	 */
	public function get_latest_version(): ?string {
		return get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'content-for-agents', 'content-for-agents.php' );
	}
}
