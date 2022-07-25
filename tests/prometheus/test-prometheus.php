<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\RegistryInterface;
use WP_UnitTestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/class-plugin-helper.php';

class Test_Prometheus extends WP_UnitTestCase {
	use ExpectPHPException;

	public function test_collectors_filter(): void {
		$plugin = Plugin_Helper::get_instance();
		self::assertInstanceOf( Plugin_Helper::class, $plugin );

		$filter_invoked = false;

		$available_collectors_filter = function ( $collectors ) use ( &$filter_invoked ) {
			$filter_invoked = true;
			WP_UnitTestCase::assertIsArray( $collectors );
			return $collectors;
		};

		add_filter( 'vip_prometheus_collectors', $available_collectors_filter );
		$plugin->plugins_loaded();

		self::assertTrue( $filter_invoked );
		self::assertIsArray( $plugin->get_collectors() );
	}

	public function test_collectors_filter_wrong_return_type(): void {
		$plugin = Plugin_Helper::get_instance();

		$filter_invoked = false;

		$available_collectors_filter = function ( $collectors ) use ( &$filter_invoked ) {
			$filter_invoked = true;
			WP_UnitTestCase::assertIsArray( $collectors );
			return null;
		};

		add_filter( 'vip_prometheus_collectors', $available_collectors_filter, PHP_INT_MAX );
		$plugin->plugins_loaded();

		self::assertTrue( $filter_invoked );
		self::assertIsArray( $plugin->get_collectors() );
		self::assertEmpty( $plugin->get_collectors() );
	}

	public function test_collectors_filter_wrong_collector_type(): void {
		$plugin = Plugin_Helper::get_instance();

		$filter_invoked = false;

		$available_collectors_filter = function ( $collectors ) use ( &$filter_invoked ) {
			$filter_invoked = true;
			WP_UnitTestCase::assertIsArray( $collectors );
			return [ new \stdClass() ];
		};

		add_filter( 'vip_prometheus_collectors', $available_collectors_filter, PHP_INT_MAX );
		$plugin->plugins_loaded();

		self::assertTrue( $filter_invoked );
		self::assertEmpty( $plugin->get_collectors() );
	}

	public function test_create_registry(): void {
		$plugin = Plugin_Helper::get_instance();
		$plugin->plugins_loaded();

		self::assertInstanceOf( RegistryInterface::class, $plugin->get_registry() );
	}

	public function test_create_registry_wrong_backend(): void {
		$plugin = Plugin_Helper::get_instance();

		add_filter( 'vip_prometheus_storage_backend', function () {
			return new \stdClass();
		} );

		$this->expectWarning();
		$plugin->plugins_loaded();
	}
}
