<?php
/**
 * Plugin Name: Art Direction Redux
 * Plugin URI:
 * Description: Per-post styles for new age art direction. Based on the original <a href="http://wordpress.org/extend/plugins/art-direction/">Art Direction</a> plugin by No&#235;l Jackson.
 * Author: Automattic
 * Version: 1.0
 * License: GPLv2+
 *
 * Changes from Art Direction:
 *   -- Caching-friendly
 *   -- Removes "global" option; only "single" styles.
 *   -- code cleanup
 *   -- CSS sanitization and cleaning using CSSTidy from Custom CSS (if available)
 */

if ( ! class_exists( 'Art_Direction_Redux' ) ) :

class Art_Direction_Redux {

	const META_KEY = 'art_direction_single';

	static function init() {
		add_post_type_support( 'post', 'art-direction' );
		add_post_type_support( 'page', 'art-direction' );

		add_action( 'wp_head', array( __CLASS__, 'output_styles' ) );

		add_action( 'save_post', array( __CLASS__, 'save_postdata' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
	}

	static function output_styles() {
		if ( ! is_singular() )
			return;

		$post_id = get_queried_object_id();

		$custom_css_file = locate_template( array(
			sprintf( 'art-direction/style-%s.css', $post_id ),
			sprintf( 'css/style-%s.css', $post_id ),
			sprintf( 'style-%s.css', $post_id ),
		) );

		if ( file_exists( $custom_css_file ) ) {
			wp_enqueue_style( 'art-direction-css-' . $post_id, $custom_css_file );
		}

		$custom_css_meta = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! empty( $custom_css_meta ) ) {
			printf( '<style>%s</style>', PHP_EOL . esc_html( $custom_css_meta ) . PHP_EOL );
		}
	}

	static function save_postdata( $post_id ) {
		if ( ! isset( $_POST['art-direction-nonce'] ) || ! wp_verify_nonce( $_POST['art-direction-nonce'], plugin_basename(__FILE__) ) )
			return;

		if( ! empty( $_POST['art-direction-css'] ) ) {
			$css = self::sanitize_css( $_POST['art-direction-css'] );
			update_post_meta( $post_id, self::META_KEY, $css );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	static function add_meta_box() {
		$post_type = get_post_type();
		if ( post_type_supports( $post_type, 'art-direction' ) )
			add_meta_box( 'art-direction-redux', __( 'Art Direction Redux', 'art-direction-redux' ), array( __CLASS__, 'display_meta_box' ), $post_type, 'normal' );
	}

	static function display_meta_box() {
		global $post;
		$custom_css = get_post_meta( $post->ID,'art_direction_single', true );
		?>
		<div class="art-direction-single">
			<p><label for="art-direction-css"><?php _e( 'Add custom CSS styles for this post in the textarea below:', 'art-direction-redux' ); ?></label></p>
			<textarea id="art-direction-css" name="art-direction-css" style="width: 98%; height: 300px;"><?php echo esc_textarea( $custom_css ); ?></textarea>
		</div>
		<?php
		wp_nonce_field( plugin_basename( __FILE__ ), 'art-direction-nonce', false );
	}

	static function sanitize_css( $css ) {
		$css = stripslashes( $css );
		$css = wp_strip_all_tags( $css );

		if ( function_exists( 'safecss_class' ) ) {
			// Stolen from the Custom CSS plugin. Sanitize and clean using CSS tidy if available.
			safecss_class();
			$csstidy = new csstidy();
			$csstidy->optimise = new safecss($csstidy);
			$csstidy->set_cfg('remove_bslash', false);
			$csstidy->set_cfg('compress_colors', false);
			$csstidy->set_cfg('compress_font-weight', false);
			$csstidy->set_cfg('discard_invalid_properties', true);
			$csstidy->set_cfg('merge_selectors', false);
			$csstidy->set_cfg('remove_last_;', false);
			$csstidy->set_cfg('css_level', 'CSS3.0');

			$css = preg_replace('/\\\\([0-9a-fA-F]{4})/', '\\\\\\\\$1', $css);

			$csstidy->parse($css);
			$css = $csstidy->print->plain();
		}

		return $css;
	}
}

add_action( 'init', array( 'Art_Direction_Redux', 'init' ) );

endif;
