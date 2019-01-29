<?php

namespace Automattic\VIP\Files;

use WP_Error;

class API_Cache_Test extends \WP_UnitTestCase {
	/**
	 * @var API_Cache
	 */
	public $cache;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/class-vip-filesystem-api-cache.php' );
	}

	public function setUp() {
		parent::setUp();

		$this->cache = API_Cache::get_instance();
	}

	public function tearDown() {
		$this->cache->clear_tmp_files();

		parent::tearDown();
	}

	public static function get_property( $object, $name ) {
		$property = new \ReflectionProperty( get_class( $object ), $name );
		$property->setAccessible( true );
		return $property;
	}

	public function test__get_instance() {
		$instance_b = API_Cache::get_instance();

		$this->assertSame( $this->cache, $instance_b );
	}

	public function test__clear_tmp_files() {
		$file1 = tempnam( sys_get_temp_dir(), 'test' );
		$file2 = tempnam( sys_get_temp_dir(), 'test' );

		$prop = self::get_property( $this->cache, 'files' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $file1, 'test2.jpg' => $file2 ] );

		$this->cache->clear_tmp_files();

		$this->assertEmpty( $prop->getValue( $this->cache ) );
	}

	public function test__get_file() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );
		$expected = 'test data';

		file_put_contents( $test_file, $expected );

		$prop = self::get_property( $this->cache, 'files' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $test_file ] );

		$actual = $this->cache->get_file( 'test.jpg' );

		$this->assertEquals( $expected, $actual );
	}

	public function test__get_file__invalid_file() {
		$result = $this->cache->get_file( 'test.jpg' );

		$this->assertFalse( $result );
	}

	public function test__cache_file() {
		$prop = self::get_property( $this->cache, 'files' );

		$this->cache->cache_file( 'test.txt', 'test data' );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files[ 'test.txt' ] ) );
	}

	public function test__cache_file__update_cache() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file, 'test data' );

		$prop = self::get_property( $this->cache, 'files' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $test_file ] );

		$expected = 'updated data';

		$this->cache->cache_file( 'test.jpg', $expected );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files[ 'test.jpg' ] ) );
		$this->assertEquals( $expected, file_get_contents( $files[ 'test.jpg' ] ) );
	}
}
