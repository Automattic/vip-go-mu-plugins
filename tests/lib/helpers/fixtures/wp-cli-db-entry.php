<?php
/**
 * Isolated WP-CLI boundary for before_run_command(). No database connection is made.
 */

use Automattic\VIP\Helpers\WP_CLI_DB\Config;
use Automattic\VIP\Helpers\WP_CLI_DB\DB_Server;
use Automattic\VIP\Helpers\WP_CLI_DB\Wp_Cli_Db;

class WP_CLI {
	public static bool $accept = true;

	public static function confirm( $message ): void {
		$GLOBALS['events'][] = 'confirm';
		$GLOBALS['prompt']   = $message;
		if ( ! self::$accept ) {
			throw new RuntimeException( 'confirmation refused' );
		}
	}
}

require_once __DIR__ . '/../../../../lib/helpers/wp-cli-db/class-config.php';
require_once __DIR__ . '/../../../../lib/helpers/wp-cli-db/class-db-server.php';
require_once __DIR__ . '/../../../../lib/helpers/wp-cli-db/class-wp-cli-db.php';

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Isolated recorder and Config observer share one child process.
class Recording_Config extends Config {
	public function set_allow_writes( bool $allow ): void {
		$GLOBALS['events'][] = 'enable_writes';
		parent::set_allow_writes( $allow );
	}

	public function get_database_server(): DB_Server {
		$GLOBALS['events'][] = 'select_server';
		return parent::get_database_server();
	}
}

$input = json_decode( stream_get_contents( STDIN ), true );
define( 'WPVIP_ENABLE_WP_DB', 1 );
$GLOBALS['db_servers'] = [
	[ 'read-only', 'user', 'password', 'database', 1, 0 ],
	[ 'read-write', 'user', 'password', 'database', 1, 1 ],
];
$GLOBALS['events']     = [];
WP_CLI::$accept        = $input['accept'];
$config                = new Recording_Config();

try {
	( new Wp_Cli_Db( $config ) )->before_run_command( [ 'db', 'query', 'SELECT 1' ], $input['options'] );
	$refused = false;
} catch ( RuntimeException $error ) {
	$refused = 'confirmation refused' === $error->getMessage();
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress is deliberately absent in this fixture.
echo json_encode( [
	'events'       => $GLOBALS['events'],
	'prompt'       => $GLOBALS['prompt'] ?? null,
	'allow_writes' => $config->allow_writes(),
	'host'         => defined( 'DB_HOST' ) ? DB_HOST : null,
	'refused'      => $refused,
] );
