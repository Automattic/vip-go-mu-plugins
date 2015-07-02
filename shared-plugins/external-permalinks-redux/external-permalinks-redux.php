<?php
/*
Plugin Name: External Permalinks Redux
Plugin URI: http://www.thinkoomph.com/plugins-modules/external-permalinks-redux/
Description: Allows users to point WordPress objects (posts, pages, custom post types) to a URL of your choosing. Inspired by and backwards-compatible with <a href="http://txfx.net/wordpress-plugins/page-links-to/">Page Links To</a> by Mark Jaquith. Written for use on WordPress.com VIP.
Version: 1.1
Author: Erick Hitter & Oomph, Inc.
Author URI: http://www.thinkoomph.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class external_permalinks_redux {
	/**
	 * Class variables
	 */
	protected static $instance;

	var $meta_key_target = '_links_to';
	var $meta_key_type = '_links_to_type';
	var $status_codes;

	/**
	 * Instantiate class as a singleton
	 *
	 * @return object
	 */
	static function get_instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Register actions and filters
	 *
	 * @uses add_action
	 * @uses add_filter
	 * @return null
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'action_init' ), 0 ); // other init actions may rely on permalinks so filter early
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'save_post', array( $this, 'action_save_post' ) );

		add_filter( 'post_link', array( $this, 'filter_post_permalink' ), 1, 2 );
		add_filter( 'post_type_link', array( $this, 'filter_post_permalink' ), 1, 2 );
		add_filter( 'page_link', array( $this, 'filter_page_link' ), 1, 2 );
		add_action( 'wp', array( $this, 'action_wp' ) );
	}

	/**
	 * Register plugin keys and status codes
	 *
	 * @uses apply_filters
	 * @action init
	 * @return null
	 */
	function action_init() {
		$this->meta_key_target = apply_filters( 'epr_meta_key_target', $this->meta_key_target );
		$this->meta_key_type = apply_filters( 'epr_meta_key_type', $this->meta_key_type );

		$status_codes = array(
			302 => __( 'Temporary (302)', 'external-permalinks-redux' ),
			301 => __( 'Permanent (301)', 'external-permalinks-redux' ),
		);
		$this->status_codes = apply_filters( 'epr_status_codes', $status_codes );
	}

	/**
	 * Add meta box
	 *
	 * @uses apply_filters
	 * @uses __
	 * @uses add_meta_box
	 * @action admin_init
	 * @return null
	 */
	function action_admin_init() {
		$post_types = apply_filters( 'epr_post_types', array( 'post', 'page' ) );

		if ( ! is_array( $post_types ) )
			return;

		foreach( $post_types as $post_type ) {
			$title  = apply_filters( 'epr_metabox_title', '', $post_type );

			if ( ! $title )
				$title = __( 'External Permalinks Redux', 'external-permalinks-redux' );

			add_meta_box( 'external-permalinks-redux', $title, array( $this, 'meta_box' ), $post_type, 'normal' );

			unset( $title );
		}
	}


	/**
	 * Render meta box
	 *
	 * @param object $post
	 * @uses get_post_meta
	 * @uses _e
	 * @uses esc_attr
	 * @uses esc_url
	 * @uses selected
	 * @uses wp_nonce_field
	 * @return string
	 */
	function meta_box( $post ) {
		$type = get_post_meta( $post->ID, $this->meta_key_type, true );
	?>
		<p class="epr-destination">
			<label for="epr-url"><?php _e( 'Destination Address:', 'external-permalinks-redux' ); ?></label><br />
			<input name="<?php echo esc_attr( $this->meta_key_target ); ?>_url" class="large-text code" id="epr-url" type="text" value="<?php echo esc_url( get_post_meta( $post->ID, $this->meta_key_target, true ) ); ?>" />
		</p>

		<p class="description"><?php _e( 'To restore the original permalink, remove the link entered above.', 'external-permalinks-redux' ); ?></p>

		<p class="epr-separator">&nbsp;</p>

		<p class="epr-redirect-type">
			<label for="epr-type"><?php _e( 'Redirect Type:', 'external-permalinks-redux' ); ?></label>
			<select name="<?php echo esc_attr( $this->meta_key_target ); ?>_type" id="epr-type">
				<option value=""><?php _e( '-- Select --', 'external-permalinks-redux' ); ?></option>
				<?php foreach ( $this->status_codes as $status_code => $explanation ) {
					echo '<option value="' . esc_attr( $status_code ) . '"';
					selected( $status_code, intval( $type ) );
					echo '>' . esc_attr( $explanation ) . '</option>';
				} ?>
			</select>
		</p>

	<?php
		wp_nonce_field( 'external-permalinks-redux', $this->meta_key_target . '_nonce', false );
	}

	/**
	 * Save meta box input
	 *
	 * @param int $post_id
	 * @uses wp_verify_nonce
	 * @uses esc_url_raw
	 * @uses update_post_meta
	 * @uses delete_post_meta
	 * @action save_post
	 * @return null
	 */
	function action_save_post( $post_id ) {
		if ( isset( $_POST[ $this->meta_key_target . '_nonce' ] ) && wp_verify_nonce( $_POST[ $this->meta_key_target . '_nonce' ], 'external-permalinks-redux' ) ) {
			//Target
			$url = esc_url_raw( $_POST[ $this->meta_key_target . '_url' ] );

			if ( ! empty( $url ) )
				update_post_meta( $post_id, $this->meta_key_target, $url );
			else
				delete_post_meta( $post_id, $this->meta_key_target, $url );

			//Redirect type
			$type = intval( $_POST[ $this->meta_key_target . '_type' ] );

			if ( ! empty( $url ) && array_key_exists( $type, $this->status_codes ) )
				update_post_meta( $post_id, $this->meta_key_type, $type );
			else
				delete_post_meta( $post_id, $this->meta_key_type );
		}
	}

	/**
	 * Filter post and custom post type permalinks
	 *
	 * @param string $permalink
	 * @param object $post
	 * @uses get_post_meta
	 * @filter post_link
	 * @uses post_type_link
	 * @return string
	 */
	function filter_post_permalink( $permalink, $post ) {
		if ( $external_link = get_post_meta( $post->ID, $this->meta_key_target, true ) )
			$permalink = $external_link;

		return $permalink;
	}

	/**
	 * Filter page permalinks
	 *
	 * @param string $link
	 * @param int $id
	 * @uses get_post_meta
	 * @filter page_link
	 * @return string
	 */
	function filter_page_link( $link, $id ) {
		if ( $external_link = get_post_meta( $id, $this->meta_key_target, true ) )
			$link = $external_link;

		return $link;
	}

	/**
	 * Redirect to external link if object requested directly.
	 *
	 * @global $post
	 * @uses is_singular
	 * @uses get_post_meta
	 * @uses apply_filters
	 * @uses wp_redirect
	 * @action wp
	 * @return null
	 */
	function action_wp() {
		global $post;

		if ( is_singular() && ( $link = get_post_meta( $post->ID, $this->meta_key_target, true ) ) ) {
			$type = intval( get_post_meta( $post->ID, $this->meta_key_type, true ) );
			$type = apply_filters( 'epr_status_code', $type, $link, $post );

			if ( ! $type )
				$type = 302;

			wp_redirect( $link, $type );
			exit;
		}
	}
}

// Initialize the plugin if it hasn't already
external_permalinks_redux::get_instance();

/**
 * Wrapper for meta box function
 * Can be used as an alternative to the epr_post_types filter found in the plugin classes's action_admin_init function.
 *
 * @param object $post
 * @uses external_permalinks_redux
 * @return string
 */
function external_permalinks_redux_meta_box( $post ) {
	external_permalinks_redux::get_instance()->meta_box( $post );
}
