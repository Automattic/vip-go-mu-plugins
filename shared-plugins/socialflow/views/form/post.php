<?php
/**
 * Template for displaying post compose form
 *
 * Available variables
 *
 * @param $post             - object current post
 * @param $compose_now      - int ( 0 | 1 ) compose now status
 * @param $grouped_accounts - array of accounts grouped by type
 * @param $accounts         - non grouped array of accounts
 * @param $SocialFlow_Post  - reference to SocialFlow_Post class object
 * 
 *
 * @since 2.0
 */
global $socialflow;

$grouped_accounts = $data['grouped_accounts'];
$post             = $data['post'];
$SocialFlow_Post  = $data['SocialFlow_Post'];
$compose_now      = $data['compose_now'];
$accounts         = $data['accounts'];

?>

<?php 
// Nonce is stored as postmeta to prevent multiple message submission
$nonce = wp_create_nonce( SF_ABSPATH );
update_post_meta( $post->ID, 'socialflow_nonce', $nonce );
?>
<input type="hidden" name="socialflow_nonce" value="<?php echo $nonce; ?>" />

<input type="hidden" name="sf_current_post_id" id="sf_current_post_id" value="<?php echo esc_attr( $post->ID ); ?>">

<p class="sf_compose">
	<input id="sf_compose" type="checkbox" value="1" name="socialflow[compose_now]" <?php checked( $compose_now, 1 ); ?> />
	<label for="sf_compose">
		<?php if ( 'publish' != $post->post_status ) : ?>
			<?php esc_html_e( 'Send to SocialFlow when the post is published', 'socialflow' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'Send to SocialFlow when the post is updated', 'socialflow' ); ?>
		<?php endif; ?>
	</label>
</p>

<?php if ( 'attachment' !== $post->post_type ) : ?>
	<p class="sf-media-toggle-container"> 
		<input id="sf_media_compose" class="sf_media_compose" type="checkbox" value="1" name="socialflow[compose_media]" <?php checked( get_post_meta( $post->ID, 'sf_compose_media', true ), 1 ); ?> />
		<label for="sf_media_compose"><?php esc_html_e( 'Image Post', 'socialflow' ) ?></label>
	</p>
<?php endif; ?>

<p class="sf-autofill-button-container"><button id="sf_autofill" class="button"><?php esc_html_e( 'Auto-populate', 'socialflow' ); ?></button></p>

<input id="sf-post-id" type="hidden" value="<?php echo esc_attr( $post->ID ); ?>" />

<ul class="compose-tabs" id="sf-compose-tabs">
	<?php foreach ( $grouped_accounts as $group => $group_accounts ) : ?>
		<li class="tabs <?php echo esc_attr( $group ); ?>-tab-item"><a href="#sf-compose-<?php echo esc_attr( $group ); ?>-panel"><?php echo SocialFlow_Accounts::get_type_title( $group ); ?></a></li>
	<?php endforeach; // accounts loop ?>
</ul>

<?php
// Loop through grouped accounts
foreach ( $grouped_accounts as $group => $group_accounts ) :
	$message = esc_html( apply_filters( 'sf_message', get_post_meta( $post->ID, 'sf_message_'.$group, true ), $group, $post ) );
