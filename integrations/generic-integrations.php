<?php
/**
 * Registers a GenericIntegration for each entry in the active-integrations mapping.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\VIP\Feature;

/**
 * The kill switch. Off unless `Feature` enables it for the site (see lib/feature/class-feature.php);
 * while it's off, nothing here reads the mapping or registers anything.
 */
const GENERIC_INTEGRATIONS_FEATURE = 'vip-generic-integrations';

/**
 * The mapping's file, next to the `<slug>-config.php` files in the integrations config directory.
 */
const ACTIVE_INTEGRATIONS_FILE = 'active-integrations.php';

/**
 * The newest mapping format this loader knows. It reads any format from 1 up, entry by entry, so
 * mu-plugins and the platform can deploy in either order:
 *
 * - A newer mapping is still read; this loader uses the keys it knows and ignores the rest, and
 *   warns that the mapping is newer than it.
 * - Only the keys in ACTIVE_INTEGRATION_REQUIRED_KEYS are required, and those are in every format.
 *   Any key added later is optional, with a default, so a newer loader reads an older mapping.
 * - The platform never removes a key or changes what one means; a new meaning gets a new key.
 */
const ACTIVE_INTEGRATIONS_FORMAT_VERSION = 1;

/**
 * Keys an entry must have, with the rule each value must match. Never add to this: a key added
 * later goes in ACTIVE_INTEGRATION_OPTIONAL_KEYS, or an older mapping stops loading.
 *
 * `D`: `$` matches only at the very end, not before a trailing newline.
 */
const ACTIVE_INTEGRATION_REQUIRED_KEYS = [
	'folder'     => '/^[a-z0-9]+(-[a-z0-9]+)*$/D',
	'entry_file' => '/^[A-Za-z0-9._-]+\.php$/D',
	'version'    => '/^[0-9]+\.[0-9]+\.[0-9]+([-+][A-Za-z0-9.-]+)?$/D',
];

/**
 * Keys an entry may have, with the rule each value must match and the default when it's missing.
 */
const ACTIVE_INTEGRATION_OPTIONAL_KEYS = [
	'version_strategy' => [ '/^[a-z][a-z0-9_]*$/D', 'latest' ],
	'scope'            => [ '/^(site|network)$/D', 'site' ],
];

/**
 * Whether manifest-backed integrations are loaded from the active-integrations mapping on this site.
 */
function generic_integrations_enabled(): bool {
	return class_exists( Feature::class ) && true === Feature::is_enabled( GENERIC_INTEGRATIONS_FEATURE );
}

/**
 * Registers a GenericIntegration for each valid entry in the active-integrations mapping, after the
 * hand-written integrations. A slug that's already registered keeps its hand-written class. Does
 * nothing while the `vip-generic-integrations` feature is off.
 *
 * @param Integrations $integrations Where to register them.
 */
function register_generic_integrations( Integrations $integrations ): void {
	if ( ! generic_integrations_enabled() ) {
		return;
	}

	foreach ( get_active_integrations_mapping() as $slug => $entry ) {
		if ( null !== $integrations->get_integration( $slug ) ) {
			continue;
		}

		$integrations->register( new GenericIntegration( $slug, $entry ) );
	}
}

/**
 * The valid entries of the active-integrations mapping, keyed by slug. Empty when there's no
 * mapping, or it isn't an array in a format this loader knows. An invalid entry is skipped with a
 * warning.
 *
 * GOOP writes the mapping: into the `integrations-config` ConfigMap on K8s, and as the
 * `VIP_ACTIVE_INTEGRATIONS` constant on WP Cloud, which vip-wpcloud-externals returns from a
 * static active-integrations.php.
 *
 * @return array<string,array{folder:string,entry_file:string,version:string,version_strategy:string,scope:string}>
 */
