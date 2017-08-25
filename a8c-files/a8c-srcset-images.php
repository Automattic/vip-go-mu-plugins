<?php

class A8C_Files_Image_Srcset_Meta {

	/**
	 * Create proper 'sizes' array for the image meta including the Photon query args used on the img src.
	 *
	 * @param array  $image_meta    The image meta data as returned by 'wp_get_attachment_metadata()'.
	 * @param array  $size_array    Array of width and height values in pixels (in that order).
	 * @param string $image_src     The 'src' of the image.
	 * @param int    $attachment_id The image attachment ID or 0 if not supplied.
	 * @return array                The normalized image meta data or empty $image_meta['sizes'] on error.
	 */
	public static function generate_image_meta( $image_meta, $size_array, $image_src, $attachment_id ) {
		// Reset $image_meta['sizes'] if not empty. Need to build it again matching the current img src.
		$image_meta['sizes'] = array();

		// Bail out early if the image is not hosted on the site, missing image meta or original image is too small.
		// This will disable the generation of srcset.
		if ( strpos( $image_src, home_url() ) === false ||
			empty( $image_meta['width'] ) ||
			empty( $image_meta['height'] ) ||
			( $image_meta['width'] < 100 && $image_meta['height'] < 100 ) ) {

			return $image_meta;
		}

		$image_basename = wp_basename( $image_meta['file'] );

		// Return early if filename mismatch
		if ( strpos( wp_basename( $image_src ), $image_basename ) === false ) {
			return $image_meta;
		}

		// Need the mime type but without getting the attachment post (fast).
		if ( ! preg_match( '/\.(jpe?g|png|gif)$/i', $image_basename, $extension ) ) {
			// Something doesn't add up...
			return $image_meta;
		}

		$extension = strtolower( $extension[1] );

		if ( 'jpg' === $extension || 'jpeg' === $extension ) {
			$mime_type = 'image/jpeg';
		} else {
			$mime_type = "image/{$extension}";
		}

		$crop = false;

		// Add the Photon query args from the img src.
		// Also, when adding the (new) sizes, one of the 'file' values has to match the image src basename
		// including the query args. Otherwise wp_calculate_image_srcset() bails out and returns false.
		if ( strpos( $image_src, '?' ) ) {
			$query = explode( '?', $image_src );
			wp_parse_str( str_replace( array( '&#38;', '&#038;', '&amp;' ), '&', $query[1] ), $photon_args );

			if ( isset( $photon_args['zoom'] ) ) {
				// This should only be used by direct requests from JS/devicepx.
				// No good way to generate proper srcset for "zoomed" images.
				return $image_meta;
			}

			if ( isset( $photon_args['fit'] ) ) {
				// When there are width and height from the img tag (that translate into "w" and "h")
				// the "fit" arg is not needed. If used, it is probably a "special case" or an error.
				return $image_meta;
			}

			if ( isset( $photon_args['crop'] ) ) {
				if ( $photon_args['crop'] === '1' ) {
					$crop = true;
				} else {
					// No support for Photon cropping using pixel values or percents, yet.
					return $image_meta;
				}
			}

			if ( ! empty( $photon_args['resize'] ) ) {
				// TODO: maybe see if there is a discrepancy between the width/height from the img tag and the values in 'resize'
				// Assuming the image must have the width/height set in the 'resize' query arg.
				$resize = explode( ',', $photon_args['resize'] );
				if ( count( $resize ) === 2 ) {
					$size_array = array_map( 'intval', $resize );
				} else {
					$resize = null;
				}
			}
		}

		list( $image_width, $image_height ) = $size_array;

		if ( empty( $photon_args ) ) {
			$photon_args = array(
				'w' => $image_width,
				'h' => $image_height,
			);
		}

		$sizes = $widths_added = array();
		$_sizes = self::get_thumbnail_sizes();

		$full_width = intval( $image_meta['width'] );
		$full_height = intval( $image_meta['height'] );

		if ( ! empty( $resize ) || $crop ) {
			list( $full_width, $full_height ) = wp_expand_dimensions( $image_width, $image_height, $full_width, $full_height );
		}

		$width_2x = round( $image_width * 2 );
		$height_2x = round( $image_height * 2 );

		// Add the current size and 2x current size. That should be the largest available size.
		// The current "retina" browsers don't show images larger than 2x even on displays with 3.5x or 4.0x pixel density.
		// Prepend wpcom-current. Fixes iOS8 bug.
		$_sizes = array(
				'vipgo-current' => array(
					'width' => $image_width,
					'height' => $image_height,
				),
				'vipgo-current-2x' => array(
					'width' => $width_2x,
					'height' => $height_2x,
				),
			) + $_sizes;

		foreach ( $_sizes as $size_name => $size_data ) {
			$already_added = false;
			$_max_w = intval( $size_data['width'] );
			$_max_h = intval( $size_data['height'] );

			// Original image is too small.
			if ( ! $_max_w || ( $full_width + 1 ) < $_max_w ) {
				continue;
			}

			// Don't add default size larger than 2x the original image size (from the img tag).
			if ( ( ! $_max_w || $_max_w > $width_2x ) && ( ! $_max_h || $_max_h > $height_2x ) ) {
				continue;
			}

			list( $width, $height ) = wp_constrain_dimensions( $full_width, $full_height, $_max_w, $_max_h );

			// Don't add images with +/- 2px size difference.
			foreach ( $widths_added as $_w ) {
				if ( abs( $width - $_w ) <= 2 ) {
					$already_added = true;
					break;
				}
			}

			if ( $already_added ) {
				continue;
			}

			$widths_added[] = $width;

			$sizes[$size_name] = array(
				'file' => self::set_photon_args( $photon_args, $width, $height, $image_basename ),
				'width' => $width,
				'height' => $height,
				'mime-type' => $mime_type,
			);
		}

		$image_meta['sizes'] = $sizes;
		return $image_meta;
	}

