<?php

/**
 * Class A8C_Files_ImageSizes_Test
 *
 * The default sizes used in the tests were collected from the database schema:
 *
 * https://github.com/WordPress/WordPress/blob/176a28905041fd79c439946a4ba290a87db5991f/wp-admin/includes/schema.php#L486,L490
 * https://github.com/WordPress/WordPress/blob/176a28905041fd79c439946a4ba290a87db5991f/wp-admin/includes/schema.php#L496,L497
 * https://github.com/WordPress/WordPress/blob/176a28905041fd79c439946a4ba290a87db5991f/wp-admin/includes/schema.php#L533,L534
 *
 * @group srcset
 */
class A8C_Files_ImageSizes_Test extends \WP_UnitTestCase {

	/**
	 * The test image.
	 *
	 * @var string
	 */
	public $test_image = __DIR__ . '/fixtures/image.jpg'; //@todo: consider using `DIR_TESTDATA . '/images/canola.jpg';`

	/**
	 * Load the A8C_Files\ImageSizes class.
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../a8c-files/Image.php' );
		require_once( __DIR__ . '/../a8c-files/ImageSizes.php' );
	}

	/**
	 * Set the test to the original initial state of the VIP Go.
	 *
	 * 1. A8C files being in place, no srcset.
	 */
	public function setUp() {
		parent::setUp();

		$this->enable_a8c_files();
		$this->enable_image_sizes();

	}

