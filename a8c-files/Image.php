<?php

namespace A8C_Files;

/**
 * Class Image
 *
 * Represents a resizable image, exposing properties necessary for properly generating srcset.
 *
 * @package A8C_Files
 */
class Image {

	/** @var int $attachment_id Attachment post ID. */
	public $attachment_id;

	/** @var string $filename Attachment's Filename. */
	public $filename;

	/** @var array $data Attachment metadata. */
	private $data;

	/** @var string $mime_type Attachment's mime-type. */
	private $mime_type;

	/** @var int $width Current attachment's width. */
	private $width;

	/** @var int $height Current attachment's height. */
	private $height;

	/** @var bool $is_resized Whether the attachment has been resized yet, or not. */
	private $is_resized = false;

	public function __construct( $data, $attachment_id = null ) {
		$this->filename = $data['file'];
		$this->width = $data['width'];
		$this->height = $data['height'];
		$this->attachment_id = $attachment_id;
		$this->mime_type = $this->get_attachment_mime_type();
		$this->data = $data;
	}

	/**
	 * Handles the image resize.
	 *
	 * @param array $size_data Array of width, height, and crop properties of a size.
	 *
	 * @return bool|\WP_Error True if resize was successful, WP_Error on failure.
	 */
	public function resize( $size_data ) {

		$dimensions = $this->image_resize_dimensions( $size_data['width'], $size_data['height'], $size_data['crop'] );

		if ( true === is_wp_error( $dimensions ) ) {
			return $dimensions; // Returns \WP_Error.
		}

		$this->mime_type = $this->get_attachment_mime_type();
		if ( true === is_wp_error( $this->mime_type ) ) {
			return $this->mime_type; // Returns \WP_Error.
		}

		$this->set_width_height( $dimensions );

		return $this->is_resized = true;
	}

	/**
	 * Return the basename filename. If the image has been resized, including
	 * the resizing params for VIP Go File Service.
	 *
	 * @return string Basename of the filename.
	 */
	public function get_filename() {

		if ( true === $this->is_resized() ) {
			$filename = $this->get_resized_filename();
		} else {
			$filename = $this->filename;
		}

		return wp_basename( $filename );
	}

	/**
	 * Returns current image width. Either original, or after resize.
	 *
	 * @return int
	 */
	public function get_width() {
		return (int) $this->width;
	}

	/**
	 * Returns current image height. Either original, or after resize.
	 *
	 * @return int
	 */
	public function get_height() {
		return (int) $this->height;
	}

	/**
	 * Returns image mime type.
	 *
	 * @return string|\WP_Error Image's mime type or WP_Error if it was not determined.
	 */
	public function get_mime_type() {
		return $this->mime_type;
	}

	/**
	 * Checks the resize status of the image.
	 *
	 * @return bool If the image has been resized.
	 */
	public function is_resized() {
		return ( true === $this->is_resized );
	}

	/**
	 * Get filename with proper args for the VIP Go File Service.
	 *
	 * @return string Filename with query args for VIP Go File Service
	 */
	protected function get_resized_filename() {
		$query_args = [
			'resize' => join( ',', array_map( 'rawurlencode', [
				$this->width,
				$this->height,
			] ) )
		];

		return add_query_arg( $query_args, $this->filename );
	}

	/**
	 * Get post mime type.
	 *
	 * Since the attachment had been already fetched from database,
	 * using the get_post_mime_type function should be efficient enough.
	 *
	 * @return string|\WP_Error Attachment mime type or WP_Error on failure.
	 */
	public function get_attachment_mime_type() {

		if ( null === $this->attachment_id ) {
			return new \WP_Error( 'error_getting_mimetype', esc_html__( 'Could not determine mime type from attachment post. Missing attachment ID' ), $this->filename );
		}

		$mime_type = get_post_mime_type( $this->attachment_id );
		if ( false === $mime_type ) {
			return new \WP_Error( 'error_getting_mimetype', esc_html__( 'Could not determine mime type from attachment post.' ), $this->filename );
		}

		return $mime_type;
	}

	/**
	 * Get resize dimensions used for the VIP Go File service.
	 *
	 * Converts the list of values returned from `image_resize_dimensions()` to
	 * associative array for the sake of more readable code no relying on index
	 * nor `list`.
	 *
	 * @param int $max_width
	 * @param int $max_height
	 * @param bool|array $crop
	 *
	 * @return array|\WP_Error Array of dimensions matching the parameters to imagecopyresampled. WP_Error on failure.
	 */
	protected function image_resize_dimensions( $max_width, $max_height, $crop ) {
		// Uses original width and height stored in $this->data.
		$dimensions = image_resize_dimensions( $this->data['width'], $this->data['height'], $max_width, $max_height, $crop );
		if ( ! $dimensions ) {
			return new \WP_Error( 'error_getting_dimensions', esc_html__( 'Could not calculate resized image dimensions' ), $this->filename );
		}

		return array_combine( [
			'dst_x',
			'dst_y',
			'src_x',
			'src_y',
			'dst_w',
			'dst_h',
			'src_w',
			'src_h'
		], $dimensions );
	}

	/**
	 * Sets proper width and height from dimensions.
	 *
	 * @return void
	 */
	protected function set_width_height( $dimensions ) {
		$this->width = $dimensions['dst_w'];
		$this->height = $dimensions['dst_h'];
	}

}