function get_active_integrations_mapping(): array {
	/**
	 * Filters the active-integrations mapping before it's read from its file. Return an array to
	 * use it instead of the file.
	 *
	 * @param null|array $mapping Null to read the file.
	 */
	$mapping = apply_filters( 'vip_integrations_pre_load_active_integrations', null );
	if ( null === $mapping ) {
		$mapping = read_active_integrations_file();
	}

	// A config file that doesn't reach a `return` gives 1, so check for an array, not for null.
	if (
		! is_array( $mapping ) ||
		! is_int( $mapping['version'] ?? null ) ||
		$mapping['version'] < 1 ||
		! is_array( $mapping['integrations'] ?? null )
	) {
		return [];
	}

	if ( $mapping['version'] > ACTIVE_INTEGRATIONS_FORMAT_VERSION ) {
		trigger_error( sprintf( 'The active integrations mapping is format %d; this loader knows up to %d, and reads the keys it knows.', (int) $mapping['version'], (int) ACTIVE_INTEGRATIONS_FORMAT_VERSION ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
	}

	$entries = [];
	foreach ( $mapping['integrations'] as $slug => $entry ) {
		$slug  = (string) $slug;
		$valid = get_valid_active_integration_entry( $slug, $entry );
		if ( null === $valid ) {
			trigger_error( sprintf( 'Skipped invalid entry "%s" in the active integrations mapping.', esc_html( $slug ) ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			continue;
		}

		$entries[ $slug ] = $valid;
	}

	return $entries;
}

/**
 * The mapping from active-integrations.php in the integrations config directory, or null when
 * there's no readable file.
 *
 * @return mixed
 */
function read_active_integrations_file() {
	$config_dir = defined( 'WPVIP_INTEGRATIONS_CONFIG_DIR' )
		? constant( 'WPVIP_INTEGRATIONS_CONFIG_DIR' )
		: ABSPATH . 'config/integrations-config';
	$file_path  = $config_dir . '/' . ACTIVE_INTEGRATIONS_FILE;

	// The ConfigMap updates its files through a symlink, so clear the cached stat as
	// IntegrationVipConfig does for `<slug>-config.php`.
	clearstatcache( true, $file_path );
	clearstatcache( true, $config_dir . '/..data' );
	clearstatcache( true, $config_dir . '/..data/' . ACTIVE_INTEGRATIONS_FILE );

	if ( ! is_readable( $file_path ) ) {
		return null;
	}

	return require $file_path;
}

/**
 * The entry with only the keys the loader uses, or null when a required key is missing or any key
 * the loader uses is invalid. An optional key that's missing gets its default. The rules match
 * GOOP's slug rule and the manifest schema's, so nothing GOOP writes is refused, and nothing else
 * reaches a file path.
 *
 * @param string $slug  The entry's slug.
 * @param mixed  $entry The entry.
 *
 * @return array{folder:string,entry_file:string,version:string,version_strategy:string,scope:string}|null
 */
function get_valid_active_integration_entry( string $slug, $entry ): ?array {
	if ( ! is_array( $entry ) || strlen( $slug ) > 30 || 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/D', $slug ) ) {
		return null;
	}

	$valid = [];
	foreach ( ACTIVE_INTEGRATION_REQUIRED_KEYS as $key => $pattern ) {
		if ( ! is_valid_active_integration_value( $entry[ $key ] ?? null, $pattern ) ) {
			return null;
		}

		$valid[ $key ] = $entry[ $key ];
	}

	foreach ( ACTIVE_INTEGRATION_OPTIONAL_KEYS as $key => [ $pattern, $default ] ) {
		if ( ! array_key_exists( $key, $entry ) ) {
			$valid[ $key ] = $default;
			continue;
		}

		if ( ! is_valid_active_integration_value( $entry[ $key ], $pattern ) ) {
			return null;
		}

		$valid[ $key ] = $entry[ $key ];
	}

	return $valid;
}

/**
 * Whether a mapping value is a string matching its rule.
 *
 * @param mixed  $value   The value.
 * @param string $pattern The rule.
 */
function is_valid_active_integration_value( $value, string $pattern ): bool {
	return is_string( $value ) && 1 === preg_match( $pattern, $value );
}
