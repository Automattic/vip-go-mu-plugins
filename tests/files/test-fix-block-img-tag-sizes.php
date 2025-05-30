<?php

/**
 * Class VIP_Go_Fix_Block_Img_Tag_Sizes_Test
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class VIP_Go_Fix_Block_Img_Tag_Sizes_Test extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		$data = $this->getProvidedData();
		if ( ! empty( $data['metadata'] ) ) {
			$matches = [];
			if ( ! preg_match( '/wp-image-(\d+)/', $data['block_content'], $matches ) ) {
				return;
			}

			$attachment_id = (int) $matches[1];
			$metadata      = $data['metadata'];
			$filter        = function ( $meta_value, $post_id, $meta_key ) use ( $metadata, $attachment_id ) {
				if ( '_wp_attachment_metadata' === $meta_key && $attachment_id == $post_id ) {
					return [ $metadata ];
				}
				return $meta_value;
			};

			add_filter( 'get_post_metadata', $filter, 10, 3 );
		}
	}

	/**
	 * Data provider for test__fix_img_block_sizes
	 *
	 * @return array Test cases.
	 */
	public function get_data_for_fix_img_block_sizes() {
		return array(
			'no-html-api'       => array(
				'block_content' => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" /></figure>',
				'expected'      => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" /></figure>',
			),
			'no-img-tag'        => array(
				'block_content' => '<figure class="wp-block-image size-medium">No image here</figure>',
				'expected'      => '<figure class="wp-block-image size-medium">No image here</figure>',
			),
			'no-image-id'       => array(
				'block_content' => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="no-image-id" /></figure>',
				'expected'      => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="no-image-id" /></figure>',
			),
			'no-figure-tag'     => array(
				'block_content' => '<img src="test.jpg" class="wp-image-123" />',
				'expected'      => '<img src="test.jpg" class="wp-image-123" />',
			),
			'no-size-class'     => array(
				'block_content' => '<figure class="wp-block-image"><img src="test.jpg" class="wp-image-123" /></figure>',
				'expected'      => '<figure class="wp-block-image"><img src="test.jpg" class="wp-image-123" /></figure>',
			),
			'full-size'         => array(
				'block_content' => '<figure class="wp-block-image size-full"><img src="test.jpg" class="wp-image-123" /></figure>',
				'expected'      => '<figure class="wp-block-image size-full"><img src="test.jpg" class="wp-image-123" /></figure>',
			),
			'successful-update' => array(
				'block_content' => '<figure class="wp-block-image size-medium"><img src="test.jpg" class="wp-image-123" /></figure>',
				'expected'      => [
					'width="300"'                        => 'Width attribute should be present',
					'height="200"'                       => 'Height attribute should be present',
					'class="wp-image-123"'               => 'Image class should be preserved',
					'class="wp-block-image size-medium"' => 'Figure class should be preserved',
				],
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
	 * @param string $block_content The block content to process.
	 * @param string $expected      The expected output.
	 * @return void
	 */
	public function test__fix_img_block_sizes( $block_content, $expected ) {
		$files  = new A8C_Files();
		$actual = $files->fix_img_block_sizes( $block_content, array(), null );

		if ( is_array( $expected ) ) {
			foreach ( $expected as $substring => $message ) {
				static::assertStringContainsString( $substring, $actual, $message );
			}
		} else {
			static::assertEquals( $expected, $actual );
		}
	}
}
