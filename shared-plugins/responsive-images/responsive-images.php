<?php
/**
 * Plugin Name: Responsive Images
 * Description: Load images based on screen size for fun, bandwidth savings, and profit. Based on the Lazy Load plugin.
 * Version: 0.1
 * Author: Automattic
 *
 * License: GPL2
 */

if ( ! class_exists( 'Responsive_Images' ) ) :

class Responsive_Images {

	const version = '0.1';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_scripts' ) );
		add_filter( 'the_content', array( __CLASS__, 'add_image_placeholders' ), 99 );
	}

	public static function add_scripts() {
		wp_enqueue_script( 'responsive-images',  self::get_url( 'js/responsive-images.js' ), array( 'jquery' ), self::version, true );
	}

	public static function add_image_placeholders( $content ) {
		// Don't load for feeds, previews, attachment pages, non-mobile views
		if( is_preview() || is_feed() || is_attachment() || ( function_exists( 'jetpack_is_mobile' ) && ! jetpack_is_mobile() ) )
			return $content;

		// In case you want to change the placeholder image
		$placeholder_image = apply_filters( 'responsive_images_placeholder_image', self::get_url( 'images/1x1.trans.gif' ) );

		preg_match_all( '#<img[^>]+?[\/]?>#', $content, $images, PREG_SET_ORDER );

		if ( empty( $images ) )
			return $content;

		foreach ( $images as $image ) {
			$attributes = wp_kses_hair( $image[0], array( 'http', 'https' ) );
			$new_image = '<img';
			$new_image_src = '';

			foreach ( $attributes as $attribute ) {
				$name = $attribute['name'];
				$value = $attribute['value'];

				// Remove the width and height attributes
				if ( in_array( $name, array( 'width', 'height' ) ) )
					continue;

				// Move the src to a data attribute and replace with a placeholder
				if ( 'src' == $name ) {
					$new_image_src = html_entity_decode( urldecode( $value ) );

					parse_str( parse_url( $new_image_src, PHP_URL_QUERY ), $image_args );

					$new_image_src = remove_query_arg( 'h', $new_image_src );
					$new_image_src = remove_query_arg( 'w', $new_image_src );
					$new_image .= sprintf( ' data-full-src="%s"', esc_url( $new_image_src ) );

					if ( isset( $image_args['w'] ) )
						$new_image .= sprintf( ' data-full-width="%s"', esc_attr( $image_args['w'] ) );
					if ( isset( $image_args['h'] ) )
						$new_image .= sprintf( ' data-full-height="%s"', esc_attr( $image_args['h'] ) );

					// replace actual src with our placeholder
					$value = $placeholder_image;
				}

				$new_image .= sprintf( ' %s="%s"', $name, esc_attr( $value ) );
			}
			$new_image .= '/>';
			$new_image .= sprintf( '<noscript><img src="%s" /></noscript>', $new_image_src ); // compat for no-js and better crawling

			$content = str_replace( $image[0], $new_image, $content );
		}

		return $content;
	}

	private static function get_url( $path = '' ) {
		return plugins_url( ltrim( $path, '/' ), __FILE__ );
	}
}

Responsive_Images::init();

endif;
