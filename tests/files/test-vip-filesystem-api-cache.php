<?php

namespace Automattic\VIP\Files;

use WP_UnitTestCase;

require_once __DIR__ . '/../../files/class-api-cache.php';

// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

class API_Cache_Test extends WP_UnitTestCase {
	/**
	 * @var API_Cache
	 */
	public $cache;

	public function setUp(): void {
		parent::setUp();

		$this->cache = API_Cache::get_instance();
	}

	public function tearDown(): void {
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
		$file1 = tempnam( sys_get_temp_dir(), 'test' );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam
		$file2 = tempnam( sys_get_temp_dir(), 'test' );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam

		$files_prop = self::get_property( $this->cache, 'files' );
		$files_prop->setValue( $this->cache, [
			'test.jpg'  => $file1,
			'test2.jpg' => $file2,
		] );

		$stats_prop = self::get_property( $this->cache, 'file_stats' );
		$stats_prop->setValue( $this->cache, [
			'test.jpg'  => [
				'size'  => '81',
				'mtime' => '123456779',
			],
			'test2.jpg' => [
				'size'  => '235',
				'mtime' => '123456779',
			],
		] );

		$this->cache->clear_tmp_files();

		$this->assertEmpty( $files_prop->getValue( $this->cache ) );
		$this->assertFalse( file_exists( $file1 ) );
		$this->assertFalse( file_exists( $file2 ) );
		$this->assertEmpty( $stats_prop->getValue( $this->cache ) );
	}

	public function test__get_file() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );
		$expected  = 'test data';

		file_put_contents( $test_file, $expected );

		$prop = self::get_property( $this->cache, 'files' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $test_file ] );

		$actual = $this->cache->get_file( 'test.jpg' );

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$this->assertEquals( $expected, file_get_contents( $actual ) );
	}

	public function test__get_file__invalid_file() {
		$result = $this->cache->get_file( 'test.jpg' );

		$this->assertFalse( $result );
	}

	public function test__get_file__invalid__same_file_different_path() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );
		$expected  = 'test data';

		file_put_contents( $test_file, $expected );

		$prop = self::get_property( $this->cache, 'files' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $test_file ] );

		$result = $this->cache->get_file( '/tmp/test.jpg' );

		$this->assertFalse( $result );
	}

	public function test__get_file_stats() {
		$expected = [
			'size'  => '123',
			'mtime' => '123456779',
		];

		$prop = self::get_property( $this->cache, 'file_stats' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $expected ] );

		$actual = $this->cache->get_file_stats( 'test.jpg' );

		$this->assertEquals( $expected, $actual );
	}

	public function test__get_file_stats__invalid_file() {
		$result = $this->cache->get_file_stats( 'test.jpg' );

		$this->assertFalse( $result );
	}

	public function test__get_file_stats__invalid__same_file_different_path() {
		$expected = [
			'size'  => '123',
			'mtime' => '123456779',
		];

		$prop = self::get_property( $this->cache, 'file_stats' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $expected ] );

		$result = $this->cache->get_file_stats( '/tmp/test.jpg' );

		$this->assertFalse( $result );
	}

	public function test__cache_file() {
		$prop = self::get_property( $this->cache, 'files' );

		$file = tempnam( sys_get_temp_dir(), 'test' );

		$this->cache->cache_file( '/test/path/test.txt', $file );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files['/test/path/test.txt'] ) );
	}

	public function test__cache_file__update_cache() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file, 'test data' );

		$prop = self::get_property( $this->cache, 'files' );
		$prop->setValue( $this->cache, [ '/test/path/test.jpg' => $test_file ] );

		$expected = 'updated data';

		$updated_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $updated_file, $expected );

		$this->cache->cache_file( '/test/path/test.jpg', $updated_file );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files['/test/path/test.jpg'] ) );
		$this->assertEquals( $expected, file_get_contents( $files['/test/path/test.jpg'] ) );
	}

	public function test__cache_file_stats() {
		$prop     = self::get_property( $this->cache, 'file_stats' );
		$expected = [
			'size'  => '123',
			'mtime' => '123456779',
		];

		$this->cache->cache_file_stats( '/test/path/test.txt', $expected );

		$stats = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $stats['/test/path/test.txt'] ) );
		$this->assertEquals( $expected, $stats['/test/path/test.txt'] );
	}

	public function test__cache_file_stats__update_cache() {
		$prop = self::get_property( $this->cache, 'file_stats' );
		$prop->setValue( $this->cache, [
			'/test/path/test.jpg' => [
				'size'  => '234',
				'mtime' => '123456779',
			],
		] );

		$expected = [
			'size'  => '411',
			'mtime' => '123459001',
		];

		$this->cache->cache_file_stats( '/test/path/test.jpg', $expected );

		$stats = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $stats['/test/path/test.jpg'] ) );
		$this->assertEquals( $expected, $stats['/test/path/test.jpg'] );
	}

	public function test__copy_to_cache() {
		$file_path = __DIR__ . '/../fixtures/files/upload.jpg';
		$prop      = self::get_property( $this->cache, 'files' );

		$this->cache->copy_to_cache( '/test/path/test.txt', $file_path );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files['/test/path/test.txt'] ) );
	}

	public function test__copy_to_cache__update_cache() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file, 'test data' );

		$prop = self::get_property( $this->cache, 'files' );
		$prop->setValue( $this->cache, [ '/test/path/test.jpg' => $test_file ] );

		$expected = 'updated data';

		$test_file2 = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file2, $expected );

		$this->cache->copy_to_cache( '/test/path/test.jpg', $test_file2 );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files['/test/path/test.jpg'] ) );
		$this->assertEquals( $expected, file_get_contents( $files['/test/path/test.jpg'] ) );
	}

	public function test__remove_file() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file, 'test data' );

		$files_prop = self::get_property( $this->cache, 'files' );
		$files_prop->setValue( $this->cache, [ '/test/path/test.jpg' => $test_file ] );

		$stats_prop = self::get_property( $this->cache, 'file_stats' );
		$stats_prop->setValue( $this->cache, [
			'/test/path/test.jpg' => [
				'size'  => '24',
				'mtime' => '123456779',
			],
		] );

		$this->cache->remove_file( '/test/path/test.jpg' );

		$files = $files_prop->getValue( $this->cache );

		$this->assertEmpty( $files );
		$this->assertFalse( file_exists( $test_file ) );

		$stats = $stats_prop->getValue( $this->cache );

		$this->assertEmpty( $stats );
	}

	public function test__remove_stats() {
		$prop = self::get_property( $this->cache, 'file_stats' );
		$prop->setValue( $this->cache, [
			'/test/path/test.jpg' => [
				'size'  => '234',
				'mtime' => '123456779',
			],
		] );

		$this->cache->remove_stats( '/test/path/test.jpg' );

		$stats = $prop->getValue( $this->cache );

		$this->assertFalse( isset( $stats['/test/path/test.jpg'] ) );
	}
}
