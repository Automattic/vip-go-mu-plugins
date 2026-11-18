<?php

namespace Automattic\VIP\Core\Constants;

require_once __DIR__ . '/../../001-core/constants.php';

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;
use wpdb;

class DB_Helpers_Test extends WP_UnitTestCase {
	/**
	 * Build a HyperDB-like object without running the wpdb constructor.
	 *
	 * @param mixed $hyper_servers
	 */
	private static function hyperdb( $hyper_servers ): wpdb {
		return new class( $hyper_servers ) extends wpdb {
			public $hyper_servers;

			public function __construct( $hyper_servers ) {
				// Do not call parent constructor
				$this->hyper_servers = $hyper_servers;
			}
		};
	}

	private static function hyper_servers( int $priority ): array {
		return [
			'global' => [
				'write' => [
					$priority => [
						[
							'host'     => 'host',
							'user'     => 'user',
							'password' => 'pass',
							'name'     => 'db',
							'write'    => $priority,
						],
					],
				],
			],
		];
	}

	public function data_define_db_constants__not_defined(): array {
		// [ DB_* constant already defined, whether the database object is HyperDB-like, its hyper_servers ]
		// The object is built in the test: declaring an anonymous class while PHPUnit collects
		// data sets makes it register this test class twice.
		return [
			'DB constant already defined' => [ 'DB_HOST', true, self::hyper_servers( 1 ) ],
			'not HyperDB'                 => [ null, false, null ],
			'servers not an array'        => [ null, true, false ],
			'no global dataset'           => [ null, true, [ null ] ],
		];
	}

	/**
	 * @dataProvider data_define_db_constants__not_defined
	 *
	 * @param mixed $hyper_servers
	 */
	public function test_define_db_constants__not_defined( ?string $defined_constant, bool $is_hyperdb, $hyper_servers ): void {
		if ( null !== $defined_constant ) {
			Constant_Mocker::define( $defined_constant, 'localhost' );
		}

		$db = $is_hyperdb ? self::hyperdb( $hyper_servers ) : false;
		define_db_constants( $db );

		self::assertFalse( Constant_Mocker::defined( 'DB_NAME' ) );
	}

	/**
	 * @dataProvider data_define_db_constants__inputs
	 */
	public function test_define_db_constants__inputs( int $priority ): void {
		define_db_constants( self::hyperdb( self::hyper_servers( $priority ) ) );

		self::assertTrue( Constant_Mocker::defined( 'DB_NAME' ) );
		self::assertTrue( Constant_Mocker::defined( 'DB_USER' ) );
		self::assertTrue( Constant_Mocker::defined( 'DB_PASSWORD' ) );
		self::assertTrue( Constant_Mocker::defined( 'DB_HOST' ) );

		self::assertSame( 'db', Constant_Mocker::constant( 'DB_NAME' ) );
		self::assertSame( 'user', Constant_Mocker::constant( 'DB_USER' ) );
		self::assertSame( 'pass', Constant_Mocker::constant( 'DB_PASSWORD' ) );
		self::assertSame( 'host', Constant_Mocker::constant( 'DB_HOST' ) );
	}

	public function data_define_db_constants__inputs(): iterable {
		return [
			'default write priority' => [ 1 ],
			'other write priority'   => [ 10 ],
		];
	}
}