	private static function get_thumbnail_sizes() {
		$sizes = array(
			'thumbnail' => array(
				'width' => intval( get_option( 'thumbnail_size_w' ) ),
				'height' => intval( get_option( 'thumbnail_size_h' ) ),
			),
			'medium' => array(
				'width' => intval( get_option( 'medium_size_w' ) ),
				'height' => intval( get_option( 'medium_size_h' ) ),
			),
			'medium_large' => array(
				'width' => 768,
				'height' => 0,
			),
			'large' => array(
				'width' => intval( get_option( 'large_size_w' ) ),
				'height' => intval( get_option( 'large_size_h' ) ),
			),
		);

		if ( ! $sizes['thumbnail']['width'] && ! $sizes['thumbnail']['height'] ) {
			$sizes['thumbnail']['width'] = 128;
			$sizes['thumbnail']['height'] = 96;
		}

		if ( ! $sizes['medium']['width'] && ! $sizes['medium']['height'] ) {
			$sizes['medium']['width'] = 300;
			$sizes['medium']['height'] = 300;
		}

		if ( ! $sizes['large']['width'] && ! $sizes['large']['height'] ) {
			$sizes['large']['width'] = 1024;
			$sizes['large']['height'] = 0;
		}

		return apply_filters( 'wpcom_vip_srcset_image_thumbnails', $sizes );

	}

	private static function set_photon_args( $photon_args, $width, $height, $image_basename ) {
		if ( isset( $photon_args['w'] ) ) {
			$photon_args['w'] = $width;
		}

		if ( isset( $photon_args['h'] ) ) {
			$photon_args['h'] = $height;
		}

		if ( ! empty( $photon_args['resize'] ) ) {
			$photon_args['resize'] = $width . ',' . $height;
		}

		return add_query_arg( $photon_args, $image_basename );
	}
}

// Generate/fix the image meta.
if ( defined( 'CALCULATE_IMAGE_SRCSET_META' ) && true === CALCULATE_IMAGE_SRCSET_META ) {
	add_filter( 'wp_calculate_image_srcset_meta', array( 'A8C_Files_Image_Srcset_Meta', 'generate_image_meta' ), 9, 4 );
}
