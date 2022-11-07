<?php

namespace Automattic\VIP\Core\Constants;

require_once __DIR__ . '/../../001-core/constants.php';

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;
use wpdb;

class DB_Helpers_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test_define_db_constants__constant_defined(): void {
		global $wpdb;

		Constant_Mocker::define( 'DB_HOST', 'localhost' );
		define_db_constants( $wpdb );

		self::assertFalse( Constant_Mocker::defined( 'DB_NAME' ) );
	}

	public function test_define_db_constants__not_hyperdb(): void {
		$db = false;

		define_db_constants( $db );

		self::assertFalse( Constant_Mocker::defined( 'DB_NAME' ) );
	}

	public function test_define_db_constants__servers_not_array(): void {
		$db = new class() extends wpdb {
			public $hyper_servers;

			public function __construct() {
				$this->hyper_servers = false;
			}
		};

		define_db_constants( $db );

		self::assertFalse( Constant_Mocker::defined( 'DB_NAME' ) );
	}

	public function test_define_db_constants__db_not_array(): void {
		$db = new class() extends wpdb {
			public $hyper_servers;

			public function __construct() {
				// Do nothing, do not call parent constructor
				$this->hyper_servers = [ null ];
			}
		};

		define_db_constants( $db );

		self::assertFalse( Constant_Mocker::defined( 'DB_NAME' ) );
	}

	/**
	 * @dataProvider data_define_db_constants__inputs
	 */
	public function test_define_db_constants__inputs( int $priority ): void {
		$expected_user = 'user';
		$expected_pass = 'pass';
		$expected_host = 'host';
		$expected_db   = 'db';

		$db = new class( $priority, $expected_user, $expected_pass, $expected_host, $expected_db ) extends wpdb {
			public $hyper_servers;
			private int $priority;

			public function __construct( int $priority, string $user, string $pass, string $host, string $db ) {
				$db_details = [
					'host'     => $host,
					'user'     => $user,
					'password' => $pass,
					'name'     => $db,
					'write'    => $priority,
				];
				// Do not call parent constructor
				$this->hyper_servers = [
					'global' => [
						'write' => [
							$priority => [
								$db_details,
							],
						],
					],
				];
			}
		};

		define_db_constants( $db );

		self::assertTrue( Constant_Mocker::defined( 'DB_NAME' ) );
		self::assertTrue( Constant_Mocker::defined( 'DB_USER' ) );
		self::assertTrue( Constant_Mocker::defined( 'DB_PASSWORD' ) );
		self::assertTrue( Constant_Mocker::defined( 'DB_HOST' ) );

		self::assertSame( $expected_db, Constant_Mocker::constant( 'DB_NAME' ) );
		self::assertSame( $expected_user, Constant_Mocker::constant( 'DB_USER' ) );
		self::assertSame( $expected_pass, Constant_Mocker::constant( 'DB_PASSWORD' ) );
		self::assertSame( $expected_host, Constant_Mocker::constant( 'DB_HOST' ) );
	}

	public function data_define_db_constants__inputs(): iterable {
		return [
			[ 1 ],
			[ 10 ],
		];
	}
}
