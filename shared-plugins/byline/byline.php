<?php
/**
 * Plugin Name: Byline
 * Description: Very simple plugin to add bylines to posts
 * Version: 0.1
 * Author: Automattic
 * License: GPLv2
 */

if ( ! class_exists( 'Byline' ) ) :
 
class Byline {

	const meta_key = '_byline';
	const nonce_key = 'byline-nonce';

	static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_byline' ), 10, 2 );

		if ( apply_filters( 'byline_auto_filter_author', false ) ) // defaults to false because it doesn't work well with functions like author_posts_links
			add_filter( 'the_author', array( __CLASS__, 'filter_the_author' ) );
	}

	static function add_meta_box() {
		if ( ! post_type_supports( get_post_type(), 'author' ) )
			return;

		add_meta_box( 'byline', __( 'Byline', 'byline' ), array( __CLASS__, 'display_meta_box' ), get_post_type() );
	}

	static function display_meta_box( $post, $meta_box_data ) {
		$byline = self::get_byline( $post->ID );
		?>
		<label for="byline"><?php _e( 'Byline', 'byline' ); ?></label>
		<input type="text" name="<?php echo esc_attr( self::meta_key ); ?>" id="byline" value="<?php echo esc_attr( $byline ); ?>" />
		<?php
		wp_nonce_field( __FILE__, self::nonce_key );
	}

	static function save_byline( $post_id, $post ) {
		if ( ! isset( $_POST[ self::nonce_key ] ) || ! wp_verify_nonce( $_POST[ self::nonce_key ], __FILE__ ) )
			return;

		$byline = isset( $_POST[ self::meta_key ] ) ? sanitize_text_field( $_POST[ self::meta_key ] ) : '';

		if ( $byline )
			update_post_meta( $post_id, self::meta_key, $byline );
		else
			delete_post_meta( $post_id, self::meta_key );
	}

	static function get_byline( $post_id ) {
		return sanitize_text_field( get_post_meta( $post_id, self::meta_key, true ) );
	}

	static function filter_the_author( $display_name ) {
		global $post;
		$byline = self::get_byline( $post->ID );
		return ! empty( $byline ) ? $byline : $display_name;
	}
}

// Initialize the plugin
add_action( 'init', array( 'Byline', 'init' ), 99 ); // load late so others can hook in before us

endif;
