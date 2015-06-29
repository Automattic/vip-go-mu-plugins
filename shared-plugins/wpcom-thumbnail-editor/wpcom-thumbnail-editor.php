<?php /*

**************************************************************************

Plugin Name:  WordPress.com Thumbnail Editor
Description:  Since thumbnails are generated on-demand on WordPress.com, thumbnail cropping location must be set via the URL. This plugin assists in doing this. Based on concepts by Imran Nathani of <a href="http://metronews.ca/">Metro News Canada</a>.
Author:       Automattic
Author URI:   http://vip.wordpress.com/

**************************************************************************/


class WPcom_Thumbnail_Editor {

	/**
	 * Post meta key name, for storing crop coordinates.
	 *
	 * @var string
	 */
	public $post_meta = 'wpcom_thumbnail_edit';

	/**
	 * Determine if we want to use a dimension map or not
	 */
	public $use_ratio_map = false;

	/**
	 * An array that maps specific aspect ratios to image size strings. Should be defined by user to be accurate.
	 */
	public $image_ratio_map = array();

	/**
	 * Initialize the class by registering various hooks.
	 */
	function __construct() {

		$args = apply_filters( 'wpcom_thumbnail_editor_args', array(
			'image_ratio_map' => false,
		) );

		// When a thumbnail is requested, intercept the request and return the custom thumbnail
		if ( ! function_exists( 'is_private_blog' ) || ( function_exists( 'is_private_blog' ) && ! is_private_blog() ) )
			add_filter( 'image_downsize', array( &$this, 'get_thumbnail_url' ), 15, 3 );

		// Admin-only hooks
		if ( is_admin() ) {

			// Add a new field to the edit attachment screen
			add_filter( 'attachment_fields_to_edit', array( &$this, 'add_attachment_fields_to_edit' ), 50, 2 );

			// Create a new screen for editing a thumbnail
			add_action( 'admin_action_wpcom_thumbnail_edit', array( &$this, 'edit_thumbnail_screen' ) );

			// Handle the form submit of the edit thumbnail screen
			add_action( 'admin_post_wpcom_thumbnail_edit', array( &$this, 'post_handler' ) );

			// Display status messages
			if ( ! empty( $_GET['wtereset'] ) || ! empty( $_GET['wteupdated'] ) )
				add_action( 'admin_notices', array( &$this, 'output_thumbnail_message' ) );

			add_action( 'admin_notices', array( &$this, 'jetpack_photon_url_message' ) );
		}

		// using a global for now, maybe these values could be set in constructor in future?
		if( is_array( $args['image_ratio_map'] ) ) {
			$this->use_ratio_map = true;

			// Validate image sizes
			global $_wp_additional_image_sizes;
			foreach ( $args['image_ratio_map'] as $ratio => $image_sizes ) {
				$ratio_map[ $ratio ] = array();

				foreach ( $image_sizes as $image_size ) {
					if ( array_key_exists( $image_size, $_wp_additional_image_sizes ) )
						$ratio_map[ $ratio ][] = $image_size;
				}

				if ( empty( $ratio_map[ $ratio ] ) )
					unset( $ratio_map[ $ratio ] );
			}
			$this->image_ratio_map = $ratio_map;
		}
	}

	/**
	 * Outputs status messages based on query parameters.
	 *
	 * It cheats a little and uses the settings error API in order to avoid having to generate it's own HTML.
	 */
	public function output_thumbnail_message() {
		if ( ! empty( $_GET['wtereset'] ) )
			add_settings_error( 'wpcom_thumbnail_edit', 'reset', __( 'Thumbnail position reset.', 'wpcom-thumbnail-editor' ), 'updated' );
		elseif ( ! empty( $_GET['wteupdated'] ) )
			add_settings_error( 'wpcom_thumbnail_edit', 'updated', __( 'Thumbnail position updated.', 'wpcom-thumbnail-editor' ), 'updated' );
		else
			return;

		settings_errors( 'wpcom_thumbnail_edit' );
	}

