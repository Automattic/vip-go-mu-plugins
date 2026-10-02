<?php

// Isolate the missing-Pilot class case; normal WordPress tests already load the class.
// phpcs:disable Universal.Namespaces, Generic.Files.OneObjectStructurePerFile, Generic.Classes.DuplicateClassName, Generic.CodeAnalysis.UnusedFunctionParameter, WordPress.NamingConventions.ValidFunctionName, WordPress.WP.GlobalVariablesOverride, WordPress.WP.AlternativeFunctions.json_encode_json_encode

namespace Automattic\VIP\Jetpack {
	if ( 'missing' !== $argv[2] ) {
		class Connection_Pilot {
			public static function instance() {
				return new self(); }
			public function run_connection_pilot() {
				++$GLOBALS['pilot_calls']; }
		}
	}
}

namespace {
	define( 'VIP_GO_APP_ENVIRONMENT', $argv[1] );
	define( 'VIP_JETPACK_SKIP_LOAD', 'skip' === $argv[2] );
	$GLOBALS['pilot_calls'] = 0;
	$warnings               = [];
	function add_action( ...$args ) {}
	function is_local_env() {
		return false;
	}
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Capture the real missing-integration warning.
	set_error_handler( function ( $severity, $message ) use ( &$warnings ) {
		$warnings[] = $message;
		return true;
	} );
	require __DIR__ . '/../../../vip-helpers/vip-migrations.php';
	\Automattic\VIP\Migration\run_connection_pilot_after_cleanup();
	if ( 'missing' === $argv[2] ) {
		// Repeated cleanup should emit only one warning.
		\Automattic\VIP\Migration\run_connection_pilot_after_cleanup();
	}
	echo json_encode( [
		'calls'    => $GLOBALS['pilot_calls'],
		'warnings' => $warnings,
	] );
}
