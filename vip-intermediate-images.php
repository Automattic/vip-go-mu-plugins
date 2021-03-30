<?php

/**
 * Class VIP_Intermediate_Images
 *
 * Convert intermediate images https://vip-go.com/2020/10/filename-1000x500.jpg
 * to https://vip-go.com/2020/10/filename.jpg?resize=1000,500
 *
 */
class VIP_Intermediate_Images {
	/**
	 * Allowed extensions.
	 *
	 * @var string[] Allowed extensions must match https://code.trac.wordpress.org/browser/photon/index.php?rev=507#L50
	 */
	protected static array $extensions = array(
		'gif',
		'jpg',
		'jpeg',
		'png',
	);
	/**
	 * Singleton.
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Silence is golden.
	 */
	private function __construct() {
	}

	/**
	 * Singleton implementation
	 *
	 * @return VIP_Intermediate_Images
	 */
	public static function instance(): VIP_Intermediate_Images {
		if ( ! is_a( self::$instance, 'VIP_Intermediate_Images' ) ) {
			self::$instance = new VIP_Intermediate_Images();
		}

		return self::$instance;
	}

	/**
	 * Set up hooks
	 *
	 * @return null
	 */
	public function setup() {
		add_filter( 'the_content', array( __CLASS__, 'filter_the_content' ), 999999 );
		return null;
	}

	/**
	 * Identify images in post content, convert all intermediate sizes to using parameter 'resize'
	 *
	 * @param  string  $content  HTML content.
	 *
	 * @return string
	 */
	public static function filter_the_content( string $content ): string {
		$images = self::parse_images_from_html( $content );

		if ( empty( $images ) ) {
			return $content;
		}

		foreach ( $images['img_url'] as $index => $src ) {

			if ( ! self::is_valid_image_url( $src ) ) {
				continue;
			}

			$new_src = self::convert_dimensions_from_filename( $src );

			if ( $src === $new_src ) {
				continue;
			}

			$content = str_replace( $src, $new_src, $content );
		}

		return $content;
	}

	/**
	 * Match all images and any relevant <a> tags in a block of HTML.
	 *
	 * @param  string  $content  HTML content.
	 *
	 * @return array An array of $images matches including two arrays where:
	 *                  - $images['img_tag'] is an array of full matches.
	 *                  - $images['img_url'] is an array of all image src values.
	 */
	public static function parse_images_from_html( string $content ): array {
		$images = array();

		if ( preg_match_all( '#(?P<img_tag><(?:img|amp-img|amp-anim)[^>]*?\s+?src=["|\'](?P<img_url>[^\s]+?)["|\'].*?>)#is',
			$content, $images ) ) {
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible.
				if ( is_numeric( $key ) ) {
					unset( $images[ $key ] );
				}
			}

			return $images;
		}

		return array();
	}

	/**
	 * Check if an image URL is valid to process. It's valid if:
	 *  - file type in the allowed list
	 *  - domain is one of these values: home, siteurl, go-vip.co, or go-vip.net
	 *
	 * @param  string  $image_url
	 *
	 * @return bool
	 */
	public static function is_valid_image_url( string $image_url ): bool {
		$type = wp_check_filetype( $image_url );
		if ( ! in_array( $type['ext'], self::$extensions ) ) {
			return false;
		}

		$home_url = home_url();
		$site_url = site_url();

		$image_url_parsed = wp_parse_url( $image_url );
		$home_url_parsed  = wp_parse_url( $home_url );
		$site_url_parsed  = wp_parse_url( $site_url );

		if ( $image_url_parsed['host'] === $home_url_parsed['host'] ) {
			return true;
		}

		if ( $image_url_parsed['host'] === $site_url_parsed['host'] ) {
			return true;
		}

		if ( wp_endswith( $image_url_parsed['host'], '.go-vip.co' )
		     || wp_endswith( $image_url_parsed['host'], '.go-vip.net' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Convert dimensions in filenames to resize parameter
	 *
	 * @param  string  $url
	 *
	 * @return string
	 */
	public static function convert_dimensions_from_filename( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! $path ) {
			return $url;
		}

		// Look for images ending with something like `-100x100.jpg`.
		// We include the period in the dimensions match to avoid double string replacing when the file looks something like `image-100x100-100x100.png` (we only catch the latter dimensions).
		$matched = preg_match( '#(-\d+x\d+\.)(jpg|jpeg|png|gif)$#i', $path, $parts );

		if ( $matched ) {
			// Strip off the dimensions and return the image
			$updated_url = str_replace( $parts[1], '.', $url );

			// Add the resize parameter
			$resize_value = str_replace( 'x', ',', substr( $parts[1], 1, - 1 ) );
			$updated_url  = add_query_arg( 'resize', $resize_value, $updated_url );

			return $updated_url;
		}

		return $url;
	}

}

// Run this instance
if ( defined( 'VIP_INTERMEDIATE_IMAGES_FIX' ) && true === VIP_INTERMEDIATE_IMAGES_FIX ) {
	VIP_Intermediate_Images::instance()->setup();
}