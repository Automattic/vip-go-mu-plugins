<?php
/**
 * Parse.ly Recommendations Block class
 *
 * @package Parsely
 * @since 3.2.0
 */

declare(strict_types=1);

namespace Parsely;

/**
 * Parse.ly Recommendations Block for the WordPress Block Editor.
 *
 * @since 3.2.0
 */
class Recommendations_Block {
	const MINIMUM_WORDPRESS_VERSION = '5.9';

	/**
	 * Determines whether the block and its assets should be registered.
	 *
	 * @since 3.2.0
	 */
	public function run(): void {
		global $wp_version;

		if ( ! isset( $wp_version ) || version_compare( $wp_version, self::MINIMUM_WORDPRESS_VERSION ) < 0 ) {
			// WordPress is not recent enough to run this block.
			return;
		}

		self::register_block();
	}

	/**
	 * Registers all block assets so that they can be enqueued through the
	 * WordPress Block Editor in the corresponding context.
	 *
	 * @since 3.3.0
	 */
	public static function register_block(): void {
		/**
		 * Register the block by passing the path to it's block.json file that
		 * contains the majority of it's definition. This file will be copied
		 * into `build/blocks/recommendations`by the build process and should be
		 * accessed there.
		 *
		 * @see https://developer.wordpress.org/reference/functions/register_block_type/
		 */
		register_block_type(
			plugin_dir_path( PARSELY_FILE ) . 'build/blocks/recommendations/',
			array(
				'render_callback' => __CLASS__ . '::render_callback',
			)
		);
	}

	/**
	 * The Server-side render_callback for the wp-parsely/recommendations block.
	 *
	 * @since 3.2.0
	 *
	 * @param array $attributes The user-controlled settings for this block.
	 * @return string
	 */
	public static function render_callback( array $attributes ): string {
		/**
		 * In block.json we define a `viewScript` that is mean to only be loaded
		 * on the front end. We need to manually enqueue this script here.
		 *
		 * The slug is automatically generated as {namespace}-{block-name}-view-script
		 */
		wp_enqueue_script( 'wp-parsely-recommendations-view-script' );
		ob_start();
		?>
<section
		<?php
		echo wp_kses_post( get_block_wrapper_attributes() );

		foreach ( $attributes as $name => $value ) {
			echo ' data-' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
		?>
></section>
		<?php
		return ob_get_clean();
	}
}