	/**
	 * Display a message if JetPack isn't enabled (specifically, jetpack_photon_url is not defined.)
	 */
	function jetpack_photon_url_message() {
		if( function_exists( 'jetpack_photon_url' ) )
			return;

		echo '<div class="error"><p>' . __( 'Jetpack is not enabled, which will disable some features of the WordPress.com Thumbnail Editor module. Please enable JetPack to make this module fully functional.', 'wpcom-thumbnail-editor' ) . '</p></div>';

		settings_errors( 'wpcom_thumbnail_edit' );
	}

	/**
	 * Adds a new field to the edit attachment screen that lists thumbnail sizes.
	 *
	 * @param $form_fields array Existing fields.
	 * @param $attachment object The attachment currently being edited.
	 * @return array Form fields, either unmodified on error or new field added on success.
	 */
	public function add_attachment_fields_to_edit( $form_fields, $attachment ) {
		if ( ! wp_attachment_is_image( $attachment->ID ) )
			return $form_fields;

		$form_fields['wpcom_thumbnails'] = array(
			'label' => 'Thumbnail Images',
			'input' => 'html',
			'html'  => $this->get_attachment_field_html( $attachment ),
		);

		return $form_fields;
	}

	/**
	 * Generates the HTML for the edit attachment field.
	 *
	 * @param $attachment object The attachment currently being edited.
	 * @return string The HTML for the form field.
	 */
	public function get_attachment_field_html( $attachment ) {

		$sizes = $this->use_ratio_map ? $this->get_image_sizes_by_ratio() : $this->get_intermediate_image_sizes();

		if ( empty( $sizes ) )
			return '<p>' . __( 'No thumbnail sizes could be found that are cropped. For now this functionality only supports cropped thumbnails.', 'wpcom-thumbnail-editor' ) . '</p>';

		// Photon has to be able to access the source images
		if ( function_exists( 'is_private_blog' ) && is_private_blog() ) {
			return '<p>' . sprintf( __( "The WordPress.com VIP custom thumbnail cropping functionality doesn't work on sites <a href='%s'>marked as private</a>.", 'wpcom-thumbnail-editor' ), admin_url( 'options-reading.php' ) ) . '</p>';
		} elseif ( 'localhost' == $_SERVER['HTTP_HOST'] ) {
			return '<p>' . __( "The WordPress.com VIP custom thumbnail cropping functionality needs the images be publicly accessible in order to work, which isn't possible when you're developing locally.", 'wpcom-thumbnail-editor' ) . '</p>';
		}

		$html = '<p class="hide-if-js">' . __( 'You need to enable Javascript to use this functionality.', 'wpcom-thumbnail-editor' ) . '</p>';

		$html .= '<input type="button" class="hide-if-no-js button" onclick="jQuery(this).hide();jQuery(\'#' . esc_js( 'wpcom-thumbs-' . $attachment->ID ) . '\').slideDown(\'slow\');" value="' . __( 'Show Thumbnails', 'wpcom-thumbnail-editor' ) . '" />';

		$html .= '<div id="' . esc_attr( 'wpcom-thumbs-' . $attachment->ID ) . '" class="hidden">';

			$html .= '<p>' . __( 'Click on a thumbnail image to modify it. Each thumbnail has likely been scaled down in order to fit nicely into a grid.<br /><strong>Only thumbnails that are cropped are shown.</strong> Other sizes are hidden because they will be scaled to fit.', 'wpcom-thumbnail-editor' ) . '</p>';

			$html .= '<div>';

			// key wont really matter if its not using a dimension map
			foreach ( $sizes as $key => $size ) {

				$image_name = $this->use_ratio_map ? $key : $size;

				$edit_url = admin_url( 'admin.php?action=wpcom_thumbnail_edit&id=' . intval( $attachment->ID ) . '&size=' . urlencode( $size ) );

				// add an extra query var if were using a ratio map
				if( $this->use_ratio_map ) {
					$edit_url = add_query_arg( 'ratio', $key, $edit_url );
				}

				// We need to get the fullsize thumbnail so that the cropping is properly done
				$thumbnail = image_downsize( $attachment->ID, $size );

				// Resize the thumbnail to fit into a small box so it's displayed at a reasonable size
				if( function_exists( 'jetpack_photon_url' ) )
					$thumbnail_url = jetpack_photon_url( $thumbnail[0], array( 'fit' => array( 250, 250 ) ) );
				else
					$thumbnail_url = $thumbnail[0];

				$html .= '<div style="float:left;margin:0 20px 20px 0;min-width:250px;">';
					$html .= '<a href="' . esc_url( $edit_url ) . '"';

					if ( 'media.php' != basename( $_SERVER['PHP_SELF'] ) )
						$html .= ' target="_blank"';

					$html .= '>';
						$html .= '<strong>' . esc_html( $image_name ) . '</strong><br />';
						$html .= '<img src="' . esc_url( $thumbnail_url ) . '" alt="' . esc_attr( $size ) . '" />';
					$html .= '</a>';
				$html .= '</div>';
			}

			$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Outputs the HTML for the thumbnail crop selection screen.
	 */
	public function edit_thumbnail_screen() {
		global $parent_file, $submenu_file, $title;

		// Validate "id" and "size" query string values and check user capabilities. Dies on error.
		$attachment = $this->validate_parameters();

		$size = $_REQUEST['size']; // Validated in this::validate_parameters()

		// Make sure the image fits on the screen
		if ( ! $image = image_downsize( $attachment->ID, array( 1024, 1024 ) ) )
			wp_die( __( 'Failed to downsize the original image to fit on your screen. How odd. Please contact support.', 'wpcom-thumbnail-editor' ) );

		// How big is the final thumbnail image?
		if ( ! $thumbnail_dimensions = $this->get_thumbnail_dimensions( $size ) )
			wp_die( sprintf( __( 'Invalid %s parameter.', 'wpcom-thumbnail-editor' ), '<code>size</code>' ) );


		$parent_file = 'upload.php';
		$submenu_file = 'upload.php';

		// adjust the image name if were using a ratio map
		$image_name = isset( $_REQUEST['ratio'] ) ? $_REQUEST['ratio'] : $size;

		$title = sprintf( __( 'Edit Thumbnail: %s', 'wpcom-thumbnail-editor' ), $image_name );

		wp_enqueue_script( 'imgareaselect' );
		wp_enqueue_style( 'imgareaselect' );

		require( ABSPATH . '/wp-admin/admin-header.php' );


		$original_aspect_ratio  = $image[1] / $image[2];
		$thumbnail_aspect_ratio = $thumbnail_dimensions['width'] / $thumbnail_dimensions['height'];


		# Build the selection coordinates

		// If there's already a custom selection
		if ( $coordinates = $this->get_coordinates( $attachment->ID, $size ) ) {
			$attachment_metadata = wp_get_attachment_metadata( $attachment->ID );

			// If original is bigger than display, scale down the coordinates to match the scaled down original
			if ( $attachment_metadata['width'] > $image[1] || $attachment_metadata['height'] > $image[2] ) {

				// At what percentage is the image being displayed at?
				$scale = $image[1] / $attachment_metadata['width'];

				foreach ( $coordinates as $coordinate ) {
					$initial_selection[] = round( $coordinate * $scale );
				}
			}

			// Or the image was not downscaled, so the coordinates are correct
			else {
				$initial_selection = $coordinates;
			}
		}
		// If original and thumb are the same aspect ratio, then select the whole image
		elseif ( $thumbnail_aspect_ratio == $original_aspect_ratio ) {
			$initial_selection = array( 0, 0, $image[1], $image[2] );
		}
		// If the thumbnail is wider than the original, we want the full width
		elseif ( $thumbnail_aspect_ratio > $original_aspect_ratio ) {
			// Take the width and divide by the thumbnail's aspect ratio
			$selected_height = round( $image[1] / ( $thumbnail_dimensions['width'] / $thumbnail_dimensions['height'] ) );

			$initial_selection = array(
				0,                                                     // Far left edge (due to aspect ratio comparison)
				round( ( $image[2] / 2 ) - ( $selected_height / 2 ) ), // Mid-point + half of height of selection
				$image[1],                                             // Far right edge (due to aspect ratio comparison)
				round( ( $image[2] / 2 ) + ( $selected_height / 2 ) ), // Mid-point - half of height of selection
			);
		}
		// The thumbnail must be narrower than the original, so we want the full height
		else {
			// Take the width and divide by the thumbnail's aspect ratio
			$selected_width = round( $image[2] / ( $thumbnail_dimensions['height'] / $thumbnail_dimensions['width'] ) );

			$initial_selection = array(
				round( ( $image[1] / 2 ) - ( $selected_width / 2 ) ), // Mid-point + half of height of selection
				0,                                                    // Top edge (due to aspect ratio comparison)
				round( ( $image[1] / 2 ) + ( $selected_width / 2 ) ), // Mid-point - half of height of selection
				$image[2],                                            // Bottom edge (due to aspect ratio comparison)
			);
		}

?>

<div class="wrap">
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">

		<?php screen_icon(); ?>
		<h2><?php echo esc_html( $title ); ?></h2>

		<noscript><p><strong style="color:red;font-size:20px;"><?php esc_html_e( 'Please enable Javascript to use this page.', 'wpcom-thumbnail-editor' ); ?></strong></p></noscript>

		<p><?php esc_html_e( 'The original image is shown in full below, although it may have been shrunk to fit on your screen. Please select the portion that you would like to use as the thumbnail image.', 'wpcom-thumbnail-editor' ); ?></p>

		<script type="text/javascript">
			jQuery(document).ready(function($){
				function update_preview ( img, selection ) {
					// This is how big the selection image is
					var img_width  = <?php echo (int) $image[1]; ?>;
					var img_height = <?php echo (int) $image[2]; ?>;

					// This is how big the thumbnail preview needs to be
					var thumb_width  = <?php echo (int) $thumbnail_dimensions['width']; ?>;
					var thumb_height = <?php echo (int) $thumbnail_dimensions['height']; ?>;

					var scaleX = thumb_width / ( selection.width || 1 );
					var scaleY = thumb_height / ( selection.height || 1 );

					// Update the preview image
					$('#wpcom-thumbnail-edit-preview').css({
						width: Math.round( scaleX * img_width ) + 'px',
						height: Math.round( scaleY * img_height ) + 'px',
						marginLeft: '-' + Math.round( scaleX * selection.x1 ) + 'px',
						marginTop: '-' + Math.round( scaleY * selection.y1 ) + 'px'
					});
				}

				$('#wpcom-thumbnail-edit').imgAreaSelect({
					aspectRatio: '<?php echo intval( $thumbnail_dimensions['width'] ) . ':' . intval( $thumbnail_dimensions['height'] ) ?>',
					handles: true,

					// Initial selection
					x1: <?php echo (int) $initial_selection[0]; ?>,
					y1: <?php echo (int) $initial_selection[1]; ?>,
					x2: <?php echo (int) $initial_selection[2]; ?>,
					y2: <?php echo (int) $initial_selection[3]; ?>,

					// Update the preview
					onInit: function ( img, selection ) {
						update_preview( img, selection );
						$('#wpcom-thumbnail-edit-preview').show();
					},
					onSelectChange: function ( img, selection ) {
						update_preview( img, selection );
					},

					// Fill the hidden fields with the selected coordinates for the form
					onSelectEnd: function ( img, selection ) {
						$('input[name="wpcom_thumbnail_edit_x1"]').val(selection.x1);
						$('input[name="wpcom_thumbnail_edit_y1"]').val(selection.y1);
						$('input[name="wpcom_thumbnail_edit_x2"]').val(selection.x2);
						$('input[name="wpcom_thumbnail_edit_y2"]').val(selection.y2);
					}
				});
			});
		</script>

		<p><img src="<?php echo esc_url( $image[0] ); ?>" width="<?php echo (int) $image[1]; ?>" height="<?php echo (int) $image[2]; ?>" id="wpcom-thumbnail-edit" alt="<?php esc_attr( sprintf( __( '"%s" Thumbnail', 'wpcom-thumbnail-editor' ), $size ) ); ?>" /></p>

		<p>
			<?php submit_button( null, 'primary', 'submit', false ); ?>
			<?php submit_button( __( 'Reset Thumbnail', 'wpcom-thumbnail-editor' ), 'primary', 'wpcom_thumbnail_edit_reset', false ); ?>
			<a href="<?php echo esc_url( admin_url( 'media.php?action=edit&attachment_id=' . $attachment->ID ) ); ?>" class="button"><?php _e( 'Cancel Changes', 'wpcom-thumbnail-editor' ); ?></a>
		</p>

		<h3><?php esc_html_e( 'Fullsize Thumbnail Preview', 'wpcom-thumbnail-editor' ); ?></h3>

		<div style="overflow:hidden;width:<?php echo (int) $thumbnail_dimensions['width']; ?>px;height:<?php echo (int) $thumbnail_dimensions['height']; ?>px;">
			<img id="wpcom-thumbnail-edit-preview" class="hidden" src="<?php echo esc_url( wp_get_attachment_url( $attachment->ID ) ); ?>" />
		</div>

		<input type="hidden" name="action" value="wpcom_thumbnail_edit" />
		<input type="hidden" name="id" value="<?php echo (int) $attachment->ID; ?>" />
		<input type="hidden" name="size" value="<?php echo esc_attr( $size ); ?>" />
		<?php wp_nonce_field( 'wpcom_thumbnail_edit_' . $attachment->ID . '_' . $size ); ?> 

		<!--
			Since the fullsize image is possibly scaled down, we need to record at what size it was
			displayed at so the we can scale up the new selection dimensions to the fullsize image.
		-->
		<input type="hidden" name="wpcom_thumbnail_edit_display_width"  value="<?php echo (int) $image[1]; ?>" />
		<input type="hidden" name="wpcom_thumbnail_edit_display_height" value="<?php echo (int) $image[2]; ?>" />

		<!-- These are manipulated via Javascript to submit the selected values -->
		<input type="hidden" name="wpcom_thumbnail_edit_x1" value="<?php echo (int) $initial_selection[0]; ?>" />
		<input type="hidden" name="wpcom_thumbnail_edit_y1" value="<?php echo (int) $initial_selection[1]; ?>" />
		<input type="hidden" name="wpcom_thumbnail_edit_x2" value="<?php echo (int) $initial_selection[2]; ?>" />
		<input type="hidden" name="wpcom_thumbnail_edit_y2" value="<?php echo (int) $initial_selection[3]; ?>" />
	</form>
</div>

<?php

		require( ABSPATH . '/wp-admin/admin-footer.php' );
	}

	/**
	 * Processes the submission of the thumbnail crop selection screen and saves the results to post meta.
	 */
	public function post_handler() {

		// Validate "id" and "size" POST values and check user capabilities. Dies on error.
		$attachment = $this->validate_parameters();

		$size = $_REQUEST['size']; // Validated in this::validate_parameters()

		check_admin_referer( 'wpcom_thumbnail_edit_' . $attachment->ID . '_' . $size );

		// Reset to default?
		if ( ! empty( $_POST['wpcom_thumbnail_edit_reset'] ) ) {
			$this->delete_coordinates( $attachment->ID, $size );

			wp_safe_redirect( admin_url( 'media.php?action=edit&attachment_id=' . $attachment->ID . '&wtereset=1' ) );
			exit();
		}

		$required_fields = array(
			'wpcom_thumbnail_edit_display_width'  => 'display_width',
			'wpcom_thumbnail_edit_display_height' => 'display_height',
			'wpcom_thumbnail_edit_x1'             => 'selection_x1',
			'wpcom_thumbnail_edit_y1'             => 'selection_y1',
			'wpcom_thumbnail_edit_x2'             => 'selection_x2',
			'wpcom_thumbnail_edit_y2'             => 'selection_y2',
		);

		foreach ( $required_fields as $required_field => $variable_name ) {
			if ( empty ( $_POST[$required_field] ) && 0 != $_POST[$required_field] ) {
				wp_die( sprintf( __( 'Invalid %s parameter.', 'wpcom-thumbnail-editor' ), '<code>' . $required_field . '</code>' ) );
			}

			$$variable_name = (int) $_POST[$required_field];
		}

		$attachment_metadata = wp_get_attachment_metadata( $attachment->ID );

		$selection_coordinates = array( 'selection_x1', 'selection_y1', 'selection_x2', 'selection_y2' );

		// If the image was scaled down on the selection screen,
		// then we need to scale up the selection to fit the fullsize image
		if ( $attachment_metadata['width'] > $display_width || $attachment_metadata['height'] > $display_height ) {
			$scale_ratio = $attachment_metadata['width'] / $display_width;

			foreach ( $selection_coordinates as $selection_coordinate ) {
				${'fullsize_' . $selection_coordinate} = round( $$selection_coordinate * $scale_ratio );
			}
		} else {
			// Remap
			foreach ( $selection_coordinates as $selection_coordinate ) {
				${'fullsize_' . $selection_coordinate} = $$selection_coordinate;
			}
		}

		// Save the coordinates
		$this->save_coordinates( $attachment->ID, $size, array( $fullsize_selection_x1, $fullsize_selection_y1, $fullsize_selection_x2, $fullsize_selection_y2 ) );

		wp_safe_redirect( admin_url( 'media.php?action=edit&attachment_id=' . $attachment->ID . '&wteupdated=1' ) );
		exit();
	}

	/**
	 * Makes sure that the "id" (attachment ID) and "size" (thumbnail size) query string parameters are valid
	 * and dies if they are not. Returns attachment object with matching ID on success.
	 *
	 * @return null|object Dies on error, returns attachment object on success.
	 */
	public function validate_parameters() {
		if ( empty( $_REQUEST['id'] ) || ! $attachment = get_post( intval( $_REQUEST['id'] ) ) )
			wp_die( sprintf( __( 'Invalid %s parameter.', 'wpcom-thumbnail-editor' ), '<code>id</code>' ) );

		if ( 'attachment' != $attachment->post_type  || ! wp_attachment_is_image( $attachment->ID ) )
			wp_die( sprintf( __( 'That is not a valid image attachment.', 'wpcom-thumbnail-editor' ), '<code>id</code>' ) );

		if ( ! current_user_can( get_post_type_object( $attachment->post_type )->cap->edit_post, $attachment->ID ) )
			wp_die( __( 'You are not allowed to edit this attachment.' ) );


		if ( $this->use_ratio_map ) {
			if ( empty( $_REQUEST['size'] ) || ! in_array( $_REQUEST['size'], $this->get_image_sizes_by_ratio() ) )
				wp_die( sprintf( __( 'Invalid %s parameter.', 'wpcom-thumbnail-editor' ), '<code>size</code>' ) );
		} else {
			if ( empty( $_REQUEST['size'] ) || ! in_array( $_REQUEST['size'], $this->get_intermediate_image_sizes() ) )
				wp_die( sprintf( __( 'Invalid %s parameter.', 'wpcom-thumbnail-editor' ), '<code>size</code>' ) );
		}

		return $attachment;
	}

	/**
	 * Returns all thumbnail size names. get_intermediate_image_sizes() is filtered to return an
	 * empty array on WordPress.com so this function removes that filter, calls the function,
	 * and then re-adds the filter back onto the function.
	 *
	 * @return array An array of image size strings.
	 */
	public function get_intermediate_image_sizes( $cropped_only = true ) {
		global $_wp_additional_image_sizes;

		# /wp-content/mu-plugins/wpcom-media.php
		$had_filter = remove_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );

		$sizes = get_intermediate_image_sizes();

		if ( $had_filter ) {
			add_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );
		}

