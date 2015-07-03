<?php
/*
Plugin name: Getty Images
Plugin URI: http://www.oomphinc.com/work/getty-images-wordpress-plugin/
Description: Integrate your site with Getty Images
Author: gettyImages
Author URI: http://gettyimages.com/
Version: 2.2.1
*/

/*  Copyright 2014  Getty Images

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/***
 ** Getty Images: The WordPress plugin!
 ***/
class Getty_Images {
	// Define and register singleton
	private static $instance = false;
	public static function instance() {
		if( !self::$instance )
			self::$instance = new Getty_Images;

		return self::$instance;
	}

	private function __clone() { }

	const capability = 'edit_posts';
	const getty_imageid_meta_key = 'getty_images_image_id';
	const getty_details_meta_key = 'getty_images_image_details';

	/**
	 * Register actions and filters
	 *
	 * @uses add_action, add_filter
	 * @return null
	 */
	private function __construct() {
		// Enqueue essential assets
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		// Add the Getty Images media button
		add_action( 'media_buttons', array( $this, 'media_buttons' ), 20 );

		// Prevent publishing posts with comp images
		add_filter( 'wp_insert_post_data', array( $this, 'wp_insert_post_data' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Create view templates used by the Getty Images media manager
		add_action( 'print_media_templates', array( $this, 'print_media_templates' ) );

		// Handle the various AJAX actions from the UI
		add_action( 'wp_ajax_getty_images_download', array( $this, 'ajax_download' ) );
		add_action( 'wp_ajax_getty_image_details', array( $this, 'ajax_image_details' ) );

		// Allow for validation of comp images
		add_filter( 'contains_getty_comp', array( $this, 'filter_contains_getty_comp' ), 0, 2 );

		// Register shorcodes
		add_action( 'init', array( $this, 'action_init' ) );
	}

  /**
   * Register shortcodes
   */
  function action_init() {
    //add_shortcode( 'getty_embed', array( $this, 'getty_embed_image' ) );
    wp_oembed_add_provider( 'http://gty.im/*', 'http://embed.gettyimages.com/oembed' );
  }

	// Convenience methods for adding 'message' data to standard
	// WP JSON responses
	function ajax_error( $message = null, $data = array() ) {
		if( !is_null( $message ) ) {
			$data['message'] = $message;
		}

		wp_send_json_error( $data );
	}

	function ajax_success( $message = null, $data = array() ) {
		if( !is_null( $message ) ) {
			$data['message'] = $message;
		}

		wp_send_json_success( $data );
	}

	/**
	 * Check against a nonce to limit exposure, all AJAX handlers must use this
	 */
	function ajax_check() {
		if( !isset( $_POST['nonce'] ) || !wp_verify_nonce( $_POST['nonce'], 'getty-images' ) ) {
			$this->ajax_error( __( "Invalid nonce", 'getty-images' ) );
		}
	}

	/**
	 * Include all of the templates used by Backbone views
	 */
	function print_media_templates() {
		include( __DIR__ . '/getty-templates.php' );
	}

	/**
	 * Enqueue all assets used for admin view. Localize scripts.
	 */
	function admin_enqueue() {
		global $pagenow;

		// Only operate on edit post pages
		if( $pagenow != 'post.php' && $pagenow != 'post-new.php' )
			return;

		// Ensure all the files required by the media manager are present
		wp_enqueue_media();

		wp_register_script( 'jquery-cookie', plugins_url( '/js/jquery.cookie.js', __FILE__ ), array( 'jquery' ), '2006', true );

		$isWPcom = self::isWPcom();

		// Determine if the Omniture Javascript should be loaded
		$load_omniture = true;
		if ( $isWPcom ) {
			$settings = isset( $_COOKIE['wpGIc'] ) ? json_decode( stripslashes( $_COOKIE['wpGIc'] ) ) : false;
			if ( isset( $settings->{'omniture-opt-in'} ) && ! $settings->{'omniture-opt-in'} ) {
				// Don't load the s_code script if the user has opted out
				$load_omniture = false;
			}
		}

		wp_enqueue_script( 'spin-js', plugins_url( '/js/spin.js', __FILE__ ), array(), 1, true );
		wp_enqueue_script( 'getty-images-filters', plugins_url( '/js/getty-filters.js', __FILE__ ), array(), 1, true );
		wp_enqueue_script( 'getty-images-views', plugins_url( '/js/getty-views.js', __FILE__ ), array( 'getty-images-filters', 'spin-js' ), 1, true );

		// Register Omniture s-code
		wp_register_script( 'getty-omniture-scode', apply_filters( 'getty_images_s_code_js_url', plugins_url( '/js/s_code.js', __FILE__ ) ), array(), 1, true );

		// Optionally load it as a dependency
		$models_depend = $load_omniture ? array( 'jquery-cookie', 'getty-omniture-scode' ) : array( 'jquery-cookie' );

		wp_enqueue_script( 'getty-images-models', plugins_url( '/js/getty-models.js', __FILE__ ), $models_depend, 1, true );
		wp_enqueue_script( 'getty-images', plugins_url( '/js/getty-images.js', __FILE__ ), array( 'getty-images-views', 'getty-images-models' ), 1, true );

		wp_enqueue_style( 'getty-images', plugins_url( '/getty-images.css', __FILE__ ) );

		// Nonce 'n' localize!
		wp_localize_script( 'getty-images-filters', 'gettyImages',
			array(
				'nonce' => wp_create_nonce( 'getty-images' ),
				'sizes' => $this->get_possible_image_sizes(),
				'isWPcom' => $isWPcom,
				'text' => array(
					// Getty Images search field placeholder
					'searchPlaceholder' => __( "Enter keywords...", 'getty-images' ),
					// Search button text
					'search' => __( "Search", 'getty-images' ),
					// 'Refine' collapsible link
					'refine' => __( "Refine", 'getty-images' ),
					// Search refinement field placeholder
					'refinePlaceholder' => __( "Search within...", 'getty-images' ),
					// Search only within these categories
					'refineCategories' => __( "Refine categories", 'getty-images' ),
					// This will be used as the default button text
					'title'  => __( "Getty Images", 'getty-images' ),
					// This will be used as the default button text
					'button' => __( "Insert Image", 'getty-images' ),

					// Downloading...
					'authorizing' => __( "Authorizing...", 'getty-images' ),
					'downloading' => __( "Downloading...", 'getty-images' ),
					'remaining' => __( "remaining", 'getty-images' ),
					'free' => __( "free", 'getty-images' ),

					// Results
					'oneResult' => __( "%d result", 'getty-images' ),
					'results' => __( "%d results", 'getty-images' ),
					'noResults' => __( "Sorry, we found zero results matching your search.", 'getty-images' ),

					// Full Sized images
					'fullSize' => __( 'Full Size', 'getty-images' ),
					'recentlyViewed' => __( "Recently Viewed", 'getty-images' ),

					// Image download
					'downloadImage' => __( "Download Image", 'getty-images' ),
					'reDownloadImage' => __( "Download Again", 'getty-images' ),

					//// Frame toolbar buttons
					'insertComp' => __( "Insert Comp into Post", 'getty-images' ),
					'embedImage' => __( "Embed Image into Post", 'getty-images' ),
					'insertImage' => __( "Insert Image into Post", 'getty-images' ),
					'selectImage' => __( "Select Image", 'getty-images' ),

					//// Filters
					'assetType' => __( "Asset Type", 'getty-images' ),
					'editorial' => __( "Editorial", 'getty-images' ),
					'creative' => __( "Creative", 'getty-images' ),

					'imageType' => __( "Image Type", 'getty-images' ),
					'photography' => __( "Photography", 'getty-images' ),
					'illustration' => __( "Illustration", 'getty-images' ),

					'orientation' => __( "Orientation", 'getty-images' ),
					'horizontal' => __( "Horizontal", 'getty-images' ),
					'vertical' => __( "Vertical", 'getty-images' ),

					'excludeNudity' => __( "Exclude Nudity?", 'getty-images' ),

					'sortOrder' => __( "Sort Order", 'getty-images' ),
					'mostPopular' => __( "Most Popular", 'getty-images' ),
					'mostRecent' => __( "Most Recent", 'getty-images' ),

					'bestMatch' => __( "Best Match", 'getty-images' ),
					'newest' => __( "Newest", 'getty-images' ),
				)
			)
		);
	}

	static function isWPcom() {
		return isset( $_GET['gettyTestWPcomIsVip'] ) || ( function_exists( 'wpcom_is_vip' ) && wpcom_is_vip() );
	}

	/**
	 * Add "Getty Images..." button to edit screen
	 *
	 * @action media_buttons
	 */
	function media_buttons( $editor_id = 'content' ) { ?>
		<a href="#" id="insert-getty-button" class="button getty-images-activate add_media"
			data-editor="<?php echo esc_attr( $editor_id ); ?>"
			title="<?php esc_attr_e( "Getty Images...", 'getty-images' ); ?>"><span class="getty-media-buttons-icon"></span><?php esc_html_e( "Getty Images...", 'getty-images' ); ?></a>
	<?php
	}

	/**
	 * Check if a string contains a comp via filter
	 *
	 * @filter contains_getty_images_comp
	 * @return bool
	 */
	function filter_contains_getty_images_comp( $contains_comp, $content ) {
		return $this->contains_comp( $content );
	}

	/**
	 * Does this string contain a Getty Images comp image?
	 * @param $post_content string
	 * @return bool
	 */
	function contains_comp( $post_content ) {
		return preg_match( '|https?://cache\d+\.asset-cache\.net|', $post_content );
	}

	/**
	 * Don't allow users to publish posts with comp images in their content
	 *
	 * @filter wp_insert_post_data
	 * @param array $postdata
	 * @param array $postarr
	 * @return array $postdata
	 */
	function wp_insert_post_data( $data, $postarr ) {
		if( $this->contains_comp( $data['post_content'] ) && $data['post_status'] == 'publish' ) {
			$data['post_status'] = 'draft';
		}

		return $data;
	}

	/**
	 * Notify the user if they tried to save an image with a comp
	 * @action admin_notices
	 */
	function admin_notices() {
		global $pagenow;

		if( $pagenow != 'post.php' || !isset( $_GET['post'] ) ) {
			return;
		}

		$post = get_post( (int) $_GET['post'] );

		if( !$post ) {
			return;
		}

		if( $this->contains_comp( $post->post_content ) ) {
			// can't use esc_html__ since it would break the HTML tags in the string to be translated.
			echo '<div class="error getty-images-message"><p>' . wp_kses_post(
				__( "<strong>WARNING</strong>: You may not publish posts with Getty Images comps. Download the image first in order to include it into your post.", 'getty-images' ) )
			. '</p></div>';
		}
	}

	/**
	 * Download an image from a URL, attach Getty MetaData which will also act
	 * as a flag that the image came from GettyImages
	 *
	 * @action wp_ajax_getty_images_download
	 */
	function ajax_download() {
		$this->ajax_check();

		if( !current_user_can( $this::capability ) ) {
			$this->ajax_error( __( "User can not download images", 'getty-images' ) );
		}

		// Sanity check inputs
		if( !isset( $_POST['url'] ) ) {
			$this->ajax_error( __( "Missing image URL", 'getty-images' ) );
		}

		$url = sanitize_url( $_POST['url'] );

		if( empty( $url ) ) {
			$this->ajax_error( __( "Invalid image URL", 'getty-images' ) );
		}

		if( !isset( $_POST['meta'] ) ) {
			$this->ajax_error( __( "Missing image meta", 'getty-images' ) );
		}

		$meta = $_POST['meta'];

		if( !is_array( $_POST['meta'] ) || !isset( $_POST['meta']['ImageId'] ) ) {
			$this->ajax_error( __( "Invalid image meta", 'getty-images' ) );
		}

		// Download the image, but don't necessarily attach it to this post.
		$tmp = download_url( $url );

		// Wah wah
		if( is_wp_error( $tmp ) ) {
			$this->ajax_error( __( "Failed to download image", 'getty-images' ) );
		}

		// Getty Images delivery URLs have the pattern:
		//
		// http://delivery.gettyimages.com/../<filename>.<ext>?TONSOFAUTHORIZATIONDATA
		//
		// Check that the URL component is correct:
		if( strpos( $url, 'http://delivery.gettyimages.com/' ) !== 0 ) {
			$this->ajax_error( "Invalid URL" );
		}

		// Figure out filename to use. by using the basename of the first image extension
		// matched component
		preg_match( '/[^?]+\.(jpe?g|jpe|gif|png)\b/i', $url, $matches );

		if( empty( $matches ) ) {
			$this->ajax_error( __( "Invalid filename", 'getty-images' ) );
		}

		$file_array['name'] = basename( $matches[0] );
		$file_array['tmp_name'] = $tmp;

		$attachment_id = media_handle_sideload( $file_array, 0 );

		if( is_wp_error( $attachment_id ) ) {
			$this->ajax_error( __( "Failed to sideload image", 'getty-images' ) );
		}

		// Set the post_content to post_excerpt for this new attachment, since
		// the field put in post_content is meant to be used as a caption for Getty
		// Images.
		//
		// We would normally use a filter like wp_insert_post_data to do this,
		// preventing an extra query, but unfortunately media_handle_sideload()
		// uses wp_insert_attachment() to insert the attachment data, and there is
		// no way to filter the data going in via that function.
		$attachment = get_post( $attachment_id );

		if( !$attachment ) {
			$this->ajax_error( __( "Attachment not found", 'getty-images' ) );
		}

    $post_parent = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		wp_update_post( array(
			'ID' => $attachment->ID,
			'post_content' => '',
      'post_excerpt' => $attachment->post_content,
      'post_parent' => $post_parent
		) );

		// Trash any existing attachment for this Getty Images image. Don't force
		// delete since posts may be using the image. Let the user force file delete explicitly.
		$getty_id = sanitize_text_field( $_POST['meta']['ImageId'] );

		$existing_image_ids = get_posts( array(
			'post_type' => 'attachment',
			'post_status' => 'any',
			'meta_key' => $this::getty_details_meta_key,
			'meta_value' => $getty_id,
			'fields' => 'ids'
		) );

		foreach( $existing_image_ids as $existing_image_id ) {
			wp_delete_post( $existing_image_id );
		}

		// Save the getty image details in post meta, but only sanitized top-level
		// string values
		update_post_meta( $attachment->ID, $this::getty_details_meta_key, array_map( 'sanitize_text_field', array_filter( $_POST['meta'], 'is_string' ) ) );

		// Save the image ID in a separate meta key for serchability
		update_post_meta( $attachment->ID, $this::getty_imageid_meta_key, sanitize_text_field( $_POST['meta']['ImageId'] ) );

		// Success! Forward new attachment_id back
		$this->ajax_success( __( "Image downloaded", 'getty-images' ), wp_prepare_attachment_for_js( $attachment_id ) );
	}

	/**
	 * Figure out potential sizes that can be used for displaying a comp
	 * in the post. Because the comp lives remotely and is never downloaded into WordPress,
	 * there's no ability to crop, so just inform JavaScript of which sizes can be used.
	 *
	 * @return array
	 */
	function get_possible_image_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = array();
		$possible_sizes = apply_filters( 'image_size_names_choose', array(
			'thumbnail' => __('Thumbnail'),
			'medium'    => __('Medium'),
			'large'     => __('Large'),
			'full'      => __('Full Size'),
		) );

		unset( $possible_sizes['full'] );

		foreach( $possible_sizes as $size => $label ) {
			$possible_sizes[$size] = array(
				'width' => get_option( $size . '_size_w' ),
				'height' => get_option( $size . '_size_h' ),
				'label' => $label
			);
		}

		return $possible_sizes;
	}

	/**
	 * Fetch details about a particular image by Getty image ID
	 *
	 * @action wp_ajax_getty_image_details
	 */
	function ajax_image_details() {
		$this->ajax_check();

		// User should only be able to read the posts DB to see these details
		if( !current_user_can( $this::capability ) ) {
			$this->ajax_error( __( "No access", 'getty-images' ) );
		}

		// Sanity check inputs
		if( !isset( $_POST['ImageId'] ) ) {
			$this->ajax_error( __( "Missing image ID", 'getty-images' ) );
		}

		$id = sanitize_text_field( $_POST['ImageId'] );

		if( empty( $id ) ) {
			$this->ajax_error( __( "Invalid image ID", 'getty-images' ) );
		}

		$posts = get_posts( array(
			'post_type' => 'attachment',
			'meta_key' => $this::getty_imageid_meta_key,
			'meta_value' => $id,
			'posts_per_page' => 1
		) );

		if( empty( $posts ) ) {
			$this->ajax_error( __( "No attachments found", 'getty-images' ) );
		}

		$this->ajax_success( __( "Got image details", 'getty-images' ), wp_prepare_attachment_for_js( $posts[0] ) );
	}
}

Getty_Images::instance();
