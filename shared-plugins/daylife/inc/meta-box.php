<?php

class Daylife_Meta_Box {
	public static $instance;

	public function __construct() {
		self::$instance = $this;
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		add_action( 'add_meta_boxes',                array( $this, 'add_meta_box'  ) );
		add_action( 'admin_enqueue_scripts',         array( $this, 'enqueue'       ) );
		add_action( 'wp_ajax_daylife-image-search',  array( $this, 'image_search'  ) );
		add_action( 'wp_ajax_daylife-image-load',    array( $this, 'image_load'    ) );
		add_action( 'wp_ajax_daylife-gallery-search', array( $this, 'gallery_search' ) );
		add_action( 'wp_ajax_daylife-gallery-load', array( $this, 'gallery_load' ) );
		add_action( 'wp_ajax_daylife-gallery-get-images', array( $this, 'get_all_gallery_images' ) );
		add_action( 'wp_ajax_daylife-gallery-load-image', array( $this, 'sideload_gallery_image' ) );
		add_action( 'wp_ajax_daylife-gallery-shortcode', array( $this, 'insert_gallery_shortcode' ) );
	}

	public function add_meta_box() {
		$options = get_option( 'daylife', array() );
		if ( ! isset( $options[ 'post_types' ] ) )
			$options[ 'post_types' ] = array( 'post' );

		foreach ( $options[ 'post_types' ] as $post_type ) {
			add_meta_box( 'daylife-images', __( 'Daylife', 'daylife' ), array( $this, 'render_meta_box' ), $post_type, 'normal', 'high' );
			if ( $options['galleries_endpoint'] ) {
				add_meta_box( 'daylife-galleries', __( 'Daylife Galleries', 'daylife' ), array( $this, 'render_gallery_meta_box' ), $post_type, 'normal', 'high' );
			}

		}
	}

	public function render_meta_box() {
		$daylife = get_option( 'daylife' );

		$start_dates = apply_filters( 'daylife-set-start-dates', array( __( 'last month', 'daylife' ), __( '3 months ago', 'daylife' ), __( '6 months ago', 'daylife' ), __( '1 year ago', 'daylife' ), __( '3 years ago', 'daylife' ) ) );
		$end_dates = apply_filters( 'daylife-set-end-dates', array( __( 'now', 'daylife' ), __( 'last month', 'daylife' ), __( '3 months ago', 'daylife' ), __( '6 months ago', 'daylife' ), __( '1 year ago', 'daylife' ), __( '3 years ago', 'daylife' ) ) );

		if ( !$daylife['access_key'] || !$daylife['shared_secret'] || !$daylife['api_endpoint'] ) {
			echo sprintf( __( 'Please enter the API credentials for Daylife on the <a href="%s">settings screen</a>.', 'daylife' ), menu_page_url( 'daylife-options', false ) );
			return;
		}
		?>
		<label><?php _e( 'Sort by:', 'daylife' ); ?></label>
		<select name="daylife-search-by" id="daylife-search-by">
			<option value="relevance"><?php _e( 'Relevance', 'daylife' ); ?></option>
			<option value="date"><?php _e( 'Date', 'daylife' ); ?></option>
		</select>
		<label><?php _e( 'Start Date:', 'daylife' ); ?></label>
		<select name="daylife-start-date" id="daylife-start-date" class="daylife-date-filter">
			<?php 
			foreach ( $start_dates as $start_date ) {
				echo '<option value="' . esc_attr( strtotime( $start_date ) ) . '">' . esc_html( $start_date ) . '</option>';
			}
			?>
		</select>
		<label><?php _e( 'End Date:', 'daylife' ); ?></label>
		<select name="daylife-end-date" id="daylife-end-date" class="daylife-date-filter">
			<?php 
			foreach ( $end_dates as $end_date ) {
				echo '<option value="' . esc_attr( strtotime( $end_date ) ) . '">' . esc_html( $end_date ) . '</option>';
			}
			?>
		</select>
		<input type="text" id="daylife-search" name="daylife-search" size="16" value="" class="widefat"><?php
		wp_nonce_field( 'daylife-search-nonce', 'daylife-search-nonce-field' );
		wp_nonce_field( 'daylife-add-nonce', 'daylife-add-nonce-field' );
		submit_button( __( 'Search Images', 'daylife' ), 'secondary', 'daylife-search-button', false );
		submit_button( __( 'Suggest Images', 'daylife' ), 'secondary', 'daylife-suggest-button', false );
		?><div class="daylife-response" style="display: none"><img src="images/wpspin_light.gif" />Loading</div><?php
	}

