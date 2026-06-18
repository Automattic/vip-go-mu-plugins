<?php
/**
 * Integration: Safe Publish.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the Safe Publish integration.
 *
 * @private
 */
class SafePublishIntegration extends Integration {
	/**
	 * The version of Safe Publish to load, defaults to the latest version.
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
		return defined( 'SAFE_PUBLISH_LOADED' ) || defined( 'SAFE_PUBLISH_PLUGIN_FILE' );
	}

	public function configure(): void {
		$configs = $this->get_safe_publish_config();

		$this->define_config_constant( 'SAFE_PUBLISH_CONNECTED_SITE_URL', $configs['connected_site_url'] ?? null );
		$this->define_config_constant( 'SAFE_PUBLISH_SYNC_MODE', $configs['sync_mode'] ?? null );
		$this->define_config_constant( 'SAFE_PUBLISH_SHARED_SECRET', $configs['shared_secret'] ?? null );
		$this->define_config_constant( 'SAFE_PUBLISH_BASIC_AUTH_USERNAME', $configs['basic_auth_username'] ?? null );
		$this->define_config_constant( 'SAFE_PUBLISH_BASIC_AUTH_PASSWORD', $configs['basic_auth_password'] ?? null );

		if ( isset( $configs['version'] ) && is_string( $configs['version'] ) && '' !== $configs['version'] ) {
			$this->version = $configs['version'];
		}
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
			$load_path               = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $selected_version_folder . '/safe-publish.php';

			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		}, 1 );
	}

	/**
	 * Get the available versions of Safe Publish in descending order.
	 *
	 * @return array<string,string>
	 */
	public function get_versions(): array {
		return get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'safe-publish', 'safe-publish.php' );
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
	 * Get Safe Publish configuration for the current site context.
	 *
	 * On multisite, Safe Publish config is split across levels. Site-level
	 * config holds shared values like Basic Auth and version, while
	 * network-site config holds connection values like the connected site URL,
	 * sync mode, and shared secret. Merge both levels so the current network
	 * site gets a complete runtime config.
	 *
	 * @return array<string,mixed>
	 */
	private function get_safe_publish_config(): array {
		if ( ! is_multisite() ) {
			return $this->get_env_config();
		}

		return array_merge( $this->get_env_config(), $this->get_network_site_config() );
	}

	/**
	 * Define a Safe Publish configuration constant.
	 *
	 * @param string $constant_name Constant name.
	 * @param mixed  $value         Constant value.
	 */
	private function define_config_constant( string $constant_name, mixed $value ): void {
		if ( defined( $constant_name ) || ! is_string( $value ) || '' === $value ) {
			return;
		}

		define( $constant_name, $value );
	}
}
