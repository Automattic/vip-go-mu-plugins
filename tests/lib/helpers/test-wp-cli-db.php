<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-config.php';
require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-db-server.php';
require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-wp-cli-db.php';

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;
use Exception;
use TypeError;

const SERVERS = [
	'no_access'             => [ 'n-0-0', 'user123', 'hunter2', 'treasure_trove', 0, 0 ],
	'r'                     => [ 'r-1-0', 'user123', 'hunter2', 'treasure_trove', 1, 0 ],
	'r_high_priority'       => [ 'r-99-0', 'user123', 'hunter2', 'treasure_trove', 99, 0 ],
	'rw'                    => [ 'rw-1-1', 'user123', 'hunter2', 'treasure_trove', 1, 1 ],
	'w'                     => [ 'w-0-1', 'user123', 'hunter2', 'treasure_trove', 0, 1 ],
	'rw_high_both_priority' => [ 'rw-99-99', 'user123', 'hunter2', 'treasure_trove', 99, 99 ],
	'rw_high_r_priority'    => [ 'rw-99-1', 'user123', 'hunter2', 'treasure_trove', 99, 1 ],
	'rw_high_w_priority'    => [ 'rw-1-99', 'user123', 'hunter2', 'treasure_trove', 1, 99 ],
	'w_high_priority'       => [ 'w-0-99', 'user123', 'hunter2', 'treasure_trove', 0, 99 ],
];

class WP_Cli_Db_Test extends TestCase {
	private $db_server_backup;

	public function setUp(): void {
		parent::setUp();
		$this->db_server_backup = $GLOBALS['db_servers'] ?? null;
	}

	public function tearDown(): void {
		$GLOBALS['db_servers'] = $this->db_server_backup;
		parent::tearDown();
	}

