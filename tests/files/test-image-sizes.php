<?php

require_once __DIR__ . '/../../files/class-image.php';
require_once __DIR__ . '/../../files/class-image-sizes.php';

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
class A8C_Files_ImageSizes_Test extends WP_UnitTestCase {
	/**
	 * The test image.
	 *
	 * @var string
	 */
	public $test_image = VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/image.jpg'; //@todo: consider using `DIR_TESTDATA . '/images/canola.jpg';`

	/**
	 * The test PDF file.
	 *
	 * @var string
	 */
	public $test_pdf = VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/pdf.pdf';

	/**
	 * Set the test to the original initial state of the VIP Go.
	 *
	 * 1. A8C files being in place, no srcset.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->enable_a8c_files();
		$this->enable_image_sizes();
	}

	/**
	 * Cleanup after a test.
	 *
	 * Remove added uploads.
	 */
	public function tearDown(): void {

		$this->remove_added_uploads();

		/*
		 * Can't use `@backupStaticAttributes enabled` due to
		 * `Exception: Serialization of 'Closure' is not allowed`
		 * Thus resetting the static property manually after each test.
		 */
		Automattic\VIP\Files\ImageSizes::$sizes = null;

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
		$class  = new ReflectionClass( 'Automattic\\VIP\\Files\\ImageSizes' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Helper function for turning the srcset implementation off.
	 */
	public function disable_image_sizes() {
		remove_filter( 'wp_get_attachment_metadata', '\\a8c_files_maybe_inject_image_sizes', 20, 2 );
	}

	/**
	 * Helper function for turning the srcset implementation on.
	 */
	public function enable_image_sizes() {
		add_filter( 'wp_get_attachment_metadata', '\\a8c_files_maybe_inject_image_sizes', 20, 2 );
	}

	/**
	 * Helper function for turning the a8c_files implementation off.
	 */
	public function disable_a8c_files() {
		remove_action( 'init', 'a8c_files_init' );
		remove_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );
		remove_filter( 'intermediate_image_sizes_advanced', 'wpcom_intermediate_sizes' );
		remove_filter( 'fallback_intermediate_image_sizes', 'wpcom_intermediate_sizes' );
	}

	/**
	 * Helper function for turning the a8c_files implementation on.
	 */
	public function enable_a8c_files() {
		add_action( 'init', 'a8c_files_init' );
		add_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );
		add_filter( 'intermediate_image_sizes_advanced', 'wpcom_intermediate_sizes' );
		add_filter( 'fallback_intermediate_image_sizes', 'wpcom_intermediate_sizes' );
	}

	/**
	 * Data provider for testing the Automattic\VIP\Files\ImageSizes::generate_sizes.
	 *
	 * Provides WordPress default sizes set
	 *
	 * @return array Array of arrays of arrays
	 */
	public function get_data_for_generate_sizes() {
		return [
			[
				[
					'thumbnail'    => [
						'width'  => '150',
						'height' => '150',
						'crop'   => '1',
					],
					'medium'       => [
						'width'  => '300',
						'height' => '300',
						'crop'   => false,
					],
					'medium_large' => [
						'width'  => '768',
						'height' => '0',
						'crop'   => false,
					],
					'large'        => [
						'width'  => '1024',
						'height' => '1024',
						'crop'   => false,
					],
					'1536x1536'    => [
						'width'  => '1536',
						'height' => '1536',
						'crop'   => false,
					],
					'2048x2048'    => [
						'width'  => '2048',
						'height' => '2048',
						'crop'   => false,
					],
				],
			],
		];
	}

