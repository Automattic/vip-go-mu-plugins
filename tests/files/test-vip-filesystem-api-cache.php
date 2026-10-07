<?php

namespace Automattic\VIP\Files;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_property_as_public;

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

	public function test__get_instance() {
		$instance_b = API_Cache::get_instance();

		$this->assertSame( $this->cache, $instance_b );
	}

	public function test__clear_tmp_files() {
		$file1 = tempnam( sys_get_temp_dir(), 'test' );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam
		$file2 = tempnam( sys_get_temp_dir(), 'test' );     // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam

		$files_prop = get_class_property_as_public( API_Cache::class, 'files' );
		$files_prop->setValue( $this->cache, [
			'test.jpg'  => $file1,
			'test2.jpg' => $file2,
		] );

		$stats_prop = get_class_property_as_public( API_Cache::class, 'file_stats' );
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

		$prop = get_class_property_as_public( API_Cache::class, 'files' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $test_file ] );

		$actual = $this->cache->get_file( 'test.jpg' );

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$this->assertEquals( $expected, file_get_contents( $actual ) );
		// Files are cached by their full path, not their name.
		$this->assertFalse( $this->cache->get_file( '/tmp/test.jpg' ) );
	}

	public function test__get_file__invalid_file() {
		$result = $this->cache->get_file( 'test.jpg' );

		$this->assertFalse( $result );
	}

	public function test__get_file_stats() {
		$expected = [
			'size'  => '123',
			'mtime' => '123456779',
		];

		$prop = get_class_property_as_public( API_Cache::class, 'file_stats' );
		$prop->setValue( $this->cache, [ 'test.jpg' => $expected ] );

		$actual = $this->cache->get_file_stats( 'test.jpg' );

		$this->assertEquals( $expected, $actual );
		// Stats are cached by the file's full path, not its name.
		$this->assertFalse( $this->cache->get_file_stats( '/tmp/test.jpg' ) );
	}

	public function test__get_file_stats__invalid_file() {
		$result = $this->cache->get_file_stats( 'test.jpg' );

		$this->assertFalse( $result );
	}

	public function test__cache_file() {
		$prop = get_class_property_as_public( API_Cache::class, 'files' );

		$file = tempnam( sys_get_temp_dir(), 'test' );

		$this->cache->cache_file( '/test/path/test.txt', $file );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files['/test/path/test.txt'] ) );
	}

	public function test__cache_file__update_cache() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file, 'test data' );

		$prop = get_class_property_as_public( API_Cache::class, 'files' );
		$prop->setValue( $this->cache, [ '/test/path/test.jpg' => $test_file ] );

		$expected = 'updated data';

		$updated_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $updated_file, $expected );

		$this->cache->cache_file( '/test/path/test.jpg', $updated_file );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files['/test/path/test.jpg'] ) );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local file
		$this->assertEquals( $expected, file_get_contents( $files['/test/path/test.jpg'] ) );
	}

	public function test__cache_file_stats() {
		$prop     = get_class_property_as_public( API_Cache::class, 'file_stats' );
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
		$prop = get_class_property_as_public( API_Cache::class, 'file_stats' );
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

	/**
	 * A newly allocated cache file must contain the source bytes.
	 */
	public function test__copy_to_cache(): void {
		$file_path   = __DIR__ . '/../fixtures/files/upload.jpg';
		$destination = '/test/path/test.txt';
		$this->assertFalse( $this->cache->get_file( $destination ) );
		$this->cache->copy_to_cache( $destination, $file_path );
		$cached_path = $this->cache->get_file( $destination );
		$this->assertIsString( $cached_path );
		$this->assertNotSame( $file_path, $cached_path );
		$this->assertFileExists( $cached_path );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Both paths are local test files.
		$this->assertSame( file_get_contents( $file_path ), file_get_contents( $cached_path ) );
	}

	public function test__copy_to_cache__update_cache() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file, 'test data' );

		$prop = get_class_property_as_public( API_Cache::class, 'files' );
		$prop->setValue( $this->cache, [ '/test/path/test.jpg' => $test_file ] );

		$expected = 'updated data';

		$test_file2 = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file2, $expected );

		$this->cache->copy_to_cache( '/test/path/test.jpg', $test_file2 );

		$files = $prop->getValue( $this->cache );

		$this->assertTrue( isset( $files['/test/path/test.jpg'] ) );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local file
		$this->assertEquals( $expected, file_get_contents( $files['/test/path/test.jpg'] ) );
	}

	public function test__remove_file() {
		$test_file = tempnam( sys_get_temp_dir(), 'test' );

		file_put_contents( $test_file, 'test data' );

		$files_prop = get_class_property_as_public( API_Cache::class, 'files' );
		$files_prop->setValue( $this->cache, [ '/test/path/test.jpg' => $test_file ] );

		$stats_prop = get_class_property_as_public( API_Cache::class, 'file_stats' );
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
		$prop = get_class_property_as_public( API_Cache::class, 'file_stats' );
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
