<?php
/**
 * Generic Integration: loads a manifest-backed integration's plugin from the active-integrations mapping.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * An integration with no class of its own. GOOP lists it in the active-integrations mapping with its
 * plugin folder, entry file and version, and it's loaded from the bundles in vip-integrations/ like
 * the hand-written integrations are.
 *
 * Registered only when the `vip-generic-integrations` feature is enabled for the site
 * (see generic-integrations.php).
 *
 * @private
 */
class GenericIntegration extends Integration {
	/**
	 * Plugin folder from the manifest, also the bundle folder prefix: `<folder>-<version>`.
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
	 * Plugin version from the manifest's release.
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
	 * `site` or `network`, from the manifest.
	 *
	 * @var string
	 */
	private string $scope;

	/**
	 * Constructor.
	 *
	 * @param string $slug  Slug of the integration.
	 * @param array  $entry The integration's entry in the mapping, already validated: `folder`,
	 *                      `entry_file`, `version`, `version_strategy` and `scope`.
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
	 * True when the plugin was already loaded some other way, e.g. from the customer's repo:
	 * a file `<folder>/<entry_file>` has been included. A bundle from vip-integrations/ is in
	 * `<folder>-<version>/`, so it doesn't count.
	 *
	 * @private
	 */
	public function is_loaded(): bool {
		$suffix = '/' . $this->folder . '/' . $this->entry_file;

		foreach ( get_included_files() as $file ) {
			if ( str_ends_with( str_replace( '\\', '/', $file ), $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Loads the plugin's bundle on plugins_loaded, unless the plugin is already loaded.
	 *
	 * @private
	 */
	public function load(): void {
		add_action( 'plugins_loaded', function () {
			if ( $this->is_loaded() ) {
				return;
			}

			$load_path = $this->get_load_path();
			if ( null === $load_path ) {
				$this->is_active = false;
				return;
			}

			require_once $load_path;
		}, 1 );
	}

	/**
	 * The entry file of the bundle to load, or null when there's no bundle to load.
	 */
	public function get_load_path(): ?string {
		$folder = $this->get_selected_version_folder( $this->get_versions() );
		if ( null === $folder ) {
			return null;
		}

		$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $folder . '/' . $this->entry_file;

		return is_readable( $load_path ) ? $load_path : null;
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
	 * Keeps the `<folder>-<version>` bundles. `get_available_versions()` matches the folder prefix
	 * anywhere in the name, so a folder `sync` would otherwise pick up `acme-sync-1.0`.
	 *
	 * @param array<string,string> $versions Folder name => version.
	 *
	 * @return array<string,string> Folder name => version.
	 */
	public function filter_versions( array $versions ): array {
		return array_filter(
			$versions,
			fn( $version, $folder ) => str_starts_with( $folder, $this->folder . '-' ) && 1 === preg_match( '/^[0-9]/', (string) $version ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Picks the bundle folder. `latest` takes the newest. Otherwise the bundle for `$version`: an
	 * exact match, or its major.minor (bundles are committed as e.g. `acme-sync-1.2` for 1.2.0);
	 * if there's none, the newest, as the hand-written integrations do.
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

	/**
	 * The plugin folder from the manifest.
	 */
	public function get_folder(): string {
		return $this->folder;
	}

	/**
	 * The plugin entry file from the manifest.
	 */
	public function get_entry_file(): string {
		return $this->entry_file;
	}

	/**
	 * The plugin version from the manifest.
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * How the bundle version is picked, from the manifest.
	 */
	public function get_version_strategy(): string {
		return $this->version_strategy;
	}

	/**
	 * `site` or `network`, from the manifest.
	 */
	public function get_scope(): string {
		return $this->scope;
	}
}