	public function render_gallery_meta_box() {
		$daylife = get_option( 'daylife' );
		if ( !$daylife['access_key'] || !$daylife['shared_secret'] || !$daylife['api_endpoint'] ) {
			echo sprintf( __( 'Please enter the API credentials for Daylife on the <a href="%s">settings screen</a>.', 'daylife' ), menu_page_url( 'daylife-options', false ) );
			return;
		}
		wp_nonce_field( 'daylife-gallery-search-nonce', 'daylife-gallery-search-nonce-field' );
		wp_nonce_field( 'daylife-gallery-add-nonce', 'daylife-gallery-add-nonce-field' );
		submit_button( __( 'Search Galleries', 'daylife' ), 'secondary', 'daylife-gallery-search-button', false );
		?><div class="daylife-gallery-response" style="display: none">Loading</div><?php
	}

	public function enqueue( $hook ) {
		if ( 'post-new.php' != $hook && 'post.php' != $hook )
			return;

		wp_enqueue_script( 'daylife', plugins_url( 'daylife.js', __FILE__ ), array( 'jquery', 'media-views', 'media-models' ), '20120304', true );
		wp_enqueue_style( 'daylife', plugins_url( 'daylife.css', __FILE__ ), array(), '20120305' );
	}

	public function image_search() {
		check_ajax_referer( 'daylife-search-nonce', 'nonce' );
		require_once( dirname( __FILE__ ) . '/class-wp-daylife-api.php' );
		$daylife = new WP_Daylife_API( get_option( 'daylife' ) );
		$page = isset( $_POST['daylife_page'] ) ? absint( $_POST['daylife_page'] ) : 0;
		
		$args = array( 'offset' => 8 * $page );
		if ( isset( $_POST['end_date'] ) ) {
			$args['end_time'] = absint( $_POST['end_date'] );
		}
		if ( isset( $_POST['start_date'] ) ) {
			$args['start_time'] = absint( $_POST['start_date'] );
		}
		if ( isset( $_POST['sort'] ) && in_array( $_POST['sort'], array( 'relevance', 'date' ) ) ) {
			$args['sort'] = sanitize_text_field( $_POST['sort'] );
		}
		if ( isset( $_POST['keyword'] ) ) {
			$args['query'] = sanitize_text_field( $_POST['keyword'] );
			$images = $daylife->search_getRelatedImages( $args );
		} else {
			$args['content'] = substr( sanitize_text_field( $_POST['content'] ), 0, 1000 );
			$images = $daylife->content_getRelatedImages( $args );
		}

		$next = 8 == count( $images ) ? $page + 1 : 0;
		$prev = $page >= 1 ? $page - 1 : false;

		if ( !$images ) {
			echo __( 'No images found.', 'daylife' );
			die;
		}

		if ( $next || $prev ) {
			echo '<div class="tablenav">';
			if ( $next )
				echo '<div class="tablenav-pages"><a href="' . esc_url( '#' . $next ) . '" id="daylife-paging-next" class="next page-numbers">' . __( 'Next &raquo;', 'daylife' ) . '</a></div>';
			if ( false !== $prev )
				echo '<div class="tablenav-pages"><a href="' . esc_url( '#' . $prev ) . '" id="daylife-paging-prev" class="prev page-numbers">' . __( '&laquo; Prev', 'daylife' ) . '</a></div>';
			echo '</div>';
		}

		foreach( $images as $image ) {

			$ratio = $image->width / $image->height;
			if ( $ratio > 1 ) {
				$width = apply_filters( 'daylife-image-default-thumb', 200 );
				$height = round( $width / $ratio );
			} else {
				$height = apply_filters( 'daylife-image-default-thumb', 200 );
				$width = round( $height * $ratio );
			}

			$url = str_replace( '/45x45.jpg', '/' . absint( $width ) . 'x' . absint( $height ) . '.jpg', $image->thumb_url );
			echo '<div class="daylife-image-wrap">';
			echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $image->caption ) . '" data-thumb_url="' . esc_attr( $image->thumb_url ) . '" data-url="' . esc_attr( $image->url ) . '" data-credit="' . esc_attr( $image->credit ) . '" data-caption="' . esc_attr( $image->caption ) . '" data-daylife_url="' . esc_attr( $image->daylife_url ) . '" data-image_title="' . esc_attr( $image->image_title ) . '" data-width="' . absint( $width ) . '" data-height="' . absint( $height ) . '" />';
			echo '<div class="daylife-overlay" title="' . esc_attr( $image->caption ) . "\r\nSource: " . esc_attr( $image->source->name ) . "\r\nDate: " . esc_attr( date_i18n( get_option( 'date_format' ), $image->timestamp_epoch ) ) . '"></div>';
			echo '<button class="daylife-ste button">' . __( 'Insert into Post', 'daylife' ) . '</button>';
			echo '</div>';
		}
		echo '<div class="clear"></div>';
		die;
	}


	public function gallery_search() {

		check_ajax_referer( 'daylife-gallery-search-nonce', 'nonce' );
		require_once( dirname( __FILE__ ) . '/class-wp-daylife-api.php' );
		$daylife = new WP_Daylife_API( get_option( 'daylife' ) );
		$page = isset( $_POST['daylife_gallery_page'] ) ? absint( $_POST['daylife_gallery_page'] ) : 0;

		if ( $page > 0 ) {
			// Need to add 1 to page as page 1 is the first page 
			$args['page'] = $page + 1;
		}
		$args['sort'] = 'created';
		$images = $daylife->get_all_galleries( $args );
	
		$next = 15 == count( $images ) ? $page + 1 : 0;
		$prev = $page >= 1 ? $page - 1 : false;

		if ( !$images ) {
			echo __( 'No galleries found.', 'daylife' );
			die;
		}

		if ( $next || $prev ) {
			echo '<div class="tablenav">';
			if ( $next )
				echo '<div class="tablenav-pages gallery"><a href="' . esc_url( '#' . $next ) . '" id="daylife-gallery-paging-next" class="next page-numbers">' . __( 'Next &raquo;', 'daylife' ) . '</a></div>';
			if ( false !== $prev )
				echo '<div class="tablenav-pages gallery"><a href="' . esc_url( '#' . $prev ) . '" id="daylife-gallery-paging-prev" class="prev page-numbers">' . __( '&laquo; Prev', 'daylife' ) . '</a></div>';
			echo '</div>';
		}

		echo ' <div class="daylife-gallery-loader" style="display: none;"><span class="count">0</span>/<span class="total">0</span>&nbsp;<span class="text">image(s) loaded</span></div>';

		foreach( $images as $image ) {
			$thumb_url = $image->images[0]->thumbnail_url;
			$url = str_replace( '/45x45.jpg', '/125x125.jpg', $thumb_url );
			if ( ! empty( $url ) ) {
				echo '<div class="daylife-image-wrap">';
				echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $image->title ) . '" data-gallery-id="' . esc_attr( $image->gallery_id ) . '" data-url="' . esc_attr( $image->gallery_url ) . '" data-daylife_url="' . esc_attr( $image->gallery_url ) . '" />';
				echo '<div class="daylife-overlay" title="' . esc_attr( $image->title ) . "\r\nDate: " . esc_attr( date_i18n( get_option( 'date_format' ), $image->created_on ) ) . '"></div>';
				echo '<button class="daylife-ste button">' . __( 'Add ' . $image->editorial_images . ' images', 'daylife' ) . '</button>';
				echo '</div>';
			}
		}
		echo '<div class="clear"></div>';
		die;
	}

	/**
	 * Find all the gallery images from the Gallery API
	 */
	public function get_all_gallery_images() {

		check_ajax_referer( 'daylife-gallery-add-nonce', 'nonce' );
		require_once( dirname( __FILE__ ) . '/class-wp-daylife-api.php' );
		$daylife = new WP_Daylife_API( get_option( 'daylife' ) );

		// Should be int but santize value as id can be bigger than maximum int value
		$gallery_id = sanitize_text_field( $_POST['gallery_id'] );
		if ( $gallery_id > 0 ) {
			$args['gallery_id'] = $gallery_id;

			$images = $daylife->get_gallery_by_id( $args );

			foreach ( $images as $image ) {
				$image_data[] = $image->url . '|' . $image->caption;
			}
			echo json_encode( $image_data );
		}
		die;

	}

	/**
	 * We want to silently load the image by ajax into the post
	 */
	public function sideload_gallery_image() {
		
		check_ajax_referer( 'daylife-gallery-add-nonce', 'nonce' );
		$file_to_load = esc_url_raw( $_POST['image_url'] );
		$post_id = absint( $_POST['post_id'] );
		$desc = sanitize_text_field( $_POST['caption'] );
		if ( $file_to_load ) {
			$image = media_sideload_image( $file_to_load, $post_id, $desc );
			if ( is_wp_error( $image ) ) {
				echo 'Error transferring image';
			}
		}
		die;

	}

	/**
	 * Insert gallery shortcode into the post
	 */
	public function insert_gallery_shortcode() {
		
		check_ajax_referer( 'daylife-gallery-add-nonce', 'nonce' );
		// TODO: Update to insert ids into shortcode so it works with new media tool
		echo '[gallery]';
		die();

	}

	/**
	 * Based off media_sideload_image().  We need this functionality, but we
	 * want the ID not the HTML
	 */
	public function media_sideload_image_get_id( $file, $post_id, $desc = null ) {
		if ( ! empty($file) ) {
			// Download file to temp location
			$tmp = download_url( $file );

			// Set variables for storage
			// fix file filename for query strings
			preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $file, $matches);
			$file_array['name'] = basename($matches[0]);
			$file_array['tmp_name'] = $tmp;

			// If error storing temporarily, unlink
			if ( is_wp_error( $tmp ) ) {
				@unlink($file_array['tmp_name']);
				$file_array['tmp_name'] = '';
			}

			// do the validation and storage stuff
			$id = media_handle_sideload( $file_array, $post_id, $desc );
			// If error storing permanently, unlink
			if ( is_wp_error($id) ) {
				@unlink($file_array['tmp_name']);
			}
			return $id;
		}
		return false;
	}

	public function image_load() {
		check_ajax_referer( 'daylife-add-nonce', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			_e( "You don't have permissions to upload files.", 'daylife' );
			die;
		}
		$_POST = stripslashes_deep( $_POST );

		// Rather than making this an option we'll add a filter as it's harder to override
		$image_multiplier = apply_filters( 'daylife-height-width-multiplier', 3 );

		$width = absint( $_POST['width'] * $image_multiplier );
		$height = absint( $_POST['height'] * $image_multiplier );
		if ( ! $width || ! $height )
			$width = $height = apply_filters( 'daylife-max-height-width', 600 ) ;
		$url = str_replace( '/45x45.jpg', "/{$width}x{$height}.jpg", esc_url_raw( $_POST['thumb_url'] ) );
		$attachment_id = $this->media_sideload_image_get_id( $url, absint( $_POST['post_id'] ), sanitize_text_field( $_POST['image_title'] ) );

		// Set caption to caption + credit
		$excerpt = apply_filters( 'daylife-caption-credit', wp_kses_post( $_POST['caption'] ) . " " . wp_kses_post( $_POST['credit'] ) );

		/**
		 * If HTML captions aren't supported, strip the HTML
		 */
		if ( version_compare( $GLOBALS['wp_version'], '3.4', '<' ) )
			$excerpt = strip_tags( $excerpt );

		wp_update_post( array( 'ID' => $attachment_id, 'post_excerpt' => $excerpt ) );

		$attachment = get_post( $attachment_id, ARRAY_A );
		$attachment['image-size'] = 'full';
		$attachment['url'] = '';//$_POST['daylife_url'];
		$to_editor = image_media_send_to_editor( wp_get_attachment_url( $attachment_id ), $attachment_id, $attachment );
		echo $to_editor;
		die;
	}

	public function image_add_caption_shortcode( $shcode, $html ) {
		return preg_replace( '/ width="[\d]*" height="[\d]*"/', '', $shcode );
	}
}

new Daylife_Meta_Box;