	/**
	 * Cleanup after a test.
	 *
	 * Remove added uploads.
	 */
	public function tearDown() {

		$this->remove_added_uploads();

		/*
		 * Can't use `@backupStaticAttributes enabled` due to
		 * `Exception: Serialization of 'Closure' is not allowed`
		 * Thus resetting the static property manually after each test.
		 */
		A8C_Files\ImageSizes::$sizes = null;

		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected method.
	 *
	 * @param string $name Name of the method.
	 *
	 * @return ReflectionMethod
	 */
	protected static function getMethod( $name ) {
		$class = new ReflectionClass( 'A8C_Files\\ImageSizes' );
		$method = $class->getMethod( $name );
		$method->setAccessible(true);
		return $method;
	}

	/**
	 * Helper function for turning the srcset implementation off.
	 */
	public function disable_image_sizes() {
		remove_filter( 'wp_get_attachment_metadata', 'A8C_Files\\maybe_inject_image_sizes', 20, 2 );
	}

	/**
	 * Helper function for turning the srcset implementation on.
	 */
	public function enable_image_sizes() {
		add_filter( 'wp_get_attachment_metadata', 'A8C_Files\\maybe_inject_image_sizes', 20, 2 );
	}

	/**
	 * Helper function for turning the a8c_files implementation off.
	 */
	public function disable_a8c_files() {
		remove_action( 'init', 'a8c_files_init' );
		remove_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );
		remove_filter( 'intermediate_image_sizes_advanced', 'wpcom_intermediate_sizes' );
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
	 * Data provider for testing the A8C_Files\ImageSizes::generate_sizes.
	 *
	 * Provides WordPress default sizes set
	 *
	 * @return array Array of arrays of arrays
	 */
	public function get_data_for_generate_sizes() {
		return [
			[
				[
					'thumbnail' => [
						'width' => '150',
						'height' => '150',
						'crop' => '1',
					],
					'medium' => [
						'width' => '300',
						'height' => '300',
						'crop' => false,
					],
					'medium_large' => [
						'width' => '768',
						'height' => '0',
						'crop' => false,
					],
					'large' => [
						'width' => '1024',
						'height' => '1024',
						'crop' => false,
					],
				],
			],
		];
	}

	/**
	 * Unit test covering the generate_sizes method.
	 *
	 * @covers A8C_Files\ImageSizes::generate_sizes
	 * @dataProvider get_data_for_generate_sizes
	 *
	 * @param array $expected_sizes Array of expected sizes.
	 */
	public function test__generate_sizes( $expected_sizes ) {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$imageSizes = new A8C_Files\ImageSizes( $attachment_id, $postmeta );
		// Disable the static property generated during construction.
		$imageSizes::$sizes = null;

		$generate_sizes = self::getMethod( 'generate_sizes' );
		$generated_sizes = $generate_sizes->invokeArgs( $imageSizes, [] );

		$this->assertEquals( $expected_sizes, $generated_sizes );
	}

	/**
	 * Data Provider for testing the A8C_Files\ImageSizes::resize
	 *
	 * @return mixed
	 */
	public function get_data_for_resize() {
		// Now we need the sizes in separate arrays
		$sizes = $this->get_data_for_generate_sizes();
		return array_shift( $sizes );
	}

	/**
	 * Unit test covering the resize method.
	 *
	 * @covers A8C_Files/ImageSizes::resize
	 * @dataProvider get_data_for_resize
	 *
	 * @param array $data Expected resize array.
	 */
	public function test__resize( $data ) {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$imageSizes = new A8C_Files\ImageSizes( $attachment_id, $postmeta );

		$generate_sizes = self::getMethod( 'resize' );

		$expected_resize = [
			'file' => 'image.jpg?resize=150,150',
			'width' => intval( $data['width'] ),
			'height' => intval( $data['height'] ),
			'mime-type' => 'image/jpeg',
		];

		$this->assertEquals( $expected_resize, $generate_sizes->invokeArgs( $imageSizes, [ $data ] ) );
	}

	/**
	 * Provides expected default sizes meta.
	 *
	 * @return array
	 */
	public function get_expected_sizes_meta() {
		return [
			[
				[
					'thumbnail' => [
						'file' => 'image.jpg?resize=150,150',
						'width' => 150,
						'height' => 150,
						'mime-type' => 'image/jpeg',
					],
					'medium' => [
						'file' => 'image.jpg?resize=300,169',
						'width' => 300,
						'height' => 169,
						'mime-type' => 'image/jpeg',
					],
					'medium_large' => [
						'file' => 'image.jpg?resize=768,432',
						'width' => 768,
						'height' => 432,
						'mime-type' => 'image/jpeg',
					],
					'large' => [
						'file' => 'image.jpg?resize=1024,576',
						'width' => 1024,
						'height' => 576,
						'mime-type' => 'image/jpeg',
					],
				]
			]
		];
	}

	/**
	 * Unit test covering the generate_sizes_meta method.
	 *
	 * @covers A8C_Files/ImageSizes::generate_sizes_meta
	 * @dataProvider get_expected_sizes_meta
	 */
	public function test__generate_sizes_meta( $expected_sizes_meta ) {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$imageSizes = new A8C_Files\ImageSizes( $attachment_id, $postmeta );
		$image_sizes_meta = $imageSizes->generate_sizes_meta();

		$this->assertEquals( $expected_sizes_meta, $image_sizes_meta );
	}


	public function get_size_data_for_standardize_size_data() {
		return [
			[
				[
					'width' => 10,
				],
				[
					'width' => 10,
					'height' => null,
					'crop' => false,
				]
			],
			[
				[
					'height' => 10,
				],
				[
					'width' => null,
					'height' => 10,
					'crop' => false,
				]
			],
			[
				[
					'crop' => false,
				],
				[

				]
			],
			[
				[
					'width' => 10,
					'height' => 10,
				],
				[
					'width' => 10,
					'height' => 10,
					'crop' => false,
				]
			]
		];
	}

	/**
	 * Test the size_data validation and standardisation.
	 *
	 * @covers A8C_Files\ImageSizes::standardize_size_data
	 * @dataProvider get_size_data_for_standardize_size_data
	 */
	public function test__standardize_size_data( $size_data, $expected ) {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$imageSizes = new A8C_Files\ImageSizes( $attachment_id, $postmeta );
		$standardised_size_data = $imageSizes->standardize_size_data( $size_data );

		$this->assertEquals( $expected, $standardised_size_data );
	}

	/**
	 * Default image sizes as defined in Database schema.
	 *
	 * @return array Default image sizes.
	 */
	public function default_sizes() {
		return [
			'thumbnail' => [
				'width' => 150,
				'height' => 150,
				'calculated_dimensions' => [
					'width' => 150,
					'height' => 150,
				],
				'params' => [
					'resize' => '150,150',
				],
			],
			'medium' => [
				'width' => 300,
				'height' => 300,
				'calculated_dimensions' => [
					'width' => 300,
					'height' => 169,
				],
				'params' => [
					'resize' => '300,169',
				],
			],
			'medium_large' => [
				'width' => 768,
				'height' => 0,
				'calculated_dimensions' => [
					'width' => 768,
					'height' => 432,
				],
				'params' => [
					'resize' => '768,432',
				],
			],
			'large' => [
				'width' => 1024,
				'height' => 1024,
				'calculated_dimensions' => [
					'width' => 1024,
					'height' => 576,
				],
				'params' => [
					'resize' => '1024,576',
				],
			],
		];
	}

	/**
	 * Integration test of the original behaviour of the VIP Go platform:
	 *
	 * 1. no intermediate image sizes are being physically created.
	 * 2. no intermediate image sizes are being filtered in.
	 */
	public function test__original_behaviour_on_vip_go() {

		$this->disable_image_sizes();

		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		// This means no intermediate image sizes were physically created.
		$this->assertEmpty( $postmeta['sizes'] );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// This means the sizes are not being created via any filters.
		$this->assertEmpty( $metadata['sizes'] );
	}

	/**
	 * Integration test of the virtual creation of intermediate sizes.
	 * No physical copies are being created.
	 */
	public function test__inject_image_sizes() {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		// This means that no intermediate image sizes were physically created.
		$this->assertEmpty( $postmeta['sizes'] );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// This means that some virtual copies were successfully created.
		$this->assertNotEmpty( $metadata['sizes'] );
	}

	/**
	 * Integration test for checking whether all sizes are properly generated.
	 */
	public function test__correct_sizes_are_created() {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Has all sizes?
		$this->assertEquals( array_keys( $this->default_sizes() ), array_keys( $metadata['sizes'] ) );

		// Have all sizes have the right dimensions?
		foreach( $this->default_sizes() as $size => $properties ) {
			$this->assertEquals( $properties['calculated_dimensions']['width'], $metadata['sizes'][ $size ]['width'] );
			$this->assertEquals( $properties['calculated_dimensions']['height'], $metadata['sizes'][ $size ]['height'] );
		}
	}

	/**
	 * Integration test for properly generated dimensions of a custom size.
	 */
	public function test__custom_size() {
		// Register the custom size.
		add_image_size( $custom_size_name = 'custom_size', $width = 200, $height = 180, $crop = true );
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Has all sizes, including the new one?
		$this->assertEquals( array_merge( array_keys( $this->default_sizes() ), [ $custom_size_name ] ), array_keys( $metadata['sizes'] ) );

		// Has all the sizes, including the new one, have correct dimensions?
		foreach( $this->default_sizes() as $size => $properties ) {
			$this->assertEquals( $properties['calculated_dimensions']['width'], $metadata['sizes'][ $size ]['width'] );
			$this->assertEquals( $properties['calculated_dimensions']['height'], $metadata['sizes'][ $size ]['height'] );
		}

		// Does the custom size have the correct dimensions?
		$this->assertEquals( $height, $metadata['sizes']['custom_size']['height'] );
		$this->assertEquals( $width, $metadata['sizes']['custom_size']['width'] );
	}

	/**
	 * Integration test of properly generated URLs to VIP Go File Service.
	 */
	public function test__correctness_of_the_urls() {
		// Register the custom size.
		add_image_size( $custom_size_name = 'custom_size', $width = 200, $height = 180, $crop = true );
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$filename = wp_basename( $this->test_image );

		// Check the default sizes.
		foreach( $this->default_sizes() as $size => $properties ) {
			$this->assertEquals( add_query_arg( $properties['params'], $filename ), $metadata['sizes'][ $size ]['file'] );
		}

		// Check the custom size.
		$this->assertEquals( add_query_arg( [ 'resize' => '200,180' ], $filename ), $metadata['sizes'][$custom_size_name]['file'] );
	}

	/**
	 * Integration test for wp_get_attachment_image_srcset
	 */
	public function test__generated_srcset() {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_image, 0, [
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

		// Test medium size.
		$expected_srcset =
			'http://example.org/wp-content/uploads/' . __DIR__ .  '/fixtures/image.jpg?resize=300,169 300w'
			.', http://example.org/wp-content/uploads/' . __DIR__ .  '/fixtures/image.jpg?resize=768,432 768w'
			.', http://example.org/wp-content/uploads/' . __DIR__ .  '/fixtures/image.jpg?resize=1024,576 1024w';

		$this->assertEquals( $expected_srcset, wp_get_attachment_image_srcset( $attachment_id ) );

		// Test custom size passed by dimensions.
		$expected_srcset = 'http://example.org/wp-content/uploads/' . __DIR__ . '/fixtures/image.jpg 5472w'
		                   . ", {$expected_srcset}";

		$this->assertEquals( $expected_srcset, wp_get_attachment_image_srcset( $attachment_id, [ 400, 200 ] ) );
	}
}