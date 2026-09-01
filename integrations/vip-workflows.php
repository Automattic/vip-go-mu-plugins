<?php
/**
 * Integration: VIP Workflows.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the VIP Workflows integration.
 *
 * @private
 */
class VipWorkflowsIntegration extends Integration {
	/**
	 * The version of VIP Workflows to load, defaults to the latest version.
	 *
	 * @var string
	 */
	public string $version = 'latest';

	/**
	 * Enable Pendo tracking for this integration.
	 *
	 * @var bool
	 */
	protected bool $enable_pendo_tracking = true;

	public function configure(): void {
		$configs = is_multisite() ? $this->get_network_site_config() : $this->get_env_config();

		if ( isset( $configs['version'] ) && is_string( $configs['version'] ) && '' !== $configs['version'] ) {
			$this->version = $configs['version'];
		}
	}

	public function is_loaded(): bool {
		return defined( 'VIP_WORKFLOWS_LOADED' ) || defined( 'VIP_WORKFLOWS_PLUGIN_FILE' );
	}

	public function load(): void {
		add_action( 'plugins_loaded', function (): void {
			$this->load_plugin();
		}, 1 );
	}

	/**
	 * Load the selected bundled plugin file when it is available.
	 */
	private function load_plugin(): void {
		if ( $this->is_loaded() ) {
			return;
		}

		$versions = $this->get_versions();
		if ( [] === $versions ) {
			$this->is_active = false;
			return;
		}

		$folder    = $this->get_selected_version_folder( $versions );
		$main_file = sprintf( '%s/vip-integrations/%s/vip-workflows.php', WPVIP_MU_PLUGIN_DIR, $folder );

		if ( ! file_exists( $main_file ) ) {
			$this->is_active = false;
			return;
		}

		require_once $main_file;
	}

	/**
	 * Get the available versions of VIP Workflows in descending order.
	 *
	 * @return array<string,string>
	 */
	public function get_versions(): array {
		return get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'vip-workflows', 'vip-workflows.php' );
	}

	/**
	 * Get the folder name for the selected version of the integration.
	 *
	 * @param array<string,string> $versions Available versions.
	 * @return string The selected folder name.
	 */
	public function get_selected_version_folder( array $versions ): string {
		$desired_folder = 'latest' === $this->version
			? false
			: array_search( $this->version, $versions, true );

		return false !== $desired_folder ? $desired_folder : array_key_first( $versions );
	}
}
