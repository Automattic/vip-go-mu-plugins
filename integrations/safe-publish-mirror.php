<?php
/**
 * Integration: Safe Publish Mirror.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the Safe Publish Mirror integration.
 *
 * Unlike the original Safe Publish integration, which exposes each value as its
 * own scalar constant, Safe Publish Mirror reads a single associative-array
 * constant, VIP_SAFE_PUBLISH_MIRROR_CONFIG, as declared in the partner handoff
 * manifest. The constant is always defined when the integration is active so the
 * plugin's config reader can report incomplete setups gracefully instead of
 * fataling on a missing constant.
 *
 * @private
 */
class SafePublishMirrorIntegration extends Integration {
	/**
	 * The version of Safe Publish Mirror to load, defaults to the latest version.
	 *
	 * @var string
	 */
	public string $version = 'latest';

	public function is_loaded(): bool {
		return defined( 'SAFE_PUBLISH_MIRROR_LOADED' ) || defined( 'SAFE_PUBLISH_MIRROR_PLUGIN_FILE' );
	}

	public function configure(): void {
		if ( defined( 'VIP_SAFE_PUBLISH_MIRROR_CONFIG' ) ) {
			return;
		}

		$configs = $this->get_mirror_config();

		if ( isset( $configs['version'] ) && is_string( $configs['version'] ) && '' !== $configs['version'] ) {
			$this->version = $configs['version'];
		}

		// Always define the constant so the plugin's config reader can detect and
		// report missing required fields rather than fataling on an undefined
		// constant. Absent values are passed through as null.
		define( 'VIP_SAFE_PUBLISH_MIRROR_CONFIG', [
			'connected_site_url' => $configs['connected_site_url'] ?? null,
			'sync_mode'          => $configs['sync_mode'] ?? null,
			'shared_secret'      => $configs['shared_secret'] ?? null,
		] );
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
			$load_path               = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $selected_version_folder . '/safe-publish-mirror.php';

			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		}, 1 );
	}

	/**
	 * Get the available versions of Safe Publish Mirror in descending order.
	 *
	 * @return array<string,string>
	 */
	public function get_versions(): array {
		return get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'safe-publish-mirror', 'safe-publish-mirror.php' );
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

	/**
	 * Get Safe Publish Mirror configuration for the current site context.
	 *
	 * On multisite the connection values (connected site URL, sync mode, shared
	 * secret) are set per network site, so merge the network-site config over the
	 * environment config to build a complete runtime config for the current site.
	 *
	 * @return array<string,mixed>
	 */
	private function get_mirror_config(): array {
		if ( ! is_multisite() ) {
			return $this->get_env_config();
		}

		return array_merge( $this->get_env_config(), $this->get_network_site_config() );
	}
}
