<?php
/*
 *
 * For help with this plugin, see http://lobby.vip.wordpress.com/plugins/watermark-image-uploads/
 *
 * Plugin Name: WordPress.com Watermark Image Uploads
 * Description: Applies a watermark image of your choosing to all uploaded images.
 * Author:      Alex Mills, Automattic
 * Author URI:  http://automattic.com/
 */

class WPcom_Watermark_Uploads {

	public $settings         = array();
	public $default_settings = array();

	public $theme_watermark_relative_path;
	public $theme_wathermark_full_path;

	const OPTION_NAME = 'wpcom_watermark_image_uploads';
	const SLUG        = 'wpcom-watermark-image-uploads';

	// Class initialization
	function __construct() {
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'handle_file' ), 100 );
		add_filter( 'wp_upload_bits_data',        array( $this, 'handle_bits' ), 10, 2 ); // Requires core hack: http://core.trac.wordpress.org/ticket/12493

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		// Use this filter to change where, within your theme, the watermark is located. Must be a PNG!
		$this->theme_watermark_relative_path = apply_filters( 'wpcom_watermark_image_relative_path', 'images/upload-watermark.png' );
		$this->theme_wathermark_full_path    = STYLESHEETPATH . '/' . $this->theme_watermark_relative_path;

		add_option( self::OPTION_NAME, array() );

		$this->default_settings = array(
			'watermark_type'             => ( $this->is_enterprise() || ! $this->theme_watermark_exists() ) ? 'none' : 'theme',
			'watermark_attachment_id'    => false,
			//'watermark_location'         => 'bottomright',
			//'watermark_offset_topbottom' => 5,
			//'watermark_offset_sides'     => 5,
		);

		$this->settings = wp_parse_args( (array) get_option( self::OPTION_NAME, array() ), $this->default_settings );
	}

	public function is_enterprise() {
		return ( function_exists( 'Enterprise' ) && method_exists( Enterprise(), 'is_enabled' ) && Enterprise()->is_enabled() );
	}

	public function theme_watermark_exists() {
		return file_exists( $this->theme_wathermark_full_path );
	}

	public function register_settings() {
		// If on the settings page and watermark_type = upload but attachment doesn't exist, revert to default
		if ( ! empty( $_GET['page'] ) && self::SLUG == $_GET['page'] && 'upload' == $this->settings['watermark_type'] && ! wp_attachment_is_image( $this->settings['watermark_attachment_id'] ) ) {
			$this->settings['watermark_type'] = $this->default_settings['watermark_type'];

			$option = (array) get_option( self::OPTION_NAME, array() );
			$option['watermark_type'] = $this->settings['watermark_type'];
			update_option( self::OPTION_NAME, $option );
		}

		add_settings_section( 'wpcom-watermark-image-uploads_base', null, array( $this, 'settings_section_base_description' ), self::SLUG );
		add_settings_section( 'wpcom-watermark-image-uploads_watermark-selection', __( 'Watermark Selection', 'wpcom-watermark-image-uploads' ), array( $this, 'settings_section_watermark_selection' ), self::SLUG );

		// Don't show this next bit to Enterprise users
		if ( ! $this->is_enterprise() ) {
			add_settings_field( 'wpcom-watermark-image-uploads_watermark-selection_field-theme', $this->make_watermark_selection_title( 'From Your Theme', 'theme', ! $this->theme_watermark_exists() ), array( $this, 'settings_field_image_selection_from_theme' ), self::SLUG, 'wpcom-watermark-image-uploads_watermark-selection' );
		}

		if ( current_user_can( 'upload_files' ) ) {
			add_settings_field( 'wpcom-watermark-image-uploads_watermark-selection_field-upload', $this->make_watermark_selection_title( 'Uploaded', 'upload' ), array( $this, 'settings_field_image_selection_from_upload' ), self::SLUG, 'wpcom-watermark-image-uploads_watermark-selection' );
		}

		add_settings_field( 'wpcom-watermark-image-uploads_watermark-selection_field-none', $this->make_watermark_selection_title( 'No Watermark', 'none' ), '__return_false', self::SLUG, 'wpcom-watermark-image-uploads_watermark-selection' );

		register_setting( self::OPTION_NAME, self::OPTION_NAME, array( $this, 'setting_sanitization' ) );
	}

	public function register_admin_menu() {
		add_options_page( __( 'Watermark Image Uploads', 'wpcom-watermark-image-uploads' ), __( 'Image Watermarking', 'wpcom-watermark-image-uploads' ), 'manage_options', self::SLUG, array( $this, 'settings_page' ) );
	}

	public function settings_page() { ?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php esc_html_e( 'Watermark Image Uploads', 'wpcom-watermark-image-uploads' ); ?></h2>

			<form enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="POST">
				<?php settings_fields( self::OPTION_NAME ); ?>
				<?php do_settings_sections( self::SLUG ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
<?php
	}

	public function settings_section_base_description() {
		echo '<p>' . esc_html__( 'Here you can set the image that will be applied to all image uploads. This functionality and its settings are not retroactive &mdash; changes here will only be applied to images uploaded from this point forward.', 'wpcom-watermark-image-uploads' ) . '</p>';
	}

	public function settings_section_watermark_selection() {
		echo '<p>' . esc_html__( 'Select what image you want to use for your watermark.', 'wpcom-watermark-image-uploads' ) . '</p>';
	}

	public function make_watermark_selection_title( $title, $value, $disabled = false ) {
		return '<label><input type="radio" name="' . esc_attr( self::OPTION_NAME . '[watermark_type]' ) . '" value="' . esc_attr( $value ) . '"' . checked( $this->settings['watermark_type'], $value, false ) . disabled( $disabled, true, false ) . ' /> ' . $title . '</label>';
	}

	public function settings_field_image_selection_from_theme() {
		if ( $this->theme_watermark_exists() ) {
			echo '<p><img src="' . esc_url( get_stylesheet_directory_uri() . '/'. $this->theme_watermark_relative_path ) . '" alt="' . esc_attr__( 'Watermark', 'wpcom-watermark-image-uploads' ) . '" /></p>';

			echo '<p>' . __( "This image has been supplied by your theme's developer and is the default option.", 'wpcom-watermark-image-uploads' ) . '</p>';
		} else {
			echo '<p>' . sprintf( __( "Unable to locate an image within your theme at %s so you cannot use this default option. If you manage many sites and don't want to have to configure this plugin on each site, then have your developer commit a PNG image to this location.", 'wpcom-watermark-image-uploads' ), '<code>' . $this->theme_watermark_relative_path . '</code>' ) . '</p>';
		}
	}

	public function settings_field_image_selection_from_upload() {
		if ( $this->settings['watermark_attachment_id'] ) {
			echo wp_get_attachment_image( $this->settings['watermark_attachment_id'], 'full' );
		}

		echo '<p>';
		printf( __( 'Upload a new watermark image (must be a PNG image!): %s', 'wpcom-watermark-image-uploads' ), '<input type="file" name="' . esc_attr( self::OPTION_NAME . '_upload' ) . '" /> ' );
		submit_button( __( 'Upload', 'wpcom-watermark-image-uploads' ), 'secondary', self::OPTION_NAME . '_upload_button', false );
		echo '</p>';
	}

	public function setting_sanitization( $user_settings ) {
		$sanitized_settings = array();

		if ( ! empty( $user_settings['watermark_type'] ) && in_array( $user_settings['watermark_type'], array( 'theme', 'upload', 'none' ) ) )
			$sanitized_settings['watermark_type'] = $user_settings['watermark_type'];

		// Process image upload
		if ( ! empty( $_FILES['wpcom_watermark_image_uploads_upload'] ) && 4 != $_FILES['wpcom_watermark_image_uploads_upload']['error'] ) {
			$attachment_id = $this->process_image_upload( $_FILES['wpcom_watermark_image_uploads_upload'] );

			if ( $attachment_id ) {
				$sanitized_settings['watermark_type']   = 'upload';
				$sanitized_settings['watermark_attachment_id'] = $attachment_id;
				add_settings_error( self::OPTION_NAME, 'watermark-uploaded', 'Watermark successfully uploaded.', 'updated' );
			}
		}

		return $sanitized_settings;
	}

	public function process_image_upload( $uploaded_file ) {
		// Do a basic validation (this doesn't actually validate the file for real, just the extension)
		if ( 'image/png' !== $uploaded_file['type'] ) {
			add_settings_error( self::OPTION_NAME, 'invalid-image-type', __( 'Only PNG images can be used as a watermark image. Please upload a PNG instead.', 'wpcom-watermark-image-uploads' ) );
			return false;
		}

		// Really validate it as a PNG
		$temporary_image = imagecreatefrompng( $uploaded_file['tmp_name'] );

		if ( ! $temporary_image ) {
			add_settings_error( self::OPTION_NAME, 'invalid-png', __( 'There is an issue with your PNG image. Please verify that it is actually a PNG and then reupload it.', 'wpcom-watermark-image-uploads' ) );
			return false;
		}

		imagedestroy( $temporary_image );

		// TODO: Check to make sure the user isn't out of upload space?

		// Move the file to the proper storage location
		remove_filter( 'wp_handle_upload_prefilter', array( $this, 'handle_file' ), 100 );
		$file = wp_handle_upload( $uploaded_file, array( 'test_form' => false ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'handle_file' ), 100 );

		if ( isset( $file['error'] ) ) {
			add_settings_error( self::OPTION_NAME, 'wp_handle_upload', sprintf( __( 'An error occurred while processing the upload: %s', 'wpcom-watermark-image-uploads' ), $file['error'] ) );
			return false;
		}

		// Create an attachment for the uploaded file
		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => sprintf( __( '%s (Watermark For Uploaded Images)', 'wpcom-watermark-image-uploads' ), basename( $file['file'] ) ),
				'post_content'   => $file['url'],
				'post_mime_type' => $file['type'],
				'guid'           => $file['url'],
				'context'        => 'wpcom-watermark-image-uploads',
			),
			$file['file']
		);

		return $attachment_id;
	}

	public function get_watermark_image_path() {
		switch ( $this->settings['watermark_type'] ) {
			case 'theme':
				if ( ! $this->theme_watermark_exists() )
					return false;

				return $this->theme_wathermark_full_path;

			case 'upload':
				if ( ! wp_attachment_is_image( $this->settings['watermark_attachment_id'] ) )
					return false;

				return get_attached_file( $this->settings['watermark_attachment_id'] );
		}

		return false;
	}

	// For filters that pass a $_FILES array
	public function handle_file( $file ) {

		if ( false === apply_filters( 'wpcom_watermark_enabled', true, 'file', $file ) )
			return $file;

		// Make sure the upload is valid
		if ( 0 == $file['error'] && is_uploaded_file( $file['tmp_name'] ) ) {

			// Check file extension (can't use $file['type'] due to Flash uploader sending application/octet-stream)
			if ( ! $type = $this->get_type( $file['name'] ) ) {
				return $file;
			}

			// Load the image into $image
			switch ( $type ) {
				case 'jpeg':
					if ( ! $image = imagecreatefromjpeg( $file['tmp_name'] ) ) {
						return $file;
					}

					// Get the JPEG quality setting of the original image
					if ( $imagecontent = file_get_contents( $file['tmp_name'] ) )
						$quality = $this->get_jpeg_quality_wrapper( $imagecontent );
					if ( empty ($quality ) )
						$quality = 100;

					break;

				case 'png':
					if ( ! $image = imagecreatefrompng( $file['tmp_name'] ) ) {
						return $file;
					}
					break;

				default;
					return $file;
			}

			// Run the $image through the watermarker
			$image = $this->watermark( $image );

			// Save the new watermarked image
			switch ( $type ) {
				case 'jpeg':
					imagejpeg( $image, $file['tmp_name'], $quality );
				case 'png':
					imagepng( $image, $file['tmp_name'] );
			}

			imagedestroy( $image );
		}

		return $file;
	}

	// For filters that pass the image as a string
	public function handle_bits( $bits, $file ) {

		if ( false === apply_filters( 'wpcom_watermark_enabled', true, 'bits', $file ) )
			return $bits;

		// Check file extension
		if ( ! $type = $this->get_type( $file ) ) {
			return $bits;
		}

		// Convert the $bits into an $image
		if ( ! $image = imagecreatefromstring( $bits ) ) {
			return $bits;
		}

		// Run the $image through the watermarker
		$image = $this->watermark( $image );

		// Get the $image back into a string
		ob_start();
		switch ( $type ) {
			case 'jpeg':
				// Get the JPEG quality setting of the original image
				$quality = $this->get_jpeg_quality_wrapper( $bits );
				if ( empty($quality) )
					$quality = 100;

				if ( ! imagejpeg( $image, null, $quality ) ) {
					ob_end_clean();
					return $bits;
				}
				break;
			case 'png':
				if ( ! imagepng( $image ) ) {
					ob_end_clean();
					return $bits;
				}
				break;

			default;
				ob_end_clean();
				return $bits;
		}
		$bits = ob_get_contents();
		ob_end_clean();

		imagedestroy( $image );

		return $bits;
	}

	// Watermarks an $image
	public function watermark( $image ) {

		$watermark_path = $this->get_watermark_image_path();

		if ( ! $watermark_path )
			return $image;

		// Load the watermark into $watermark
		$watermark = imagecreatefrompng( $watermark_path );

		if ( ! $watermark )
			return $image;

		// Get the original image dimensions
		$image_width  = imagesx( $image );
		$image_height = imagesy( $image );

		// Get the watermark dimensions
		$watermark_width  = imagesx( $watermark );
		$watermark_height = imagesy( $watermark );

		// Calculate watermark location (see docs for help with these filters)
		$dest_x = (int) apply_filters( 'wpcom_watermark_uploads_destx', $image_width - $watermark_width - 5, $image_width, $watermark_width );
		$dest_y = (int) apply_filters( 'wpcom_watermark_uploads_desty', $image_height - $watermark_height - 5, $image_height, $watermark_height );

		// Copy the watermark onto the original image
		imagecopy( $image, $watermark, $dest_x, $dest_y, 0, 0, $watermark_width, $watermark_height );

		imagedestroy( $watermark );

		return $image;
	}

	// Safety wrapper for our get_jpeg_quality() function
	// See http://blog.apokalyptik.com/2009/09/16/quality-time-with-your-jpegs/
	public function get_jpeg_quality_wrapper( $imagecontent ) {

		$quality = false;

		if ( ! function_exists( 'get_jpeg_quality' ) && file_exists( WP_PLUGIN_DIR . '/wpcom-images/libjpeg.php' ) )
			include_once( WP_PLUGIN_DIR . '/wpcom-images/libjpeg.php' );

		if ( function_exists( 'get_jpeg_quality' ) )
			$quality = get_jpeg_quality( $imagecontent );

		return $quality;
	}

	// Figure out image type based on filename
	public function get_type( $filename ) {
		$wp_filetype = wp_check_filetype( $filename );
		switch ( strtolower( $wp_filetype['ext'] ) ) {
			case 'png':
				return 'png';
			case 'jpg':
			case 'jpeg':
				return 'jpeg';
			default;
				return false;
		}
	}
}

// Start this plugin once everything else is loaded up
add_action( 'init', 'WPcom_Watermark_Uploads', 5 );
function WPcom_Watermark_Uploads() {
	global $WPcom_Watermark_Uploads;
	$WPcom_Watermark_Uploads = new WPcom_Watermark_Uploads();
}

?>