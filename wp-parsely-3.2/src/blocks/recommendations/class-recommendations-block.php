<?php
/**
 * Parse.ly Recommendations Block class file.
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
	const MINIMUM_WORDPRESS_VERSION = '5.6';

	/**
	 * Determine whether the block and its assets should be registered.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public function run(): void {
		global $wp_version;

		if ( ! apply_filters( 'wp_parsely_recommendations_block_enabled', false ) ) {
			// This block is behind a "feature flag" and it's not enabled. Bail.
			return;
		}

		if ( ! isset( $wp_version ) || version_compare( $wp_version, self::MINIMUM_WORDPRESS_VERSION ) < 0 ) {
			// WordPress is not recent enough to run this block.
			return;
		}

		self::register_block_and_assets();
	}

	/**
	 * Registers all block assets so that they can be enqueued through the
	 * WordPress Block Editor in the corresponding context.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public static function register_block_and_assets(): void {
		// Temporary workaround - don't register block when in FSE due to issues.
		global $pagenow;
		if ( 'site-editor.php' === $pagenow ) {
			return;
		}

		$plugin_url = plugin_dir_url( PARSELY_FILE );

		$editor_asset_file = require plugin_dir_path( PARSELY_FILE ) . 'build/recommendations-edit.asset.php';
		wp_register_script(
			'wp-parsely-recommendations-block-editor',
			$plugin_url . 'build/recommendations-edit.js',
			$editor_asset_file['dependencies'],
			$editor_asset_file['version'],
			true
		);

		$script_asset_file = require plugin_dir_path( PARSELY_FILE ) . 'build/recommendations.asset.php';
		wp_register_script(
			'wp-parsely-recommendations-block',
			$plugin_url . 'build/recommendations.js',
			$script_asset_file['dependencies'],
			$script_asset_file['version'],
			true
		);

		wp_register_style(
			'wp-parsely-recommendations-block',
			$plugin_url . 'build/style-recommendations-edit.css',
			array(),
			$script_asset_file['version']
		);

		register_block_type( 'wp-parsely/recommendations', self::get_block_registration_args() );
	}

	/**
	 * Return the Block's registration arguments.
	 *
	 * @since 3.2.0
	 *
	 * @return array<string, mixed>
	 */
	private static function get_block_registration_args(): array {
		return array(
			'editor_script'   => 'wp-parsely-recommendations-block-editor',
			'render_callback' => __CLASS__ . '::render_callback',
			'script'          => 'wp-parsely-recommendations-block',
			'style'           => 'wp-parsely-recommendations-block',
			'supports'        => array(
				'html' => false,
			),
			'attributes'      => array(
				'boost'      => array(
					'type'    => 'string',
					'default' => 'views',
				),
				'imagestyle' => array(
					'type'    => 'string',
					'default' => 'original',
				),
				'limit'      => array(
					'type'    => 'number',
					'default' => 3,
				),
				'showimages' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'sort'       => array(
					'type'    => 'string',
					'default' => 'score',
				),
				'tag'        => array(
					'type' => 'string',
				),
				'title'      => array(
					'type'    => 'string',
					'default' => __( 'Related Content', 'wp-parsely' ),
				),
			),
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
		ob_start();
		?>
<section
		<?php
		echo wp_kses_post( get_block_wrapper_attributes() );

		// Remove any attributes that don't need to be in output.
		unset( $attributes['tag'] );

		foreach ( $attributes as $name => $value ) {
			echo ' data-' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
		?>
></section>
		<?php
		return ob_get_clean();
	}
}
