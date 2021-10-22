<?php

require_once __DIR__ . '/../../files/class-image.php';

/**
 * Class A8C_Files_Image_Test
 *
 * @group srcset
 */
class A8C_Files_Image_Test extends WP_UnitTestCase {

	/**
	 * The test image.
	 *
	 * @var string
	 */
	public $test_image = VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/image.jpg'; //@todo: consider using `DIR_TESTDATA . '/images/canola.jpg';`

	/**
	 * Set the test to the original initial state of the VIP Go.
	 *
	 * 1. A8C files being in place, no srcset.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->enable_a8c_files();
	}

	/**
	 * Cleanup after a test.
	 *
	 * Remove added uploads.
	 */
	public function tearDown(): void {

		$this->remove_added_uploads();

		parent::tearDown();
	}

	/**
	 * Helper function for turning the a8c_files implementation on.
	 */
	public function enable_a8c_files() {
		add_action( 'init', 'a8c_files_init' );
		add_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );
		add_filter( 'intermediate_image_sizes_advanced', 'wpcom_intermediate_sizes' );
	}

	/**
	 * Helper function for accessing protected method.
	 * 
	 * Renamed from `getMethod` to `get_method` for consistency with `get_property` (see below)
	 *
	 * @param string $name Name of the method.
	 *
	 * @return ReflectionMethod
	 */
	protected static function get_method( $name ) {
		$class  = new ReflectionClass( 'Automattic\\VIP\\Files\\Image' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Helper function for accessing protected property.
	 * 
	 * Renamed from `getProperty` `to `get_property` to avoid conflicts with PHPUnit Polyfill's `getProperty()` method
	 * which is used by `assertAttributeXXX` assertions.
	 *
	 * @param string $name Name of the property.
	 *
	 * @return ReflectionProperty
	 */
	protected function get_property( $name ) {
		$class    = new ReflectionClass( 'Automattic\\VIP\\Files\\Image' );
		$property = $class->getProperty( $name );
		$property->setAccessible( true );
		return $property;
	}

	/**
	 * Unit test covering the object initialisation.
	 *
	 * @covers Automattic\VIP\Files\Image::__construct
	 *
	 * @param array $expected_sizes Array of expected sizes.
	 */
	public function test__object_construction() {
		$attachment_post_data = [
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
		];
		$attachment_id        = self::factory()->attachment->create_object( $this->test_image, 0, $attachment_post_data );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );
		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_post_data['post_mime_type'] );

		$this->assertEquals( $postmeta['width'], $image->get_width(), 'Wrong image width.' );
		$this->assertEquals( $postmeta['height'], $image->get_height(), 'Wrong image height.' );
		$this->assertEquals( 'image/jpeg', $image->get_mime_type(), 'Wrong image mime type.' );
		$this->assertFalse( $image->is_resized(), 'Non-resized image is marked as resized.' );
	}

	/**
	 * Data provider for testing the Automattic\VIP\Files\ImageSizes::generate_sizes.
	 *
	 * @return array Array of Arrays of Arrays in order to provide input and expected output.
	 */
	public function get_data_for_generate_sizes() {
		return [
			'thumbnail'    => [
				[
					'width'  => '150',
					'height' => '150',
					'crop'   => '1',
				],
				[
					'width'  => 150,
					'height' => 150,
					'params' => [
						'resize' => '150,150',
					],
				],
			],
			'medium'       => [
				[
					'width'  => '300',
					'height' => '300',
					'crop'   => false,
				],
				[
					'width'  => 300,
					'height' => 169,
					'params' => [
						'resize' => '300,169',
					],
				],
			],
			'medium_large' => [
				[
					'width'  => '768',
					'height' => '0',
					'crop'   => false,
				],
				[
					'width'  => 768,
					'height' => 432,
					'params' => [
						'resize' => '768,432',
					],
				],
			],
			'large'        => [
				[
					'width'  => '1024',
					'height' => '1024',
					'crop'   => false,
				],
				[
					'width'  => 1024,
					'height' => 576,
					'params' => [
						'resize' => '1024,576',
					],
				],
			],
		];
	}

	/**
	 * Unit test covering the object initialisation.
	 *
	 * @covers Automattic\VIP\Files\Image::resize
	 * @dataProvider get_data_for_generate_sizes
	 *
	 * @param array $expected_sizes Array of requested size dimensions.
	 */
	public function test__image_resize( $size, $expected_resize ) {
		$attachment_post_data = [
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
		];
		$attachment_id        = self::factory()->attachment->create_object( $this->test_image, 0, $attachment_post_data );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );
		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_post_data['post_mime_type'] );
		$image->resize( $size );

		$this->assertTrue( $image->is_resized(), 'Resized image is not marked as resized.' );
		$this->assertEquals( $expected_resize['width'], $image->get_width(), 'Resized image does not have expected width.' );
		$this->assertEquals( $expected_resize['height'], $image->get_height(), 'Resized image does not have expected height.' );
		$this->assertEquals( 'image/jpeg', $image->get_mime_type(), 'Resized image does not have appropriate mime type.' );
		$this->assertEquals( add_query_arg( $expected_resize['params'], 'image.jpg' ), $image->get_filename(), 'Resized image does not point to appropriate file.' );
	}

	/**
	 * Test size array generation.
	 *
	 * @covers Automattic\VIP\Files\Image::get_size
	 * @dataProvider get_data_for_generate_sizes
	 *
	 * @return array
	 */
	public function test__get_size( $size, $expected_resize ) {
		$attachment_post_data = [
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
		];
		$attachment_id        = self::factory()->attachment->create_object( $this->test_image, 0, $attachment_post_data );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );
		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image          = new Automattic\VIP\Files\Image( $postmeta, $attachment_post_data['post_mime_type'] );
		$new_size_array = $image->get_size( $size );

		$expected_size_array = [
			'file'      => add_query_arg( $expected_resize['params'], 'image.jpg' ),
			'width'     => $expected_resize['width'],
			'height'    => $expected_resize['height'],
			'mime-type' => 'image/jpeg',
		];
		$this->assertEquals( $expected_size_array, $new_size_array, 'The size array does not match the expected one.' );
	}

	/**
	 * Test the reset of dimensions to original.
	 *
	 * @covers Automattic\VIP\Files\Image::reset_to_original
	 * @dataProvider get_data_for_generate_sizes
	 *
	 * @param array $expected_sizes Array of requested size dimensions.
	 */
	public function test__reset_to_original( $size ) {
		$attachment_post_data = [
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
		];
		$attachment_id        = self::factory()->attachment->create_object( $this->test_image, 0, $attachment_post_data );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );
		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_post_data['post_mime_type'] );
		$image->resize( $size );
		$image->reset_to_original();

		$this->assertFalse( $image->is_resized(), 'Image is not marked as NOT resized.' );
		$this->assertEquals( $postmeta['width'], $image->get_width(), 'Width has not been properly reset.' );
		$this->assertEquals( $postmeta['height'], $image->get_height(), 'Height has not been properly reset.' );
		$this->assertEquals( 'image/jpeg', $image->get_mime_type(), 'Mime-type has not been properly reset' );
		$this->assertEquals( 'image.jpg', $image->get_filename(), 'Image after reset does not point to appropriate file.' );
	}

	/**
	 * Unit test covering the get_filename method
	 *
	 * @covers Automattic\VIP\Files\Image::get_filename
	 */
	public function test__get_filename() {
		$attachment_post_data = [
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
		];
		$attachment_id        = self::factory()->attachment->create_object( $this->test_image, 0, $attachment_post_data );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );
		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_post_data['post_mime_type'] );

		$this->assertEquals( wp_basename( $this->test_image ), $image->get_filename(), 'Wrong original filename before image resize.' );
	}

	/**
	 * Test get_filename method after image resize.
	 *
	 * @covers Automattic\VIP\Files\Image::get_filename
	 */
	public function test__get_filename_after_resize() {
		$attachment_post_data = [
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
		];
		$attachment_id        = self::factory()->attachment->create_object( $this->test_image, 0, $attachment_post_data );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );
		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_post_data['post_mime_type'] );
		$image->resize( array(
			'width'  => 150,
			'height' => 150,
			'crop'   => true,
		) );

		$this->assertEquals( wp_basename( $this->test_image ) . '?resize=150,150', $image->get_filename(), 'Wrong filename after image resize.' );
	}

	/**
	 * Test get_resized_filename
	 *
	 * @covers Automattic\VIP\Files\Image::get_resized_filename
	 */
	public function test__get_resized_filename() {
		$attachment_post_data = [
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
		];
		$attachment_id        = self::factory()->attachment->create_object( $this->test_image, 0, $attachment_post_data );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );
		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_post_data['post_mime_type'] );
		$width = $this->get_property( 'width' );
		$width->setValue( $image, 150 );
		$height = $this->get_property( 'height' );
		$height->setValue( $image, 150 );
		$get_resized_filename = $this->get_method( 'get_resized_filename' );

		$this->assertEquals( $this->test_image . '?resize=150,150', $get_resized_filename->invokeArgs( $image, [] ) );
	}

}
