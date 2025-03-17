<?php

/**
 * Class VIP_Go_Fix_Block_Img_Tag_Sizes_Test
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class VIP_Go_Fix_Block_Img_Tag_Sizes_Test extends WP_UnitTestCase {
	/**
	 * Data provider for test__fix_img_block_sizes
	 *
	 * @return array Test cases.
	 */
	public function get_data_for_fix_img_block_sizes() {
		return array(
			'admin-context'     => array(
				'is_admin'      => true,
				'block_content' => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" /></figure>',
				'expected'      => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" /></figure>',
			),
			'no-html-api'       => array(
				'is_admin'      => false,
				'block_content' => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" /></figure>',
				'expected'      => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" /></figure>',
			),
			'no-img-tag'        => array(
				'is_admin'      => false,
				'block_content' => '<figure class="wp-block-image size-medium">No image here</figure>',
				'expected'      => '<figure class="wp-block-image size-medium">No image here</figure>',
			),
			'no-image-id'       => array(
				'is_admin'      => false,
				'block_content' => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="no-image-id" /></figure>',
				'expected'      => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="no-image-id" /></figure>',
			),
			'no-figure-tag'     => array(
				'is_admin'      => false,
				'block_content' => '<img src="test.jpg" class="wp-image-123" />',
				'expected'      => '<img src="test.jpg" class="wp-image-123" />',
			),
			'no-size-class'     => array(
				'is_admin'      => false,
				'block_content' => '<figure class="wp-block-image"><img src="test.jpg" class="wp-image-123" /></figure>',
				'expected'      => '<figure class="wp-block-image"><img src="test.jpg" class="wp-image-123" /></figure>',
			),
			'full-size'         => array(
				'is_admin'      => false,
				'block_content' => '<figure class="wp-block-image size-full"><img src="test.jpg" class="wp-image-123" /></figure>',
				'expected'      => '<figure class="wp-block-image size-full"><img src="test.jpg" class="wp-image-123" /></figure>',
			),
			'successful-update' => array(
				'is_admin'      => false,
				'block_content' => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" /></figure>',
				// THIS IS FLAKY - the actual output may vary depending on the order of the attributes passed, but it seems that the last passed arguments get at the beginning of the tag
				'expected'      => '<figure class="wp-block-image size-medium"><img height="200" width="300" src="test.jpg" class="wp-image-123" /></figure>',
				'metadata'      => array(
					'sizes' => array(
						'medium' => array(
							'width'  => 300,
							'height' => 200,
						),
					),
				),
			),
		);
	}

	/**
	 * Test the fix_img_block_sizes method.
	 *
	 * @dataProvider get_data_for_fix_img_block_sizes
	 * @param bool   $is_admin      Whether we're in admin context.
	 * @param string $block_content The block content to process.
	 * @param string $expected      The expected output.
	 * @param array  $metadata      Optional metadata for the image.
	 * @return void
	 */
	public function test__fix_img_block_sizes( $is_admin, $block_content, $expected, $metadata = null ) {
		
		if ( $is_admin && ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}

		// Mock wp_get_attachment_metadata if metadata is provided
		if ( $metadata ) {
			$attachment_id = 123; // Matches the wp-image-123 in test data
			$filter        = function ( $meta_value, $post_id, $meta_key ) use ( $metadata, $attachment_id ) {
				if ( '_wp_attachment_metadata' === $meta_key && $attachment_id == $post_id ) {
					return [ $metadata ];
				}
				return $meta_value;
			};
			add_filter( 'get_post_metadata', $filter, 10, 3 );
		}

		$files  = new A8C_Files();
		$actual = $files->fix_img_block_sizes( $block_content, array(), null );

		// For the successful-update case, add more detailed assertions
		if ( $metadata ) {
			$this->assertStringContainsString( 'width="300"', $actual, 'Width attribute should be present' );
			$this->assertStringContainsString( 'height="200"', $actual, 'Height attribute should be present' );
			$this->assertStringContainsString( 'class="wp-image-123"', $actual, 'Image class should be preserved' );
			$this->assertStringContainsString( 'class="wp-block-image size-medium"', $actual, 'Figure class should be preserved' );
			remove_filter( 'get_post_metadata', $filter, 10 );
		} else {
			$this->assertEquals( $expected, $actual );
		}
	}
}
