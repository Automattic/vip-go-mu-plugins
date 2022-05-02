<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-config.php';
require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-db-server.php';
require_once __DIR__ . '/../../../lib/helpers/wp-cli-db/class-wp-cli-db.php';

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;
use ArgumentCountError;
use TypeError;

class WP_Cli_Db_Test extends TestCase {
	//use ExpectPHPException;

	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();
	}

	public function test_config_not_enabled_writes_disallowed_by_default() {
		$config = new Config();
		$this->assertFalse( $config->enabled() );
		$this->assertFalse( $config->allow_writes() );
	}

	public function test_config_not_enabled_writes_disallowed_non_1_values() {
		Constant_Mocker::define( 'VIP_ENV_VAR_WP_DB_ENABLED', '0' );
		Constant_Mocker::define( 'VIP_ENV_VAR_WP_DB_ALLOW_WRITES', '0' );
		$config = new Config();
		$this->assertFalse( $config->enabled() );
		$this->assertFalse( $config->allow_writes() );
	}

	public function test_config_enabled_writes_disallowed() {
		Constant_Mocker::define( 'VIP_ENV_VAR_WP_DB_ENABLED', '1' );
		Constant_Mocker::define( 'VIP_ENV_VAR_WP_DB_ALLOW_WRITES', '0' );
		$config = new Config();
		$this->assertTrue( $config->enabled() );
		$this->assertFalse( $config->allow_writes() );
	}

	public function test_config_enabled_writes_allowed() {
		Constant_Mocker::define( 'VIP_ENV_VAR_WP_DB_ENABLED', '1' );
		Constant_Mocker::define( 'VIP_ENV_VAR_WP_DB_ALLOW_WRITES', '1' );
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
		$server = new DB_Server( 'host0', 'user123', 'hunter2', 'treasure_trove', 0, 0 );
		$this->assertFalse( $server->can_read() );
		$this->assertFalse( $server->can_write() );
	}

	public function test_db_server_can_read_not_write() {
		$server = new DB_Server( 'host0', 'user123', 'hunter2', 'treasure_trove', 1, 0 );
		$this->assertTrue( $server->can_read() );
		$this->assertFalse( $server->can_write() );
	}

	public function test_db_server_can_write_not_read() {
		$server = new DB_Server( 'host0', 'user123', 'hunter2', 'treasure_trove', 0, 1 );
		$this->assertFalse( $server->can_read() );
		$this->assertTrue( $server->can_write() );
	}

	public function test_db_server_can_read_and_write() {
		$server = new DB_Server( 'host0', 'user123', 'hunter2', 'treasure_trove', 1, 1 );
		$this->assertTrue( $server->can_read() );
		$this->assertTrue( $server->can_write() );
	}
}
