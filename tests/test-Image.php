<?php

/**
 * Class A8C_Files_Image_Test
 *
 * @group srcset
 */
class A8C_Files_Image_Test extends \WP_UnitTestCase {

	/**
	 * The test image.
	 *
	 * @var string
	 */
	public $test_image = __DIR__ . '/fixtures/image.jpg'; //@todo: consider using `DIR_TESTDATA . '/images/canola.jpg';`

	/**
	 * Load the Automattic\VIP\Files\ImageSizes class.
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../a8c-files/Image.php' );
	}

	/**
	 * Set the test to the original initial state of the VIP Go.
	 *
	 * 1. A8C files being in place, no srcset.
	 */
	public function setUp() {
		parent::setUp();

		$this->enable_a8c_files();
	}

	/**
	 * Cleanup after a test.
	 *
	 * Remove added uploads.
	 */
	public function tearDown() {

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
	 * @param string $name Name of the method.
	 *
	 * @return ReflectionMethod
	 */
	protected static function getMethod( $name ) {
		$class = new ReflectionClass( 'Automattic\\VIP\\Files\\Image' );
		$method = $class->getMethod( $name );
		$method->setAccessible(true);
		return $method;
	}

	/**
	 * Helper function for accessing protected property.
	 *
	 * @param string $name Name of the property.
	 *
	 * @return ReflectionProperty
	 */
	protected function getProperty( $name ) {
		$class = new ReflectionClass( 'Automattic\\VIP\\Files\\Image' );
		$property = $class->getProperty( $name );
		$property->setAccessible(true);
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

		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_id );

		$this->assertEquals( $postmeta['width'], $image->get_width() );
		$this->assertEquals( $postmeta['height'], $image->get_height() );
		$this->assertEquals( 'image/jpeg', $image->get_mime_type() );
		$this->assertFalse( $image->is_resized() );
	}

	/**
	 * Data provider for testing the Automattic\VIP\Files\ImageSizes::generate_sizes.
	 *
	 * @return array Array of Arrays of Arrays in order to provide input and expected output.
	 */
	public function get_data_for_generate_sizes() {
		return [
			'thumbnail' => [
				[
					'width' => '150',
					'height' => '150',
					'crop' => '1',
				],
				[
					'width' => 150,
					'height' => 150,
					'params' => [
						'resize' => '150,150',
					],
				]
			],
			'medium' => [
				[
					'width' => '300',
					'height' => '300',
					'crop' => false,
				],
				[
					'width' => 300,
					'height' => 169,
					'params' => [
						'resize' => '300,169',
					],
				]
			],
			'medium_large' => [
				[
					'width' => '768',
					'height' => '0',
					'crop' => false,
				],
				[
					'width' => 768,
					'height' => 432,
					'params' => [
						'resize' => '768,432',
					],
				]
			],
			'large' => [
				[
					'width' => '1024',
					'height' => '1024',
					'crop' => false,
				],
				[
					'width' => 1024,
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

		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_id );

		$image->resize( $size );

		// Resized image should be marked as resized.
		$this->assertTrue( $image->is_resized() );
		// Should have expected width.
		$this->assertEquals( $expected_resize['width'], $image->get_width() );
		// And height.
		$this->assertEquals( $expected_resize['height'], $image->get_height() );
		// Should have appropriate mime type.
		$this->assertEquals( 'image/jpeg', $image->get_mime_type() );
		// And should point to appropriate file.
		$this->assertEquals( add_query_arg( $expected_resize['params'], 'image.jpg' ), $image->get_filename() );
	}

	/**
	 * Unit test covering the get_filename method
	 *
	 * @covers Automattic\VIP\Files\Image::get_filename
	 */
	public function test__get_filename() {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_id );

		// Test getting original filename before the image is resized
		$this->assertEquals( wp_basename( $this->test_image ), $image->get_filename() );

		// Test resized filename
		$image->resize( array( 'width' => 150, 'height' => 150, 'crop' => true ) );
		$this->assertEquals( wp_basename( $this->test_image ) . '?resize=150,150', $image->get_filename() );
	}

	/**
	 * Test get_resized_filename
	 *
	 * @covers Automattic\VIP\Files\Image::get_resized_filename
	 */
	public function test__get_resized_filename() {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$image = new Automattic\VIP\Files\Image( $postmeta, $attachment_id );

		$width = $this->getProperty( 'width' );
		$width->setValue( $image, 150 );

		$height = $this->getProperty( 'height' );
		$height->setValue( $image, 150 );

		$get_resized_filename = $this->getMethod( 'get_resized_filename' );

		$this->assertEquals( $this->test_image . '?resize=150,150', $get_resized_filename->invokeArgs( $image, [] ) );
	}

}