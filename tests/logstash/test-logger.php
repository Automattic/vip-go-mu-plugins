<?php

namespace Automattic\VIP\Logstash;

use WP_UnitTestCase;

require_once __DIR__ . '/../../logstash/class-logger.php';
require_once __DIR__ . '/class-testable-logger.php';

class Logger_Test extends WP_UnitTestCase {
	private $errors;

	public function setUp(): void {
		parent::setUp();

		$this->errors = [];

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		set_error_handler( [ $this, 'errorHandler' ] );

		Testable_Logger::set_entries( [] );
	}

	public function tearDown(): void {
		restore_error_handler();

		parent::tearDown();
	}

	public function errorHandler( $errno, $errstr, $errfile, $errline ) {
		$this->errors[] = compact( 'errno', 'errstr', 'errfile', 'errline' );
	}

	public function assertError( $errstr, $errno ) {
		foreach ( $this->errors as $error ) {
			if ( $error['errstr'] === $errstr
				&& $error['errno'] === $errno ) {
				return;
			}
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$this->fail( 'Error with level ' . $errno . " and message '" . $errstr . "' not found in " . var_export( $this->errors, true ) );
	}

	public function test__log2logstash() {
		$data = [
			'severity'  => 'alert',
			'feature'   => 'test',
			'message'   => 'Test alert',
			'timestamp' => '2020-03-26 05:29:05',
			'file'      => '/app/logstash/class-logger.php',
			'line'      => '402',
		];

		$expected = array_merge( $data, [
			'feature'         => 'a8c_vip_test',
			'site_id'         => 1,
			'blog_id'         => 1,
			'http_host'       => 'example.org',
			'http_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '', // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'user_id'         => 0,
			'extra'           => '[]',
			'index'           => 'log2logstash',
		] );

		Logger::log2logstash( $data );

		$this->assertEquals( [ $expected ], Testable_Logger::get_entries() );
	}

	public function test__log2logstash__too_many_entries() {
		$entries = range( 0, 101 );
		$data    = [
			'severity' => 'alert',
			'feature'  => 'test',
			'message'  => 'Test alert',
		];

		Testable_Logger::set_entries( $entries );

		Logger::log2logstash( $data );

		$this->assertError( 'Excessive calls to Automattic\VIP\Logstash\Logger::log2logstash(). Maximum is 100 log entries.', E_USER_WARNING );

		// No new entries added
		$this->assertEquals( $entries, Testable_Logger::get_entries() );
	}

	public function test__log2logstash__invalid_site_id() {
		$data = [
			'site_id'  => 'invalid',
			'severity' => 'alert',
			'feature'  => 'test',
			'message'  => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `site_id` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be an integer > 0.', E_USER_WARNING );

		// No new entries added
		$this->assertEquals( [], Testable_Logger::get_entries() );
	}

	public function test__log2logstash__invalid_blog_id() {
		$data = [
			'blog_id'  => 'invalid',
			'severity' => 'alert',
			'feature'  => 'test',
			'message'  => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `blog_id` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be an integer > 0.', E_USER_WARNING );

		// No new entries added
		$this->assertEquals( [], Testable_Logger::get_entries() );
	}

	public function test__log2logstash__invalid_http_host_size() {
		$data = [
			'http_host' => 'BqnKKZj3EHPqnzg7HC9aHJRFfqMZiHPJbKjKZJBeCrqcQmFq2QN2202GOYwsuVzkmKnxycLXUhTS4vbIDsMcNfsPWB0vcz9TxjfbqiJ3Tt0akDmxf841w409Ghge2PnUJ1fA7PkeyQmQux3D36AiLz8VmglrIbiI4zhDG8iiJG09XuOyMWjthvnyWqQqSuQLx2vbdifauXfEXMcPanXk2T2quG94OfHzBkptLPnUKi8n7FMk8mSagR0OHrM1QPOso',
			'severity'  => 'alert',
			'feature'   => 'test',
			'message'   => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `http_host` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be 255 bytes or less.', E_USER_WARNING );

		// No new entries added
		$this->assertEquals( [], Testable_Logger::get_entries() );
	}

	public function test__log2logstash__invalid_severity() {
		$data = [
			'severity' => 'invalid',
			'feature'  => 'test',
			'message'  => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `severity` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be one of: ``, `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.', E_USER_WARNING );

		// No new entries added
		$this->assertEquals( [], Testable_Logger::get_entries() );
	}

	public function test__log2logstash__invalid_feature_size() {
		$data = [
			'severity' => 'alert',
			'feature'  => 'BqnKKZj3EHPqnzg7HC9aHJRFfqMZiHPJbKjKZJBeCrqcQmFq2QN2202GOYwsuVzkmKnxycLXUhTS4vbIDsMcNfsPWB0vcz9TxjfbqiJ3Tt0akDmxf841w409Ghge2PnUJ1fA7PkeyQmQux3D36AiLz8VmglrIbiI4zhDG8iiJG09XuOyMWjthvnyWqQqSuQLx2vbdifauXfEXMcPanXk2T2quG94OfHzBkptLPnUKi8n7FMk8mSagR0OHrM1QPOso',
			'message'  => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `feature` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be 200 bytes or less.', E_USER_WARNING );

		// No new entries added
		$this->assertEquals( [], Testable_Logger::get_entries() );
	}

	public function test__log2logstash__invalid_extra() {
		$data = [
			'severity' => 'alert',
			'feature'  => 'test',
			'message'  => 'Test alert',
			'extra'    => tmpfile(),
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `extra` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be an object, array, or scalar value.', E_USER_WARNING );

		// No new entries added
		$this->assertEquals( [], Testable_Logger::get_entries() );
	}

	public function test__process_entries_on_shutdown() {
		$entries = [
			[
				'feature'   => 'a8c_vip_test',
				'site_id'   => 1,
				'blog_id'   => 1,
				'http_host' => 'example.org',
				'user_id'   => 0,
				'extra'     => '[]',
				'index'     => 'log2logstash',
				'severity'  => 'alert',
				'feature'   => 'test',
				'message'   => 'Test alert',
				'timestamp' => '2020-03-26 05:29:05',
				'file'      => '/app/logstash/class-logger.php',
				'line'      => '402',
			],
		];

		Testable_Logger::set_entries( $entries );
		Testable_Logger::process_entries_on_shutdown();

		self::assertEquals( $entries, Testable_Logger::$logged_entries );
	}
}