	private static function define_constants( array $constants ): void {
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}
	}

	public function test_before_run_command_returns_early_for_non_db_subcommand() {
		$config_mock = $this->createMock( Config::class );
		$config_mock
			->expects( $this->never() )
			->method( 'enabled' );
		$config_mock
			->expects( $this->never() )
			->method( 'get_database_server' );

		$wp_cli_db_mock = $this->getMockBuilder( Wp_Cli_Db::class )
			->setConstructorArgs( [ $config_mock ] )
			->onlyMethods( [ 'validate_subcommand' ] )
			->getMock();
		$wp_cli_db_mock
			->expects( $this->never() )
			->method( 'validate_subcommand' );

		$wp_cli_db_mock->before_run_command( [ 'notdb', 'something', '--something="else"' ] );
	}

	public function test_before_run_command_uses_real_entry_to_select_database_server() {
		$GLOBALS['db_servers'] = [ SERVERS['rw'] ];
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		$config  = new Config();
		$command = new Wp_Cli_Db( $config );

		$command->before_run_command( [ 'db', 'query', 'SELECT 1' ] );

		$this->assertSame( SERVERS['rw'][0], Constant_Mocker::constant( 'DB_HOST' ) );
		$this->assertSame( SERVERS['rw'][3], Constant_Mocker::constant( 'DB_NAME' ) );
	}

	public function get_test_data__get_database_server_errors() {
		return [
			'db not enabled'                => [ [], [ SERVERS['r'] ], Exception::class, 'The db command is not currently supported in this environment.' ],
			'db not enabled by non-1 value' => [ [ 'WPVIP_ENABLE_WP_DB' => 'gibberish' ], [ SERVERS['r'] ], Exception::class, 'The db command is not currently supported in this environment.' ],
			'servers unset'                 => [ [ 'WPVIP_ENABLE_WP_DB' => 1 ], null, Exception::class, 'The database configuration is missing.' ],
			'servers empty'                 => [ [ 'WPVIP_ENABLE_WP_DB' => 1 ], [], Exception::class, 'The database configuration is empty.' ],
			'server not an array'           => [ [ 'WPVIP_ENABLE_WP_DB' => 1 ], [ 'not an array' ], TypeError::class, null ],
			'server with invalid params'    => [ [ 'WPVIP_ENABLE_WP_DB' => 1 ], [ [ 'a', 'b', 'c', 'd', 'e', 'f' ] ], TypeError::class, null ],
		];
	}

	/**
	 * @dataProvider get_test_data__get_database_server_errors
	 */
	public function test_get_database_server_errors( array $constants, ?array $db_servers, string $exception, ?string $message ) {
		self::define_constants( $constants );
		if ( null === $db_servers ) {
			unset( $GLOBALS['db_servers'] );
		} else {
			$GLOBALS['db_servers'] = $db_servers;
		}

		$this->expectException( $exception );
		if ( null !== $message ) {
			$this->expectExceptionMessage( $message );
		}

		( new Config() )->get_database_server();
	}

	public function test_config_can_write() {
		$GLOBALS['db_servers'] = [
			SERVERS['r'],
			SERVERS['rw'],
		];
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB_WRITES', 1 );

		$config = new Config();
		$result = $config->allow_writes();
		$this->assertTrue( $result );

		$server = $config->get_database_server();
		$server->define_variables();

		$this->assertEquals( SERVERS['rw'][0], Constant_Mocker::constant( 'DB_HOST' ) );
		$this->assertEquals( SERVERS['rw'][1], Constant_Mocker::constant( 'DB_USER' ) );
		$this->assertEquals( SERVERS['rw'][2], Constant_Mocker::constant( 'DB_PASSWORD' ) );
		$this->assertEquals( SERVERS['rw'][3], Constant_Mocker::constant( 'DB_NAME' ) );
	}

	public function get_test_data__config_flags() {
		return [
			'not enabled, writes disallowed by default' => [ [], false, false ],
			'not enabled, writes disallowed by non-1 values' => [
				[
					'WPVIP_ENABLE_WP_DB'        => 0,
					'WPVIP_ENABLE_WP_DB_WRITES' => 0,
				],
				false,
				false,
			],
			'enabled, writes disallowed'                => [
				[
					'WPVIP_ENABLE_WP_DB'        => 1,
					'WPVIP_ENABLE_WP_DB_WRITES' => 0,
				],
				true,
				false,
			],
			'enabled, writes allowed'                   => [
				[
					'WPVIP_ENABLE_WP_DB'        => 1,
					'WPVIP_ENABLE_WP_DB_WRITES' => 1,
				],
				true,
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__config_flags
	 */
	public function test_config_flags( array $constants, bool $enabled, bool $allow_writes ) {
		self::define_constants( $constants );

		$config = new Config();
		$this->assertSame( $enabled, $config->enabled() );
		$this->assertSame( $allow_writes, $config->allow_writes() );
	}

	public function get_test_data__db_server_access() {
		return [
			'no access'      => [ 'no_access', false, false ],
			'read only'      => [ 'r', true, false ],
			'write only'     => [ 'w', false, true ],
			'read and write' => [ 'rw', true, true ],
		];
	}

	/**
	 * @dataProvider get_test_data__db_server_access
	 */
	public function test_db_server_access( string $server, bool $can_read, bool $can_write ) {
		$server = new DB_Server( ...SERVERS[ $server ] );
		$this->assertSame( $can_read, $server->can_read() );
		$this->assertSame( $can_write, $server->can_write() );
	}

	public function get_test_data__get_database_server() {
		return [
			'single read replica'                  => [ false, [ 'r' ], 'r' ],
			'highest read priority'                => [ false, [ 'r_high_priority', 'r' ], 'r_high_priority' ],
			'read replica when writes not allowed' => [ false, [ 'rw_high_both_priority', 'r', 'rw' ], 'r' ],
			'highest write priority'               => [ true, [ 'rw_high_both_priority', 'r', 'rw' ], 'rw_high_both_priority' ],
		];
	}

	/**
	 * @dataProvider get_test_data__get_database_server
	 */
	public function test_get_database_server( bool $allow_writes, array $servers, string $expected_server ) {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		if ( $allow_writes ) {
			Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB_WRITES', 1 );
		}
		$GLOBALS['db_servers'] = array_map( fn( $server ) => SERVERS[ $server ], $servers );

		$server = ( new Config() )->get_database_server();

		$this->assertEquals( new DB_Server( ...SERVERS[ $expected_server ] ), $server );
	}

	public function get_test_data__blocked_subcommands() {
		return [
			'drop'                => [ [ 'db', 'drop', 'really_important_table' ], 'The `wp db drop` subcommand is not permitted for this site.' ],
			'cli alone'           => [ [ 'db', 'cli' ], 'The `wp db cli` subcommand is not permitted for this site.' ],
			'cli with extra args' => [ [ 'db', 'cli', 'whatever' ], 'The `wp db cli` subcommand is not permitted for this site.' ],
			'query alone'         => [ [ 'db', 'query' ], 'Please provide the database query as a part of the command.' ],
			'drop query'          => [ [ 'db', 'query', 'DROP TABLE wp_example' ], 'This query is disallowed.' ],
			'create query'        => [ [ 'db', 'query', 'CREATE TABLE wp_example (id INT)' ], 'This query is disallowed.' ],
			'truncate query'      => [ [ 'db', 'query', 'TrUnCaTe TABLE wp_example' ], 'This query is disallowed.' ],
		];
	}

	/**
	 * @dataProvider get_test_data__blocked_subcommands
	 */
	public function test_validate_subcommand_blocked( array $command, string $message ) {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( $message );

		( new Wp_Cli_Db( new Config() ) )->validate_subcommand( $command );
	}

	public function get_test_data__allowed_subcommands() {
		return [
			'read query'             => [ [ 'db', 'query', 'SELECT * FROM crypto_wallet_keys' ] ],
			'read query with flags'  => [ [ 'db', 'query', 'SELECT 1', '--skip-column-names' ] ],
			'query with extra input' => [ [ 'db', 'query', 'whatever' ] ],
		];
	}

	/**
	 * @dataProvider get_test_data__allowed_subcommands
	 */
	public function test_validate_subcommand_allowed( array $command ) {
		$this->assertNull( ( new Wp_Cli_Db( new Config() ) )->validate_subcommand( $command ) );
	}

	public function get_test_data__validate_query() {
		return [
			'drop'   => [ 'DROP TABLE table', false ],
			'create' => [ 'CREATE TABLE wp_table', false ],
			'select' => [ 'SELECT * FROM wp_options WHERE option_name="home"', true ],
		];
	}

	/**
	 * @dataProvider get_test_data__validate_query
	 */
	public function test_validate_query( string $query, bool $expected ) {
		$this->assertSame( $expected, ( new Wp_Cli_Db( new Config() ) )->validate_query( $query ) );
	}

	public function test_allow_writes() {
		$config = new Config();

		$config->set_allow_writes( false );
		$this->assertFalse( $config->allow_writes() );

		$config->set_allow_writes( true );
		$this->assertTrue( $config->allow_writes() );
	}
}
