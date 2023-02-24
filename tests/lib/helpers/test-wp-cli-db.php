<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-config.php';
require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-db-server.php';
require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-wp-cli-db.php';

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;
use Exception;
use ArgumentCountError;
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
		Constant_Mocker::clear();
		$this->db_server_backup = $GLOBALS['db_servers'] ?? null;
	}

	public function tearDown(): void {
		$GLOBALS['db_servers'] = $this->db_server_backup;
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
			->getMock();
		$wp_cli_db_mock
			->expects( $this->never() )
			->method( 'validate_subcommand' );

		$wp_cli_db_mock->before_run_command( [ 'notdb', 'something', '--something="else"' ] );
	}

	public function test_get_database_server_db_not_enabled() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The db command is not currently supported in this environment.' );

		( new Config() )->get_database_server();
	}

	public function test_get_database_server_db_not_enabled_non_1_const() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 'gibberish' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The db command is not currently supported in this environment.' );

		( new Config() )->get_database_server();
	}

	public function test_validate_subcommand_db_blocked_command_no_write() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The `wp db drop` subcommand is not permitted for this site.' );
		( new Wp_Cli_Db( new Config() ) )->validate_subcommand( [ 'db', 'drop', 'really_important_table' ] );
	}

	public function test_validate_subcommand_db_read_query() {
		$result = ( new Wp_Cli_Db( new Config() ) )->validate_subcommand( [ 'db', 'query', 'SELECT * FROM crypto_wallet_keys' ] );
		$this->assertEquals( null, $result );
	}

	public function test_config_no_write() {
		$GLOBALS['db_servers'] = [
			SERVERS['r'],
			SERVERS['rw'],
		];
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );

		$config = new Config();
		$result = $config->allow_writes();
		$this->assertFalse( $result );
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

	public function test_validate_subcommand_no_console_cli() {
		$GLOBALS['db_servers'] = [
			SERVERS['r'],
		];
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The `wp db cli` subcommand is not permitted for this site.' );
		( new Wp_Cli_Db( new Config() ) )->validate_subcommand( [ 'db', 'cli' ] );
	}

	public function test_validate_subcommand_no_console_query_and_no_querystring() {
		$GLOBALS['db_servers'] = [
			SERVERS['r'],
		];
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Please provide the database query as a part of the command' );
		( new Wp_Cli_Db( new Config() ) )->validate_subcommand( [ 'db', 'query' ] );
	}

	public function test_config_not_enabled_writes_disallowed_by_default() {
		$config = new Config();
		$this->assertFalse( $config->enabled() );
		$this->assertFalse( $config->allow_writes() );
	}

	public function test_config_not_enabled_writes_disallowed_non_1_values() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 0 );
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB_WRITES', 0 );
		$config = new Config();
		$this->assertFalse( $config->enabled() );
		$this->assertFalse( $config->allow_writes() );
	}

	public function test_config_enabled_writes_disallowed() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB_WRITES', 0 );
		$config = new Config();
		$this->assertTrue( $config->enabled() );
		$this->assertFalse( $config->allow_writes() );
	}

	public function test_config_enabled_writes_allowed() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB_WRITES', 1 );
		$config = new Config();
		$this->assertTrue( $config->enabled() );
		$this->assertTrue( $config->allow_writes() );
	}

	public function test_db_server_empty_args() {
		$this->expectException( ArgumentCountError::class );
		new DB_Server();
	}

	public function test_db_server_bad_args() {
		$this->expectException( TypeError::class );
		new DB_Server( 'a', 'b', 'c', 'd', 'e', 'f' );
	}

	public function test_db_server_cannot_read_or_write() {
		$server = new DB_Server( ...SERVERS['no_access'] );
		$this->assertFalse( $server->can_read() );
		$this->assertFalse( $server->can_write() );
	}

	public function test_db_server_can_read_not_write() {
		$server = new DB_Server( ...SERVERS['r'] );
		$this->assertTrue( $server->can_read() );
		$this->assertFalse( $server->can_write() );
	}

	public function test_db_server_can_write_not_read() {
		$server = new DB_Server( ...SERVERS['w'] );
		$this->assertFalse( $server->can_read() );
		$this->assertTrue( $server->can_write() );
	}

	public function test_db_server_can_read_and_write() {
		$server = new DB_Server( ...SERVERS['rw'] );
		$this->assertTrue( $server->can_read() );
		$this->assertTrue( $server->can_write() );
	}

	public function test_no_config() {
		$this->expectException( ArgumentCountError::class );
		new Wp_Cli_Db();
	}

	public function test_get_database_server_not_enabled_no_const() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The db command is not currently supported in this environment.' );
		( new Config() )->get_database_server();
	}

	public function test_get_database_server_not_enabled_non_1_const() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 'gibberish' );
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The db command is not currently supported in this environment.' );
		( new Config() )->get_database_server();
	}

	public function test_get_database_server_unset() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		unset( $GLOBALS['db_servers'] );
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The database configuration is missing.' );
		( new Config() )->get_database_server();
	}

	public function test_get_database_server_empty_array() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		$GLOBALS['db_servers'] = [];
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The database configuration is empty.' );
		( new Config() )->get_database_server();
	}

	public function test_get_database_server_array_invalid_type() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		$GLOBALS['db_servers'] = [
			'not an array',
		];
		$this->expectException( TypeError::class );
		( new Config() )->get_database_server();
	}

	public function test_get_database_server_array_params() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		$GLOBALS['db_servers'] = [
			[ 'a', 'b', 'c', 'd', 'e', 'f' ],
		];
		$this->expectException( TypeError::class );
		( new Config() )->get_database_server();
	}

	public function test_get_database_server_single_read() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		$GLOBALS['db_servers'] = [
			SERVERS['r'],
		];
		$server                = ( new Config() )->get_database_server();
		$this->assertTrue( $server->can_read() );
		$this->assertFalse( $server->can_write() );
	}

	public function test_get_database_server_prioritized_read() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		$GLOBALS['db_servers'] = [
			SERVERS['r_high_priority'],
			SERVERS['r'],
		];
		$server                = ( new Config() )->get_database_server();
		$this->assertTrue( $server->can_read() );
		$this->assertFalse( $server->can_write() );
		$this->assertEquals( 99, $server->read_priority() );
	}

	public function test_get_database_server_read_replica_when_write_not_allowed() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		$GLOBALS['db_servers'] = [
			SERVERS['rw_high_both_priority'],
			SERVERS['r'],
			SERVERS['rw'],
		];
		$server                = ( new Config() )->get_database_server();
		$this->assertTrue( $server->can_read() );
		$this->assertFalse( $server->can_write() );
	}

	public function test_get_database_server_prioritized_rw() {
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB', 1 );
		Constant_Mocker::define( 'WPVIP_ENABLE_WP_DB_WRITES', 1 );
		$GLOBALS['db_servers'] = [
			SERVERS['rw_high_both_priority'],
			SERVERS['r'],
			SERVERS['rw'],
		];
		$server                = ( new Config() )->get_database_server();
		$this->assertTrue( $server->can_read() );
		$this->assertTrue( $server->can_write() );
		$this->assertEquals( 99, $server->read_priority() );
		$this->assertEquals( 99, $server->write_priority() );
	}

	public function test_console_is_blocked_for_cli_alone() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The `wp db cli` subcommand is not permitted for this site.' );
		( new Wp_Cli_Db( new Config() ) )->validate_subcommand( [ 'db', 'cli' ] );
	}

	public function test_console_is_blocked_for_cli_with_extra_commands() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'The `wp db cli` subcommand is not permitted for this site.' );
		( new Wp_Cli_Db( new Config() ) )->validate_subcommand( [ 'db', 'cli', 'whatever' ] );
	}

	public function test_console_is_blocked_for_query_alone() {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Please provide the database query as a part of the command.' );
		( new Wp_Cli_Db( new Config() ) )->validate_subcommand( [ 'db', 'query' ] );
	}

	public function test_console_is_allowed_for_query_with_extra_commands() {
		try {
			( new Wp_Cli_Db( new Config() ) )->validate_subcommand( [ 'db', 'query', 'whatever' ] );
			$this->addToAssertionCount( 1 );
		} catch ( Exception $e ) {
			$this->fail( '`wp db query whatever` should not have thrown' );
		}
	}

	public function test_validate_query_drop() {
		$result = ( new Wp_Cli_Db( new Config() ) )->validate_query( 'DROP TABLE table' );
		$this->assertFalse( $result );
	}

	public function test_validate_query_create() {
		$result = ( new Wp_Cli_Db( new Config() ) )->validate_query( 'CREATE TABLE wp_table' );
		$this->assertFalse( $result );
	}

	public function test_validate_query_select() {
		$result = ( new Wp_Cli_Db( new Config() ) )->validate_query( 'SELECT * FROM wp_options WHERE option_name="home"' );
		$this->assertTrue( $result );
	}
}
