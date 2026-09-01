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

	public function is_loaded(): bool {
		return defined( 'VIP_WORKFLOWS_LOADED' ) || defined( 'VIP_WORKFLOWS_PLUGIN_FILE' );
	}

	public function load(): void {
		add_action( 'plugins_loaded', function () {
			if ( $this->is_loaded() ) {
				return;
			}

			$versions = $this->get_versions();

			if ( empty( $versions ) ) {
				$this->is_active = false;
				return;
			}

			$selected_version_folder = $this->get_selected_version_folder( $versions );
			$load_path               = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $selected_version_folder . '/vip-workflows.php';

			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		}, 1 );
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
		if ( 'latest' === $this->version ) {
			return array_key_first( $versions );
		}

		$desired_version = array_search( $this->version, $versions, true );

		if ( false !== $desired_version ) {
			return $desired_version;
		}

		return array_key_first( $versions );
	}
}