	/**
	 * Unit test covering the generate_sizes method.
	 *
	 * @covers Automattic\VIP\Files\ImageSizes::generate_sizes
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

		$postmeta    = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$image_sizes = new Automattic\VIP\Files\ImageSizes( $attachment_id, $postmeta );
		// Disable the static property generated during construction.
		$image_sizes::$sizes = null;

		$generate_sizes  = self::getMethod( 'generate_sizes' );
		$generated_sizes = $generate_sizes->invokeArgs( $image_sizes, [] );

		$this->assertEquals( $expected_sizes, $generated_sizes, 'Mismatching arrayof generated sizes meta.' );
	}

	/**
	 * Data Provider for testing the Automattic\VIP\Files\ImageSizes::resize
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
	 * @covers Automattic\VIP\Files/ImageSizes::resize
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

		$image_sizes = new Automattic\VIP\Files\ImageSizes( $attachment_id, $postmeta );

		$generate_sizes = self::getMethod( 'resize' );

		$expected_resize = [
			'file'      => 'image.jpg?resize=150,150',
			'width'     => intval( $data['width'] ),
			'height'    => intval( $data['height'] ),
			'mime-type' => 'image/jpeg',
		];
		$this->assertEquals( $expected_resize, $generate_sizes->invokeArgs( $image_sizes, [ $data ] ) );
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
					'thumbnail'    => [
						'file'      => 'image.jpg?resize=150,150',
						'width'     => 150,
						'height'    => 150,
						'mime-type' => 'image/jpeg',
					],
					'medium'       => [
						'file'      => 'image.jpg?resize=300,169',
						'width'     => 300,
						'height'    => 169,
						'mime-type' => 'image/jpeg',
					],
					'medium_large' => [
						'file'      => 'image.jpg?resize=768,432',
						'width'     => 768,
						'height'    => 432,
						'mime-type' => 'image/jpeg',
					],
					'large'        => [
						'file'      => 'image.jpg?resize=1024,576',
						'width'     => 1024,
						'height'    => 576,
						'mime-type' => 'image/jpeg',
					],
					'1536x1536'    => [
						'file'      => 'image.jpg?resize=1536,865',
						'width'     => 1536,
						'height'    => 865,
						'mime-type' => 'image/jpeg',
					],
					'2048x2048'    => [
						'file'      => 'image.jpg?resize=2048,1153',
						'width'     => 2048,
						'height'    => 1153,
						'mime-type' => 'image/jpeg',
					],
				],
			],
		];
	}

	/**
	 * Unit test covering the generate_sizes_meta method.
	 *
	 * @covers Automattic\VIP\Files/ImageSizes::generate_sizes_meta
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

		$postmeta         = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$image_sizes      = new Automattic\VIP\Files\ImageSizes( $attachment_id, $postmeta );
		$image_sizes_meta = $image_sizes->generate_sizes_meta();

		$this->assertEquals( $expected_sizes_meta, $image_sizes_meta );
	}


	public function get_size_data_for_standardize_size_data() {
		return [
			[
				[
					'width' => 10,
				],
				[
					'width'  => 10,
					'height' => null,
					'crop'   => false,
				],
			],
			[
				[
					'height' => 10,
				],
				[
					'width'  => null,
					'height' => 10,
					'crop'   => false,
				],
			],
			[
				[
					'crop' => false,
				],
				[],
			],
			[
				[
					'width'  => 10,
					'height' => 10,
				],
				[
					'width'  => 10,
					'height' => 10,
					'crop'   => false,
				],
			],
		];
	}

	/**
	 * Test the size_data validation and standardisation.
	 *
	 * @covers Automattic\VIP\Files\ImageSizes::standardize_size_data
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

		$postmeta               = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$image_sizes            = new Automattic\VIP\Files\ImageSizes( $attachment_id, $postmeta );
		$standardised_size_data = $image_sizes->standardize_size_data( $size_data );

		$this->assertEquals( $expected, $standardised_size_data );
	}

	/**
	 * Default image sizes as defined in Database schema.
	 *
	 * @return array Default image sizes.
	 */
	public function default_sizes() {
		return [
			'thumbnail'    => [
				'width'                 => 150,
				'height'                => 150,
				'calculated_dimensions' => [
					'width'  => 150,
					'height' => 150,
				],
				'params'                => [
					'resize' => '150,150',
				],
			],
			'medium'       => [
				'width'                 => 300,
				'height'                => 300,
				'calculated_dimensions' => [
					'width'  => 300,
					'height' => 169,
				],
				'params'                => [
					'resize' => '300,169',
				],
			],
			'medium_large' => [
				'width'                 => 768,
				'height'                => 0,
				'calculated_dimensions' => [
					'width'  => 768,
					'height' => 432,
				],
				'params'                => [
					'resize' => '768,432',
				],
			],
			'large'        => [
				'width'                 => 1024,
				'height'                => 1024,
				'calculated_dimensions' => [
					'width'  => 1024,
					'height' => 576,
				],
				'params'                => [
					'resize' => '1024,576',
				],
			],
			'1536x1536'    => [
				'width'                 => 1536,
				'height'                => 1536,
				'calculated_dimensions' => [
					'width'  => 1536,
					'height' => 865,
				],
				'params'                => [
					'resize' => '1536,865',
				],
			],
			'2048x2048'    => [
				'width'                 => 2048,
				'height'                => 2048,
				'calculated_dimensions' => [
					'width'  => 2048,
					'height' => 1153,
				],
				'params'                => [
					'resize' => '2048,1153',
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

		$this->assertEmpty( $postmeta['sizes'], 'Intermediate image sizes has been physically created.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertEmpty( $metadata['sizes'], 'Some filter must be filtering sizes key of image metadata.' );
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

		$this->assertEmpty( $postmeta['sizes'], 'Intermediate image sizes has been physically created.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertNotEmpty( $metadata['sizes'], 'Virtual copies were not created.' );
	}

	/**
	 * Integration test of the virtual creation of intermediate sizes for non-image files.
	 * No physical copies are being created.
	 *
	 * @group srcset-pdf
	 */
	public function test__inject_image_sizes_for_pdf() {
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_pdf, 0, [
				'post_mime_type' => 'application/pdf',
				'post_type'      => 'attachment',
			]
		);
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_pdf ) );

		$postmeta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		$this->assertEmpty( $postmeta, 'Intermediate image sizes has been physically created.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertEmpty( $metadata, 'Virtual copies were created.' );

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

		$this->assertEquals( array_keys( $this->default_sizes() ), array_keys( $metadata['sizes'] ), 'Some registered sizes have not been created.' );

		// Have all sizes have the right dimensions?
		foreach ( $this->default_sizes() as $size => $properties ) {
			$this->assertEquals( $properties['calculated_dimensions']['width'], $metadata['sizes'][ $size ]['width'], 'Incorrect calculated width.' );
			$this->assertEquals( $properties['calculated_dimensions']['height'], $metadata['sizes'][ $size ]['height'], 'Incorrect calculated height.' );
		}
	}

	/**
	 * Integration test for properly generated dimensions of a custom size.
	 */
	public function test__custom_size() {
		// Register the custom size.
		$custom_size_name = 'custom_size';
		$width            = 200;
		$height           = 180;
		add_image_size( $custom_size_name, $width, $height, true );
		try {
			$attachment_id = self::factory()->attachment->create_object(
				$this->test_image, 0, [
					'post_mime_type' => 'image/jpeg',
					'post_type'      => 'attachment',
				]
			);
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_image ) );

			$metadata = wp_get_attachment_metadata( $attachment_id );

			$this->assertEquals( array_merge( array_keys( $this->default_sizes() ), [ $custom_size_name ] ), array_keys( $metadata['sizes'] ), 'The newly registered image size has not been created.' );

			// Has all the sizes, including the new one, have correct dimensions?
			foreach ( $this->default_sizes() as $size => $properties ) {
				$this->assertEquals( $properties['calculated_dimensions']['width'], $metadata['sizes'][ $size ]['width'], 'Incorrect calculated width.' );
				$this->assertEquals( $properties['calculated_dimensions']['height'], $metadata['sizes'][ $size ]['height'], 'Incorrect calculated height.' );
			}

			// Does the custom size have the correct dimensions?
			$this->assertEquals( $height, $metadata['sizes']['custom_size']['height'], 'Incorrect calculated height for the custom size.' );
			$this->assertEquals( $width, $metadata['sizes']['custom_size']['width'], 'Incorrect calculated width for the custom size.' );
		} finally {
			remove_image_size( $custom_size_name );
		}
	}

	/**
	 * Integration test of properly generated URLs to VIP Go File Service.
	 */
	public function test__correctness_of_the_urls() {
		// Register the custom size.
		$custom_size_name = 'custom_size';
		try {
			add_image_size( $custom_size_name, 200, 180, true );
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
			foreach ( $this->default_sizes() as $size => $properties ) {
				$this->assertEquals( add_query_arg( $properties['params'], $filename ), $metadata['sizes'][ $size ]['file'], sprintf( 'Incorrect file or query params for %s size.', $size ) );
			}

			// Check the custom size.
			$this->assertEquals( add_query_arg( [ 'resize' => '200,180' ], $filename ), $metadata['sizes'][ $custom_size_name ]['file'], 'Incorrect file for custom size.' );
		} finally {
			remove_image_size( $custom_size_name );
		}
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
			'http://example.org/wp-content/uploads/' . $this->test_image . '?resize=300,169 300w'
			. ', http://example.org/wp-content/uploads/' . $this->test_image . '?resize=768,432 768w'
			. ', http://example.org/wp-content/uploads/' . $this->test_image . '?resize=1024,576 1024w'
			. ', http://example.org/wp-content/uploads/' . $this->test_image . '?resize=1536,865 1536w'
			. ', http://example.org/wp-content/uploads/' . $this->test_image . '?resize=2048,1153 2048w';

		$this->assertEquals( $expected_srcset, wp_get_attachment_image_srcset( $attachment_id ) );

		// Test custom size passed by dimensions.
		$expected_srcset = 'http://example.org/wp-content/uploads/' . $this->test_image . ' 5472w' . ", {$expected_srcset}";

		$this->assertEquals( $expected_srcset, wp_get_attachment_image_srcset( $attachment_id, [ 400, 200 ] ), 'Incorrectly generated srcset.' );
	}
}
