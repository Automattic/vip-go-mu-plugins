<?php
/**
 * Integration: Agent Ready Content.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the Agent Ready Content integration.
 *
 * @private
 */
class AgentReadyContentIntegration extends Integration {
	/**
	 * Returns `true` if Agent Ready Content is already available, for example via customer code.
	 * This prevents the platform from loading the plugin again.
	 */
	public function is_loaded(): bool {
		return defined( 'AGENT_READY_CONTENT_LOADED' );
	}

	/**
	 * Applies hooks to load the Agent Ready Content plugin.
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

			// Agent Ready Content requires WordPress 6.8.
			// Loading it directly bypasses WordPress's plugin requirement checks.
			// See https://github.com/Automattic/agent-ready-content/blob/v0.3.0/agent-ready-content.php#L6
			if ( version_compare( get_bloginfo( 'version' ), '6.8', '<' ) ) {
				$this->is_active = false;
				return;
			}

			$latest_directory = $this->get_latest_version();
			if ( null === $latest_directory ) {
				$this->is_active = false;
				return;
			}

			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/agent-ready-content.php';
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		}, 1 );
	}

	/**
	 * Get the latest version of Agent Ready Content.
	 *
	 * @return string|null The directory for the latest version, or null if no versions are found.
	 */
	public function get_latest_version(): ?string {
		return get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'agent-ready-content', 'agent-ready-content.php' );
	}
}
