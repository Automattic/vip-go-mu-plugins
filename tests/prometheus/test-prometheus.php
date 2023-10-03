<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\RegistryInterface;
use Prometheus\RenderTextFormat;
use WP_UnitTestCase;

require_once __DIR__ . '/class-plugin-helper.php';

class Test_Prometheus extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		remove_all_actions( 'vip_mu_plugins_loaded' );
		remove_all_actions( 'mu_plugins_loaded' );
		remove_all_actions( 'plugins_loaded' );
		remove_all_actions( 'init' );

		Plugin_Helper::clear_instance();

		// As of PHPUnit 10.x, expectWarning() is removed. We'll use a custom error handler to test for warnings.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static function ( int $errno, string $errstr ): never {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
			throw new \Exception( $errstr, $errno );
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();

		Plugin::get_instance();
		parent::tearDown();
	}

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
		do_action( 'vip_mu_plugins_loaded' );

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
		do_action( 'vip_mu_plugins_loaded' );

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
		do_action( 'vip_mu_plugins_loaded' );

		self::assertTrue( $filter_invoked );
		self::assertEmpty( $plugin->get_collectors() );
	}

	public function test_create_registry(): void {
		$plugin = Plugin_Helper::get_instance();
		do_action( 'vip_mu_plugins_loaded' );

		self::assertInstanceOf( RegistryInterface::class, $plugin->get_registry() );
	}

	public function test_create_registry_wrong_backend(): void {
		Plugin_Helper::get_instance();

		add_filter( 'vip_prometheus_storage_backend', function () {
			return new \stdClass();
		} );

		$this->expectException( \Exception::class );
		do_action( 'vip_mu_plugins_loaded' );
	}

	public function test_merge_collectors(): void {
		$plugin = Plugin_Helper::get_instance();

		$collector1 = new class() implements CollectorInterface {
			public $initialize_called = 0;

			public function initialize( RegistryInterface $registry ): void {
				++$this->initialize_called;
			}

			public function collect_metrics(): void {
				// Do nothing
			}

			public function process_metrics(): void {
				// Do nothing
			}
		};

		$collector2 = clone $collector1;
		$collector3 = clone $collector1;

		add_filter( 'vip_prometheus_collectors', function ( array $collectors, string $hook ) use ( $collector1, $collector2, $collector3 ): array {
			switch ( $hook ) {
				case 'vip_mu_plugins_loaded':
					return [ $collector1 ];

				case 'mu_plugins_loaded':
					return [ $collector1, $collector2 ];

				case 'plugins_loaded':
					return [ $collector2, $collector3 ];

				default:
					WP_UnitTestCase::assertFalse( true );
					return $collectors;
			}
		}, 10, 2 );

		do_action( 'vip_mu_plugins_loaded' );
		self::assertEquals( 1, $collector1->initialize_called );
		self::assertEquals( 0, $collector2->initialize_called );
		self::assertEquals( 0, $collector3->initialize_called );

		do_action( 'mu_plugins_loaded' );
		self::assertEquals( 1, $collector1->initialize_called );
		self::assertEquals( 1, $collector2->initialize_called );
		self::assertEquals( 0, $collector3->initialize_called );

		do_action( 'plugins_loaded' );
		self::assertEquals( 1, $collector1->initialize_called );
		self::assertEquals( 1, $collector2->initialize_called );
		self::assertEquals( 1, $collector3->initialize_called );

		$expected = [ $collector1, $collector2, $collector3 ];
		self::assertEquals( $expected, $plugin->get_collectors() );
	}

	public function test_safe_adapter(): void {
		Plugin_Helper::get_instance();

		$collector = new class() implements CollectorInterface {
			private Gauge $gauge;
			private Counter $counter;
			public RegistryInterface $registry;

			public function initialize( RegistryInterface $registry ): void {
				$this->registry = $registry;
				$this->gauge    = $registry->getOrRegisterGauge( 'test', 'gauge', '', [ 'A', 'B' ] );
				$this->counter  = $registry->getOrRegisterCounter( 'test', 'counter', '', [ 'A', 'B' ] );
			}

			public function collect_metrics(): void {
				$this->gauge->set( 1, [ '0', 1 ] );
				$this->counter->inc( [ 0, 1 ] );
			}

			public function process_metrics(): void {
				// Do nothing
			}
		};

		add_filter( 'vip_prometheus_collectors', function ( array $collectors, string $hook ) use ( $collector ): array {
			if ( 'vip_mu_plugins_loaded' === $hook ) {
				return [ $collector ];
			}

			return $collectors;
		}, 10, 2 );

		do_action( 'vip_mu_plugins_loaded' );

		$collector->collect_metrics();
		$renderer = new RenderTextFormat();
		$actual   = $renderer->render( $collector->registry->getMetricFamilySamples() );

		self::assertStringContainsString( 'test_gauge{A="0",B="1"} 1', $actual );
		self::assertStringContainsString( 'test_counter{A="0",B="1"} 1', $actual );
	}
}
