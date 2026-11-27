<?php
/**
 * Registers a GenericIntegration for each entry in the active-integrations mapping.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\VIP\Feature;

/**
 * The feature that turns this on for a site. See lib/feature/class-feature.php.
 */
const GENERIC_INTEGRATIONS_FEATURE = 'vip-generic-integrations';

/**
 * The mapping's file in the integrations config directory.
 */
const ACTIVE_INTEGRATIONS_FILE = 'active-integrations.php';

/**
 * Keys an entry must have, with the pattern each value must match. A key added later goes in
 * ACTIVE_INTEGRATION_OPTIONAL_KEYS, so that an older mapping keeps loading.
 */
const ACTIVE_INTEGRATION_REQUIRED_KEYS = [
	'folder'     => '/^[a-z0-9]+(-[a-z0-9]+)*$/D',
	'entry_file' => '/^[A-Za-z0-9._-]+\.php$/D',
	'version'    => '/^[0-9]+\.[0-9]+\.[0-9]+([-+][A-Za-z0-9.-]+)?$/D',
];

/**
 * Keys an entry may have, with the pattern each value must match and its default.
 */
const ACTIVE_INTEGRATION_OPTIONAL_KEYS = [
	'version_strategy' => [ '/^[a-z][a-z0-9_]*$/D', 'latest' ],
	'scope'            => [ '/^(site|network)$/D', 'site' ],
];

/**
 * Whether the feature is on for this site.
 */
function generic_integrations_enabled(): bool {
	return true === Feature::is_enabled( GENERIC_INTEGRATIONS_FEATURE );
}

/**
 * Registers a GenericIntegration for each valid entry in the mapping. A slug that's already
 * registered keeps its own class.
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
 * Gets the valid entries of the mapping, keyed by slug. An invalid entry is skipped with a warning.
 *
 * @return array<string,array{folder:string,entry_file:string,version:string,version_strategy:string,scope:string}>
 */
function get_active_integrations_mapping(): array {
	/**
	 * Filters the mapping before it's read from its file.
	 *
	 * @param null|array $mapping An array to use instead of the file.
	 */
	$mapping = apply_filters( 'vip_integrations_pre_load_active_integrations', null );
	if ( null === $mapping ) {
		$mapping = read_active_integrations_file();
	}

	// A file that doesn't return gives 1, not null.
	if ( ! is_array( $mapping ) || ! is_array( $mapping['integrations'] ?? null ) ) {
		return [];
	}

	// The format doesn't change how the mapping is read: a newer one only adds keys.
	$format = $mapping['format'] ?? 1;
	if ( ! is_int( $format ) || $format < 1 ) {
		trigger_error( 'The active integrations mapping has an invalid format.', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
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
 * Reads the mapping's file.
 *
 * @return mixed What the file returns, or null when it isn't readable.
 */
function read_active_integrations_file() {
	$config_dir = defined( 'WPVIP_INTEGRATIONS_CONFIG_DIR' )
		? constant( 'WPVIP_INTEGRATIONS_CONFIG_DIR' )
		: ABSPATH . 'config/integrations-config';
	$file_path  = $config_dir . '/' . ACTIVE_INTEGRATIONS_FILE;

	// The file is updated through a symlink. See IntegrationVipConfig::get_vip_config_from_file().
	clearstatcache( true, $file_path );
	clearstatcache( true, $config_dir . '/..data' );
	clearstatcache( true, $config_dir . '/..data/' . ACTIVE_INTEGRATIONS_FILE );

	if ( ! is_readable( $file_path ) ) {
		return null;
	}

	return require $file_path;
}

/**
 * Validates an entry, keeping only the keys the loader uses. `folder` and `entry_file` end up in a
 * file path.
 *
 * @param string $slug  The entry's slug.
 * @param mixed  $entry The entry.
 *
 * @return array{folder:string,entry_file:string,version:string,version_strategy:string,scope:string}|null Null when invalid.
 */
function get_valid_active_integration_entry( string $slug, $entry ): ?array {
	if ( ! is_array( $entry ) || strlen( $slug ) > 30 || 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/D', $slug ) ) {
		return null;
	}

	// A missing optional key gets its default.
	$entry   += array_map( fn( $rule ) => $rule[1], ACTIVE_INTEGRATION_OPTIONAL_KEYS );
	$patterns = ACTIVE_INTEGRATION_REQUIRED_KEYS + array_map( fn( $rule ) => $rule[0], ACTIVE_INTEGRATION_OPTIONAL_KEYS );

	$valid = [];
	foreach ( $patterns as $key => $pattern ) {
		if ( ! is_valid_active_integration_value( $entry[ $key ] ?? null, $pattern ) ) {
			return null;
		}

		$valid[ $key ] = $entry[ $key ];
	}

	return $valid;
}

/**
 * Whether a value is a string matching its pattern.
 *
 * @param mixed  $value   The value.
 * @param string $pattern The pattern.
 */
function is_valid_active_integration_value( $value, string $pattern ): bool {
	return is_string( $value ) && 1 === preg_match( $pattern, $value );
}