?>
	<div class="tabs-panel sf-tabs-panel <?php echo esc_attr( $group ); ?>-tab-panel" id="sf-compose-<?php echo esc_attr( $group ); ?>-panel">

		<textarea data-content-selector="#title" class="autofill widefat socialflow-message-<?php echo esc_attr( $group ); ?>" id="sf_message_<?php echo esc_attr( $group ); ?>" name="socialflow[message][<?php echo esc_attr( $group ); ?>]" cols="30" rows="5" placeholder="<?php esc_html_e('Message', 'socialflow') ?>" ><?php echo esc_html( $message ); ?></textarea>

		<?php if ( 'google_plus' == $group ) : ?>
			<span class="sf-muted-text"><?php esc_html_e( '* Metadata title and description are not editable for G+', 'socialflow' ); ?></span>
		<?php endif; ?>

		<?php if ( in_array( $group, array( 'google_plus', 'facebook', 'linkedin' ) ) ) :

			if ( in_array( $group, array( 'facebook', 'linkedin' ) ) ) {
				$title       = get_post_meta( $post->ID, 'sf_title_'.$group, true );
				$description = get_post_meta( $post->ID, 'sf_description_'.$group, true );
			} else {
				$title = $post->post_title;
				$description = ( !empty( $post->post_excerpt ) ) ? $post->post_excerpt : $post->post_content;
				$description = wp_trim_words( strip_tags( apply_filters( 'the_content', $description ) ), 20, '...' );
			}

			$image = get_post_meta( $post->ID, 'sf_image_'.$group, true );

			if ( 'attachment' == $post->post_type ) {
				$is_custom_image = true;
				$media_image = $SocialFlow_Post->get_attachment_media( $post->ID );

				$custom_image = is_array( $media_image ) ? $media_image['medium_thumbnail_url'] : '';
				$custom_image_filename = is_array( $media_image ) ? $media_image['filename'] : '';
			} 
			else {
				$is_custom_image = absint( get_post_meta( $post->ID, 'sf_is_custom_image_'.$group, true ) );
				$custom_image = get_post_meta( $post->ID, 'sf_custom_image_'.$group, true );
				$custom_image_filename = get_post_meta( $post->ID, 'sf_custom_image_filename_'.$group, true );
			}

		?>
		<div class="sf-additional-fields">

			<div class="sf-attachments js-sf-attachments <?php if ( $is_custom_image ) echo 'sf-is-custom-attachment'; ?>">

				<div class="sf-attachments-slider">
					<div class="image-container sf-attachment-slider">
						<?php $SocialFlow_Post->post_attachments( $post->ID, $post->post_content ); ?>
					</div>

					<?php if ( 'linkedin' !== $group ) : ?>
						<button class="button button-attachment-switch-status js-toggle-custom-image"><?php esc_html_e( 'Select', 'socialflow' ); ?></button>
					<?php endif; ?>

					<span title="<?php esc_html_e( 'Previous', 'socialflow' ) ?>" class="prev icon sf-attachment-slider-prev"><?php esc_html_e( 'Previous', 'socialflow' ); ?></span>
					<span title="<?php esc_html_e( 'Next', 'socialflow' ) ?>" class="next icon sf-attachment-slider-next"><?php esc_html_e( 'Next', 'socialflow' ); ?></span>
					<span class="sf-update-attachments icon reload sf-update-attachments"><?php esc_html_e( 'Update attachments', 'socialflow' ); ?></span>
				</div>

				<div class="sf-attachments-custom">
					<div class="image-container">
						<?php if ( $custom_image ) : ?>
							<img src="<?php echo esc_url( $custom_image ); ?>" alt="">
						<?php endif; ?>
					</div>

					<?php if ( 'linkedin' !== $group ) : ?>
						<button class="button button-attachment-switch-status js-toggle-custom-image"><?php esc_html_e( 'Cancel', 'socialflow' ); ?></button>
					<?php endif; ?>

					<button class="button js-attachments-set-custom-image sf-custom-attachment-button"><?php esc_html_e( 'Select', 'socialflow' ); ?> <span class="additional-hint"><?php esc_html_e( 'image', 'socialflow' ); ?></span></button>
				</div>

				<input class="sf-current-attachment" type="hidden" name="socialflow[image][<?php echo esc_attr( $group ); ?>]" value="<?php echo esc_attr( $image ); ?>" />

				<input class="sf-is-custom-image" type="hidden" name="socialflow[is_custom_image][<?php echo esc_attr( $group ); ?>]" value="<?php echo esc_attr( $is_custom_image ); ?>" />
				<input class="sf-custom-image" type="hidden" name="socialflow[custom_image][<?php echo esc_attr( $group ); ?>]" value="<?php echo esc_attr( $custom_image ); ?>" />
				<input class="sf-custom-image-filename" type="hidden" name="socialflow[custom_image_filename][<?php echo esc_attr( $group ); ?>]" value="<?php echo esc_attr( $custom_image_filename ); ?>" />
			</div>

			<?php if ( in_array( $group, array( 'facebook', 'linkedin' ) ) ) : ?>
			<input data-content-selector="#title" class="autofill sf-title widefat socialflow-title-<?php echo esc_attr( $group ); ?>" type="text" name="socialflow[title][<?php echo esc_attr( $group ); ?>]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_html_e( 'Title', 'socialflow' ); ?>" />
			<textarea data-content-selector="#content" class="autofill sf-description widefat socialflow-description-<?php echo esc_attr( $group ); ?>" name="socialflow[description][<?php echo esc_attr( $group ); ?>]" cols="30" rows="5" placeholder="<?php esc_html_e( 'Description', 'socialflow' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
			<?php else : ?>
			<div class="sf-muted-text" data-content-selector="#title" class="autofill"><?php echo esc_attr( $title ); ?></div> <hr>
			<div class="sf-muted-text" data-content-selector="#content" class="autofill" ><small><?php echo esc_html( $description ); ?></small></div>
			<?php endif; ?>
		</div>
		<?php endif; // fecebook group ?>
	</div>
<?php endforeach; // accounts loop ?>

<div class="tabs-panel sf-media-attachment">
	<?php $media = get_post_meta( $post->ID, 'sf_media', true ); ?>

	<div class="sf-image-container">
		<?php if ( $media ) : ?>
		<img src="<?php echo esc_url( $media['medium_thumbnail_url'] ); ?>" alt="">
		<?php endif; ?>
	</div>

	<?php if ( 'attachment' !== $post->post_type ) : ?>
		<button class="button js-attachments-set-media sf-custom-attachment-button"><?php esc_html_e( 'Select', 'socialflow' ); ?> <span class="additional-hint"><?php esc_html_e( 'image', 'socialflow' ); ?></span></button>
	<?php endif; ?>
</div>


