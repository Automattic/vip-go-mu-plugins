<?php

// Standalone loader fixture: WordPress hook and metadata boundaries only.
// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped, Generic.CodeAnalysis.UnusedFunctionParameter
$wp_version = $argv[2];
define( 'WP_INSTALLING', true );
define( 'WPVIP_MU_PLUGIN_DIR', $argv[1] );
define( 'WPCOM_VIP_CLIENT_MU_PLUGIN_DIR', $argv[1] . '/client' );
define( 'VIP_JETPACK_DEFAULT_VERSION', 'default' );
if ( 'local' === $argv[3] ) {
	define( 'WPCOM_VIP_JETPACK_LOCAL', true );
}
if ( 'pinned' === $argv[3] ) {
	define( 'VIP_JETPACK_PINNED_VERSION', 'pinned' );
}
if ( 'skip' === $argv[3] ) {
	define( 'VIP_JETPACK_SKIP_LOAD', true );
}
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function is_local_env() { return 'local' === $GLOBALS['argv'][4]; }
function is_multisite() { return false; }
function wp_in( $needle, $haystack ) { return str_contains( $haystack, $needle ); }
function get_file_data( $path, $headers ) {
	$text = file_get_contents( $path );
	foreach ( $headers as $key => $header ) {
		preg_match( '/^\s*\*\s*' . preg_quote( $header, '/' ) . ':\s*(.*)$/m', $text, $matches );
		$headers[ $key ] = trim( $matches[1] ?? '' );
	}
	return $headers;
}
$warnings = [];
set_error_handler( function ( $severity, $message ) use ( &$warnings ) {
	$warnings[] = $message;
	return true;
} );
require $argv[1] . '/loader.php';
vip_jetpack_load();
echo json_encode( [
	'loaded' => defined( 'VIP_JETPACK_LOADED_VERSION' ) ? VIP_JETPACK_LOADED_VERSION : null,
	'plugin' => defined( 'JETPACK__VERSION' ) ? JETPACK__VERSION : null,
	'integration' => $GLOBALS['integration_loaded'] ?? false,
	'warnings' => $warnings,
] );
