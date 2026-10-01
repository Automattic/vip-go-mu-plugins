<?php

// Standalone process: emulate external WordPress/CLI/Pilot boundaries without loading them.
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
	define( 'WP_CLI', true );
	$GLOBALS['pilot_calls'] = 0;
	$GLOBALS['warnings']    = [];
	class WP_CLI {
		public static function add_command( $name, $command ): void {}
		public static function success( $message ): void {}
		public static function warning( $message ): void {
			$GLOBALS['warnings'][] = $message; }
	}
	class WPCOM_VIP_CLI_Command {}
	function add_action( ...$args ) {}
	function do_action( ...$args ) {}
	function has_action( ...$args ) {
		return false; }
	function is_local_env() {
		return false; }
	function is_multisite() {
		return false; }
	function dbDelta( ...$args ) {}
	function wp_cache_flush() {}
	function delete_option( $name ) {}
	function get_option( $name, $default = false ) {
		return $default; }
	$wpdb = new class() {
		public $options = 'test_options';
		public function query( $query ) {
			return 0; }
	};
	require __DIR__ . '/../../../vip-helpers/vip-migrations.php';
	require __DIR__ . '/../../../wp-cli/vip-data-cleanup.php';
	try {
		if ( 'migration' === $argv[3] ) {
			\Automattic\VIP\Migration\run_after_data_migration_cleanup();
		} else {
			( new VIP_Data_Cleanup_Command() )->sql_import();
		}
		echo json_encode( [
			'calls'    => $GLOBALS['pilot_calls'],
			'warnings' => $GLOBALS['warnings'],
		] );
	} catch ( \Throwable $error ) {
		echo json_encode( [ 'error' => $error->getMessage() ] );
	}
}