<?php  // Render advenced settings ?>
<div class="advanced-settings">
	<a id="sf-advanced-toggler" class="advanced-toggler" href="#"><?php esc_html_e( 'Advanced Settings', 'socialflow' ) ?></a>
	<!-- display none -->
	<div id="sf-advanced-content" class="advanced-settings-content" >
		<table><tbody>

		<?php 
		// Get saved settings
		$advanced = get_post_meta( $post->ID, 'sf_advanced', true );
		$methods = array( 'publish', 'hold', 'optimize' );

		$_must = esc_attr__( 'Must Send', 'socialflow' );
		$_can = esc_attr__( 'Can Send', 'socialflow' );

		// array of enabled account ids
		$send_to = ( '' !== get_post_meta( $post->ID, 'sf_send_accounts', true ) ) ? get_post_meta( $post->ID, 'sf_send_accounts', true ) : $socialflow->options->get( 'send', array() );

		foreach ( $accounts as $user_id => $account ) : 

			// Extract acctount advanced variables
			$advanced_options = $SocialFlow_Post->get_user_advanced_options( $account, $advanced );
			?>
			<tr valign="top" class="field socialflow-user-advanced">
				<td>
					<label class="account" for="sf_send_<?php echo esc_attr( $user_id ); ?>">
						<input class="js-sf-account-checkbox" name="socialflow[send][]" id="sf_send_<?php echo esc_attr( $user_id ); ?>" type="checkbox" <?php checked( in_array( $user_id, $send_to ), true ) ?> value="<?php echo esc_attr( $user_id ); ?>" /> 
						<?php echo esc_html( $socialflow->accounts->get_display_name( $account ) ); ?>
					</label>
				</td>
				<td>
					<select class="publish-option" id="sf_publish_option<?php echo esc_attr( $user_id ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][publish_option]">
						<option value="optimize" <?php selected( $advanced_options['publish_option'], 'optimize' ); ?>><?php esc_html_e( 'Optimize', 'socialflow' ); ?></option>
						<option value="publish now" <?php selected( $advanced_options['publish_option'], 'publish now' ); ?>><?php esc_html_e( 'Publish Now', 'socialflow' ); ?></option>
						<option value="hold" <?php selected( $advanced_options['publish_option'], 'hold' ); ?>><?php esc_html_e( 'Hold', 'socialflow' ); ?></option>
						<option value="schedule" <?php selected( $advanced_options['publish_option'], 'schedule' ); ?>><?php esc_html_e( 'Schedule', 'socialflow' ); ?></option>
					</select>

					<span class="optimize">
						<span class="clickable must_send" data-toggle_html="<?php echo ( 0 == $advanced_options['must_send'] ) ? $_must : $_can; ?>"><?php echo ( 0 == $advanced_options['must_send'] ) ? $_can : $_must; ?></span>
						<input class="must_send" type="hidden" value="<?php echo esc_attr( $advanced_options['must_send'] ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][must_send]" />

						<select class="optimize-period" name="socialflow[<?php echo esc_attr( $user_id ); ?>][optimize_period]">
							<option <?php selected( $advanced_options['optimize_period'], '10 minutes' ); ?> value="10 minutes" ><?php esc_html_e( '1 hour', 'socialflow' ); ?>x</option>
							<option <?php selected( $advanced_options['optimize_period'], '1 hour' ); ?> value="1 hour"><?php esc_html_e( '1 hour', 'socialflow' ); ?></option>
							<option <?php selected( $advanced_options['optimize_period'], '1 day' ); ?> value="1 day"><?php esc_html_e( '1 day', 'socialflow' ); ?></option>
							<option <?php selected( $advanced_options['optimize_period'], '1 week' ); ?> value="1 week"><?php esc_html_e( '1 week', 'socialflow' ); ?></option>
							<option <?php selected( $advanced_options['optimize_period'], 'anytime' ); ?> value="anytime"><?php esc_html_e( 'Anytime', 'socialflow' ); ?></option>
							<option <?php selected( $advanced_options['optimize_period'], 'range' ); ?> value="range"><?php esc_html_e( 'Pick a range', 'socialflow' ); ?></option>
						</select>

						<span class="optimize-range" <?php if ( $advanced_options['optimize_period'] != 'range' ) echo 'style="display:none;"' ?>>
							<?php esc_html_e( 'from', 'socialflow' ); ?>
							<input class="time datetimepicker" type="text" value="<?php echo esc_attr( $advanced_options['optimize_start_date'] ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][optimize_start_date]" data-tz-offset="<?php echo ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ?>" />
							<?php esc_html_e( 'to', 'socialflow' ); ?>
							<input class="time datetimepicker" type="text" value="<?php echo esc_attr( $advanced_options['optimize_end_date'] ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][optimize_end_date]" data-tz-offset="<?php echo ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); ?>" />
						</span>
					</span>

					<span class="schedule">
						<?php esc_html_e( 'Send at', 'socialflow' ); ?>
						<input class="time datetimepicker" type="text" value="<?php echo esc_attr( $advanced_options['scheduled_date'] ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][scheduled_date]" data-tz-offset="<?php echo ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); ?>" />
					</span>
				</td>

			</tr><!-- .field -->
		<?php endforeach; ?>
		</tbody></table>
	</div><!-- #sf-advanced-content -->
</div><!-- .advanced-settings -->