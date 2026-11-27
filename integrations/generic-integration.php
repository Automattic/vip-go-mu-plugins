<?php
/**
 * Integration: Generic, for the entries of the active-integrations mapping.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// Deploys are non-atomic, so this file can arrive before the trait it uses. Without the trait, skip
// declaring the class, which leaves these integrations unregistered until the deploy finishes.
if ( ! file_exists( __DIR__ . '/bundled-plugin-loader.php' ) ) {
	return;
}

require_once __DIR__ . '/bundled-plugin-loader.php';

/**
 * Loads an integration listed in the active-integrations mapping. See generic-integrations.php.
 *
 * @private
 */
class GenericIntegration extends Integration {
	use LoadsLatestBundledPlugin;

	/**
	 * Plugin folder, and the prefix of its bundle folders: `<folder>-<version>`.
	 *
	 * @var string
	 */
	private string $folder;

	/**
	 * Plugin entry file, relative to the bundle folder.
	 *
	 * @var string
	 */
	private string $entry_file;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * How the bundle is picked: `latest` takes the newest; anything else pins to `$version`.
	 *
	 * @var string
	 */
	private string $version_strategy;

	/**
	 * `site` or `network`.
	 *
	 * @var string
	 */
	private string $scope;

	/**
	 * The bundle folder that was loaded, e.g. `acme-sync-1.2`, or null.
	 *
	 * @var string|null
	 */
	private ?string $loaded_bundle = null;

	/**
	 * Constructor.
	 *
	 * @param string $slug  Slug of the integration.
	 * @param array  $entry The integration's entry in the mapping, already validated.
	 */
	public function __construct( string $slug, array $entry ) {
		parent::__construct( $slug );

		$this->folder           = $entry['folder'];
		$this->entry_file       = $entry['entry_file'];
		$this->version          = $entry['version'];
		$this->version_strategy = $entry['version_strategy'];
		$this->scope            = $entry['scope'];
	}

	/**
	 * True once its bundle is loaded, or when the plugin's entry file has defined `VIP_<SLUG>_LOADED`,
	 * e.g. from the customer's own copy.
	 *
	 * @private
	 */
	public function is_loaded(): bool {
		return null !== $this->loaded_bundle || defined( $this->get_constant_name( 'LOADED' ) );
	}

	/**
	 * Defines `VIP_<SLUG>_CONFIG` from the integration's config, unless it's already defined. On
	 * multisite a network site inherits the environment's config, and its own values win.
	 *
	 * @private
	 */
	public function configure(): void {
		$constant = $this->get_config_constant_name();
		if ( defined( $constant ) ) {
			return;
		}

		$config = $this->get_env_config();
		if ( is_multisite() ) {
			$config = array_merge( $config, $this->get_network_site_config() );
		}

		define( $constant, $config );
	}

	/**
	 * The name of the plugin's config constant, e.g. `VIP_ACME_SYNC_CONFIG` for `acme-sync`.
	 */
	public function get_config_constant_name(): string {
		return $this->get_constant_name( 'CONFIG' );
	}

	/**
	 * The name of one of the plugin's constants, e.g. `VIP_ACME_SYNC_LOADED` for `acme-sync`.
	 *
	 * @param string $suffix `CONFIG` or `LOADED`.
	 */
	private function get_constant_name( string $suffix ): string {
		return 'VIP_' . strtoupper( str_replace( '-', '_', $this->get_slug() ) ) . '_' . $suffix;
	}

	/**
	 * Loads the plugin's bundle on plugins_loaded, unless the plugin is already loaded.
	 *
	 * @private
	 */
	public function load(): void {
		// An integration activated from site code skips activate_platform_integrations(), which calls this.
		$this->configure();

		add_action( 'plugins_loaded', [ $this, 'load_bundle' ], 1 );
	}

	/**
	 * Requires the bundle's entry file. With no bundle to load, or one that needs a newer WordPress
	 * than the site's, the integration is no longer active.
	 *
	 * @private
	 */
	public function load_bundle(): void {
		if ( $this->is_loaded() ) {
			return;
		}

		$folder = $this->get_bundle_folder();
		if ( null === $folder ) {
			$this->is_active = false;
			return;
		}

		if ( ! $this->is_supported_wp_version( $this->get_bundle_path( $folder ) ) ) {
			$this->is_active = false;
			return;
		}

		require_once $this->get_bundle_path( $folder );

		$this->loaded_bundle = $folder;
	}

	/**
	 * The bundle folder to load, or null when there's none with a readable entry file.
	 */
	public function get_bundle_folder(): ?string {
		$folder = $this->get_selected_version_folder( $this->get_versions() );

		return null !== $folder && is_readable( $this->get_bundle_path( $folder ) ) ? $folder : null;
	}

	/**
	 * The bundle folder to load. LoadsLatestBundledPlugin requires this method.
	 */
	public function get_latest_version(): ?string {
		return $this->get_bundle_folder();
	}

	/**
	 * The entry file's path in a bundle folder in vip-integrations/.
	 *
	 * @param string $folder The bundle folder, e.g. `acme-sync-1.2`.
	 */
	public function get_bundle_path( string $folder ): string {
		return WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $folder . '/' . $this->entry_file;
	}

	/**
	 * Bundles of this plugin in vip-integrations/, newest first, keyed by folder name.
	 *
	 * @return array<string,string> Folder name => version.
	 */
	public function get_versions(): array {
		return $this->filter_versions(
			get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', $this->folder, $this->entry_file )
		);
	}

	/**
	 * Keeps the `<folder>-<version>` bundles, where the version is numbers and dots.
	 * get_available_versions() matches the prefix anywhere in the name, so `sync` would otherwise
	 * pick up `acme-sync-1.0`.
	 *
	 * @param array<string,string> $versions Folder name => version.
	 *
	 * @return array<string,string> Folder name => version.
	 */
	public function filter_versions( array $versions ): array {
		$prefix = $this->folder . '-';

		return array_filter(
			$versions,
			fn( $folder ) => str_starts_with( (string) $folder, $prefix )
				&& 1 === preg_match( '/^\d+(\.\d+)*$/D', substr( (string) $folder, strlen( $prefix ) ) ),
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Picks the bundle folder. `latest` takes the newest. Any other strategy takes the bundle for
	 * `$version`, or for its major.minor (e.g. `acme-sync-1.2` for 1.2.0), or else the newest.
	 *
	 * @param array<string,string> $versions Folder name => version, newest first.
	 */
	public function get_selected_version_folder( array $versions ): ?string {
		if ( empty( $versions ) ) {
			return null;
		}

		if ( 'latest' !== $this->version_strategy ) {
			$parts = explode( '.', $this->version );
			foreach ( [ $this->version, $parts[0] . '.' . ( $parts[1] ?? '0' ) ] as $wanted ) {
				$folder = array_search( $wanted, $versions, true );
				if ( false !== $folder ) {
					return $folder;
				}
			}
		}

		return array_key_first( $versions );
	}
}
