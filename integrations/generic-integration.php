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
	 * How loading went: null until `configure()` refuses or `load_bundle()` runs, then
	 * `config_conflict`, `loaded`, `already_loaded` or `missing_bundle`.
	 *
	 * @var string|null
	 */
	private ?string $load_status = null;

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
	 * Defines the plugin's config constant, `VIP_<SLUG>_CONFIG`, from the integration's config. On
	 * multisite that's the current network site's config, which is where the platform writes it, as
	 * for the hand-written integrations (e.g. Parse.ly); otherwise the environment's. Values are typed
	 * by GOOP from the manifest's fields.
	 *
	 * When the constant is already defined, e.g. in vip-config.php, the integration isn't loaded:
	 * that config would skip GOOP's validation and replace the config the platform manages, secrets
	 * included. It's deactivated with a warning, and reported as `config_conflict`.
	 *
	 * @private
	 */
	public function configure(): void {
		$constant = $this->get_config_constant_name();
		if ( defined( $constant ) ) {
			$this->is_active   = false;
			$this->load_status = 'config_conflict';
			trigger_error( sprintf( 'VIP Integration "%s" was not loaded: %s is already defined, and its config comes from the platform.', esc_html( $this->get_slug() ), esc_html( $constant ) ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return;
		}

		$config = is_multisite() ? $this->get_network_site_config() : $this->get_env_config();

		define( $constant, $config );
	}

	/**
	 * The plugin's config constant: the slug upper-cased, with `-` as `_`, e.g. `acme-sync` is
	 * `VIP_ACME_SYNC_CONFIG`. The manifest doesn't name it; it's always derived from the slug.
	 */
	public function get_config_constant_name(): string {
		return 'VIP_' . strtoupper( str_replace( '-', '_', $this->get_slug() ) ) . '_CONFIG';
	}

	/**
	 * Loads the plugin's bundle on plugins_loaded, unless the plugin is already loaded.
	 *
	 * @private
	 */
	public function load(): void {
		add_action( 'plugins_loaded', [ $this, 'load_bundle' ], 1 );
	}

	/**
	 * Requires the bundle's entry file, and records how loading went for `get_runtime_status()`. With
	 * no bundle to load, the integration is no longer active.
	 *
	 * @private
	 */
	public function load_bundle(): void {
		if ( $this->is_loaded() ) {
			$this->load_status = 'already_loaded';
			return;
		}

		$folder = $this->get_bundle_folder();
		if ( null === $folder ) {
			$this->is_active   = false;
			$this->load_status = 'missing_bundle';
			return;
		}

		require_once $this->get_bundle_path( $folder );

		$this->load_status   = 'loaded';
		$this->loaded_bundle = $folder;
	}

	/**
	 * The entry file of the bundle to load, or null when there's no bundle to load.
	 */
	public function get_load_path(): ?string {
		$folder = $this->get_bundle_folder();

		return null === $folder ? null : $this->get_bundle_path( $folder );
	}

	/**
	 * The bundle folder to load, or null when there's none with a readable entry file.
	 */
	public function get_bundle_folder(): ?string {
		$folder = $this->get_selected_version_folder( $this->get_versions() );

		return null !== $folder && is_readable( $this->get_bundle_path( $folder ) ) ? $folder : null;
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
	 * How the integration loaded, for the Site Details Service. Holds no config, so nothing secret.
	 *
	 * `status` is `inactive` (not activated, or deactivated), `pending` (activated, not loaded yet),
	 * `loaded` (its bundle was required), `already_loaded` (its plugin was loaded some other way,
	 * e.g. from the customer's repo), `config_conflict` (its config constant was already defined, so
	 * it wasn't loaded) or `missing_bundle` (no bundle in vip-integrations/, so nothing
	 * was loaded). `bundle` is the folder that was loaded, or null.
	 *
	 * @return array{slug:string,folder:string,version:string,version_strategy:string,scope:string,active:bool,status:string,bundle:?string}
	 */
	public function get_runtime_status(): array {
		if ( null !== $this->load_status ) {
			$status = $this->load_status;
		} else {
			$status = $this->is_active() ? 'pending' : 'inactive';
		}

		return [
			'slug'             => $this->get_slug(),
			'folder'           => $this->folder,
			'version'          => $this->version,
			'version_strategy' => $this->version_strategy,
			'scope'            => $this->scope,
			'active'           => $this->is_active(),
			'status'           => $status,
			'bundle'           => $this->loaded_bundle,
		];
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