		if ( $cropped_only ) {
			$filtered_sizes = array();

			foreach ( $sizes as $size ) {
				switch ( $size ) {
					case 'thumbnail':
						if ( get_option( 'thumbnail_crop' ) )
							$filtered_sizes[] = $size;
						break;

					case 'medium':
					case 'large':
						break;

					default:
						if ( ! empty( $_wp_additional_image_sizes[$size] ) && $_wp_additional_image_sizes[$size]['crop'] )
							$filtered_sizes[] = $size;
				}
			}

			$sizes = $filtered_sizes;
		}

		return $sizes;
	}

	/**
	 * Gets the first size defined for each dimension. All images are assumed to be cropped
	 *
	 * @todo Add validation that image sizes are of the cropped variety?
	 * @return array Array of image size strings.
	 */
	public function get_image_sizes_by_ratio() {

		$ratios = array_keys( $this->image_ratio_map );

		foreach( $ratios as $ratio ) {
			if( isset( $this->image_ratio_map[$ratio][0] ) )
				$sizes[$ratio] = $this->image_ratio_map[$ratio][0];
		}

		return $sizes;
	}

	/**
	 * Returns the width and height of a given thumbnail size.
	 *
	 * @param $size string Thumbnail size name.
	 * @return array|false Associative array of width and height in pixels. False on invalid size.
	 */
	public function get_thumbnail_dimensions( $size ) {
		global $_wp_additional_image_sizes;

		switch ( $size ) {
			case 'thumbnail':
			case 'medium':
			case 'large':
				$width  = get_option( $size . '_size_w' );
				$height = get_option( $size . '_size_h' );
				break;

			default:
				if ( empty( $_wp_additional_image_sizes[$size] ) )
					return false;

				$width  = $_wp_additional_image_sizes[$size]['width'];
				$height = $_wp_additional_image_sizes[$size]['height'];
		}

		// Just to be safe
		$width  = (int) $width;
		$height = (int) $height;

		return array( 'width' => $width, 'height' => $height );
	}

	/**
	 * Fetches the coordinates for a custom crop for a given attachment ID and thumbnail size.
	 *
	 * @param $attachment_id int Attachment ID.
	 * @param $size string Thumbnail size name.
	 * @return array|false Array of crop coordinates or false if no custom selection set.
	 */
	public function get_coordinates( $attachment_id, $size ) {
		$sizes = (array) get_post_meta( $attachment_id, $this->post_meta, true );

		$coordinates = false;

		if ( empty( $sizes[ $size ] ) ) {
			// Coordinates not explictly set for this size, but is it in a size group? If so, we can use the coordinates
			// from other sizes in the same group, as they are always the same. Happens if a size is added to a group later and hasn't
			// been backfilled in all post meta. Not sure why coords are saved for every size, rather than group, but hey.
			if ( $this->use_ratio_map ) {
				foreach( $this->image_ratio_map as $ratio => $ratio_sizes ) {
					foreach( $ratio_sizes as $ratio_size ) {
						if ( $size === $ratio_size ) {
							// Determine if there are any saved coordinates that match the desired $size in the matched ratio group
							$intersect = array_intersect_key( $ratio_sizes, $sizes );

							if ( is_array( $intersect ) && ! empty( $intersect ) ) {
								foreach( $intersect as $matching_size ) {
									if ( isset( $sizes[ $matching_size ] ) ) {
										$coordinates 	= $sizes[ $matching_size ];

										break;
									}
								}
							}
						}
					}
				}
			}
		} else {
			$coordinates = $sizes[ $size ];
		}

		return $coordinates;
	}

	/**
	 * Saves the coordinates for a custom crop for a given attachment ID and thumbnail size.
	 *
	 * @param $attachment_id int Attachment ID.
	 * @param $size string Thumbnail size name.
	 * @param $coordinates array Array of coordinates in the format array( x1, y1, x2, y2 )
	 */
	public function save_coordinates( $attachment_id, $size, $coordinates ) {
		$sizes = (array) get_post_meta( $attachment_id, $this->post_meta, true );

		$sizes[$size] = $coordinates;

		// save meta for all the related sizes to if were using a ratio map
		if( $this->use_ratio_map ) {

			$related_sizes = $this->get_related_sizes( $size );

			// add the same meta value to the related sizes
			if( count( $related_sizes ) ) {
				foreach( $related_sizes as $related_size ){
					$sizes[$related_size] = $coordinates;
				}
			}
		}

		update_post_meta( $attachment_id, $this->post_meta, $sizes );
	}

	/**
	 * Find the siblings of the passed size so we can apply the coordinates to them too.
	 *
	 * @return array Array of related image size strings
	 */
	public function get_related_sizes( $size ) {

		$related_sizes = array();

		// find out which ratio map the size belongs to
		foreach( $this->image_ratio_map as $ratio => $ratio_sizes ) {
			foreach( $ratio_sizes as $ratio_size ) {
				if( $ratio_size == $size ){
					$related_sizes = $this->image_ratio_map[$ratio];
					break 2;
				}
			}
		}

		return $related_sizes;
	}

	/**
	 * Deletes the coordinates for a custom crop for a given attachment ID and thumbnail size.
	 *
	 * @param $attachment_id int Attachment ID.
	 * @param $size string Thumbnail size name.
	 * @return bool False on failure (probably no such custom crop), true on success.
	 */
	public function delete_coordinates( $attachment_id, $size ) {
		if ( ! $sizes = get_post_meta( $attachment_id, $this->post_meta, true ) )
			return false;

		if ( empty( $sizes[$size] ) )
			return false;

		unset( $sizes[$size] );

		// also unset related sizes
		if( $this->use_ratio_map ) {
			$related_sizes = $this->get_related_sizes( $size );
			if( count( $related_sizes ) ) {
				foreach( $related_sizes as $related_size ){
					unset( $sizes[$related_size] );
				}
			}
		}

		return update_post_meta( $attachment_id, $this->post_meta, $sizes );
	}

	/**
	 * Returns the attributes for a given attachment thumbnail. Meant for hooking into image_downsize().
	 *
	 * @param $existing_resize array|false Any existing data. Returned on no action.
	 * @param $attachment_id int Attachment ID.
	 * @param $size string Thumbnail size name.
	 * @return mixed Array of thumbnail details (URL, width, height, is_intermedite) or the previous data.
	 */
	public function get_thumbnail_url( $existing_resize, $attachment_id, $size ) {
		// Named sizes only
		if ( is_array( $size ) )
			return $existing_resize;

		$coordinates = $this->get_coordinates( $attachment_id, $size );

		if ( ! $coordinates || ! is_array( $coordinates ) || 4 != count( $coordinates ) )
			return $existing_resize;

		if ( ! $thumbnail_size = $this->get_thumbnail_dimensions( $size ) )
			return $existing_resize;

		list( $selection_x1, $selection_y1, $selection_x2, $selection_y2 ) = $coordinates;

		if( function_exists( 'jetpack_photon_url' ) )
			$url = jetpack_photon_url(
				wp_get_attachment_url( $attachment_id ),
				array(
					'crop' => array( 
						$selection_x1 . 'px',
						$selection_y1 . 'px',
						( $selection_x2 - $selection_x1 ) . 'px',
						( $selection_y2 - $selection_y1 ) . 'px',
					),
					'resize' => array(
						$thumbnail_size['width'],
						$thumbnail_size['height'],
					),
				)
			);
		else
			$url = wp_get_attachment_url( $attachment_id );

		return array( $url, $thumbnail_size['width'], $thumbnail_size['height'], true );
	}
}

// initializing the class on init so we can filter the args
add_action( 'init', function() {
	$GLOBALS['WPcom_Thumbnail_Editor'] = new WPcom_Thumbnail_Editor;
} );
