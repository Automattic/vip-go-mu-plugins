<?php
/**
 * Test cases for the fix_img_block_sizes functionality in A8C_Files class.
 *
 * @package VIP_Go
 */

/**
 * Class VIP_Go_Fix_Block_Img_Tag_Sizes_Test
 *
 * @package VIP_Go
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
				'expected'      => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" width="300" height="200" /></figure>',
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
		// Mock is_admin().
		global $current_screen;
		$current_screen = $is_admin ? new stdClass() : null;

		// Mock WP_HTML_Tag_Processor class if needed.
		if ( ! $is_admin && ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$this->getMockBuilder( 'WP_HTML_Tag_Processor' )
				->getMock();
		}

		// Mock wp_get_attachment_metadata if metadata is provided.
		if ( $metadata ) {
			$attachment_id = 123; // Matches the wp-image-123 in test data.
			add_filter(
				'wp_get_attachment_metadata',
				function ( $data, $id ) use ( $metadata, $attachment_id ) {
					return $id === $attachment_id ? $metadata : $data;
				},
				10,
				2
			);
		}

		$files  = new A8C_Files();
		$actual = $files->fix_img_block_sizes( $block_content, array(), null );

		$this->assertEquals( $expected, $actual );
	}
} 
