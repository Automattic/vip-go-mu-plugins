<?php

namespace Automattic\VIP\Logstash;

class Logger_Test extends \WP_UnitTestCase {
	private $errors;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../../logstash/class-logger.php';
	}

	public function setUp() {
		$this->errors = [];

		set_error_handler( [ $this, 'errorHandler' ] );

		// Reset Logger::$entries prop
		$entriesProp = $this->get_property( 'entries' );
		$entriesProp->setValue( [] );
	}

	public function errorHandler( $errno, $errstr, $errfile, $errline, $errcontext ) {
		$this->errors[] = compact( 'errno', 'errstr', 'errfile',
			'errline', 'errcontext' );
	}

	public function assertError($errstr, $errno) {
		foreach ( $this->errors as $error ) {
			if ( $error[ 'errstr' ] === $errstr
				&& $error[ 'errno' ] === $errno ) {
				return;
			}
		}

		$this->fail( "Error with level " . $errno .
			" and message '" . $errstr . "' not found in " .
			var_export( $this->errors, TRUE ) );
	}

	/**
	 * Helper function for accessing protected static properties.
	 */
	protected function get_property( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\Logger' );
		$prop = $class->getProperty( $name );
		$prop->setAccessible( true );
		return $prop;
	}

	public function test__log2logstash() {
		$data = [
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
		];

		$expected = array_merge( $data, [
			'feature' => 'a8c_vip_test',
			'site_id' => 1,
			'blog_id' => 1,
			'host' => 'example.org',
			'user_id' => 0,
			'user_ua' => '',
			'extra' => '[]',
		] );

		Logger::log2logstash( $data );

		$entriesProp = $this->get_property( 'entries' );

		$this->assertEquals( [ $expected ], $entriesProp->getValue() );
	}

	public function test__log2logstash__too_many_entries() {
		$entries = [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30 ];
		$data = [
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
		];

		$entriesProp = $this->get_property( 'entries' );
		$entriesProp->setValue( $entries );

		Logger::log2logstash( $data );

		$this->assertError( 'Excessive calls to Automattic\VIP\Logstash\Logger::log2logstash(). Maximum is 30 log entries.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( $entries, $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_site_id() {
		$data = [
			'site_id' => 'invalid',
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `site_id` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be an integer > 0.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_blog_id() {
		$data = [
			'blog_id' => 'invalid',
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `blog_id` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be an integer > 0.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_host() {
		$data = [
			'host' => 123,
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `host` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be a string.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_host_size() {
		$data = [
			'host' => 'BqnKKZj3EHPqnzg7HC9aHJRFfqMZiHPJbKjKZJBeCrqcQmFq2QN2202GOYwsuVzkmKnxycLXUhTS4vbIDsMcNfsPWB0vcz9TxjfbqiJ3Tt0akDmxf841w409Ghge2PnUJ1fA7PkeyQmQux3D36AiLz8VmglrIbiI4zhDG8iiJG09XuOyMWjthvnyWqQqSuQLx2vbdifauXfEXMcPanXk2T2quG94OfHzBkptLPnUKi8n7FMk8mSagR0OHrM1QPOso',
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `host` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be 255 bytes or less.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_severity() {
		$data = [
			'severity' => 'invalid',
			'feature' => 'test',
			'message' => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `severity` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be one of: ``, `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_feature() {
		$data = [
			'severity' => 'alert',
			'feature' => 123,
			'message' => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Missing required `feature` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be a string.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_feature_size() {
		$data = [
			'severity' => 'alert',
			'feature' => 'BqnKKZj3EHPqnzg7HC9aHJRFfqMZiHPJbKjKZJBeCrqcQmFq2QN2202GOYwsuVzkmKnxycLXUhTS4vbIDsMcNfsPWB0vcz9TxjfbqiJ3Tt0akDmxf841w409Ghge2PnUJ1fA7PkeyQmQux3D36AiLz8VmglrIbiI4zhDG8iiJG09XuOyMWjthvnyWqQqSuQLx2vbdifauXfEXMcPanXk2T2quG94OfHzBkptLPnUKi8n7FMk8mSagR0OHrM1QPOso',
			'message' => 'Test alert',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `feature` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be 200 bytes or less.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_message() {
		$data = [
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 123,
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Missing required `message` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be a string.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_user_id() {
		$data = [
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
			'user_id' => 'invalid',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `user_id` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be an integer >= 0.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_user_ua() {
		$data = [
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
			'user_ua' => 123,
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `user_ua` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be a string.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_user_ua_size() {
		$data = [
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
			'user_ua' => 'BqnKKZj3EHPqnzg7HC9aHJRFfqMZiHPJbKjKZJBeCrqcQmFq2QN2202GOYwsuVzkmKnxycLXUhTS4vbIDsMcNfsPWB0vcz9TxjfbqiJ3Tt0akDmxf841w409Ghge2PnUJ1fA7PkeyQmQux3D36AiLz8VmglrIbiI4zhDG8iiJG09XuOyMWjthvnyWqQqSuQLx2vbdifauXfEXMcPanXk2T2quG94OfHzBkptLPnUKi8n7FMk8mSagR0OHrM1QPOso',
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `user_ua` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be 255 bytes or less.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}

	public function test__log2logstash__invalid_extra() {
		$data = [
			'severity' => 'alert',
			'feature' => 'test',
			'message' => 'Test alert',
			'extra' => tmpfile(),
		];

		Logger::log2logstash( $data );

		$this->assertError( 'Invalid `extra` in call to Automattic\VIP\Logstash\Logger::log2logstash(). Must be an object, array, or scalar value.', E_USER_WARNING );

		$entriesProp = $this->get_property( 'entries' );

		// No new entries added
		$this->assertEquals( [], $entriesProp->getValue() );
	}
}
