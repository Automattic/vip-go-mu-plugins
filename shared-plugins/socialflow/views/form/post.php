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
?>

<?php 
// Nonce is stored as postmeta to prevent multiple message submission
$nonce = wp_create_nonce( SF_ABSPATH );
update_post_meta( $post->ID, 'socialflow_nonce', $nonce );
?>
<input type="hidden" name="socialflow_nonce" value="<?php echo $nonce; ?>" />

<p class="sf_compose">
	<label for="sf_compose">
		<?php if ( 'publish' != $post->post_status ) : ?>
			<?php _e( 'Send to SocialFlow when the post is published', 'socialflow' ); ?>
		<?php else : ?>
			<?php _e( 'Send to SocialFlow when the post is updated', 'socialflow' ); ?>
		<?php endif; ?>
	</label>
	<input id="sf_compose" type="checkbox" value="1" name="socialflow[compose_now]" <?php checked( $compose_now, 1 ); ?> />
</p>

<button id="sf_autofill" class="button">Auto-populate</button>

<input id="sf-post-id" type="hidden" value="<?php echo esc_attr( $post->ID ); ?>" />

<ul class="compose-tabs" id="sf-compose-tabs">
	<?php foreach ( $grouped_accounts as $group => $group_accounts ) : ?>
		<li class="tabs <?php echo esc_attr( $group ); ?>-tab-item"><a href="#sf-compose-<?php echo esc_attr( $group ); ?>-panel"><?php echo SocialFlow_Accounts::get_type_title( $group ); ?></a></li>
	<?php endforeach; // accounts loop ?>
</ul>

<?php
// Loop through grouped accounts
foreach ( $grouped_accounts as $group => $group_accounts ) :
	$message = esc_html( get_post_meta( $post->ID, 'sf_message_'.$group, true ) );
?>
	<div class="tabs-panel sf-tabs-panel <?php echo esc_attr( $group ); ?>-tab-panel" id="sf-compose-<?php echo esc_attr( $group ); ?>-panel">

		<textarea data-content-selector="#title" class="autofill widefat socialflow-message-<?php echo esc_attr( $group ); ?>" id="sf_message_<?php echo esc_attr( $group ); ?>" name="socialflow[message][<?php echo esc_attr( $group ); ?>]" cols="30" rows="5" placeholder="<?php _e('Message', 'socialflow') ?>" ><?php echo esc_html( $message ); ?></textarea>

		<?php if ( 'facebook' == $group ) :
	    	$title       = esc_attr( get_post_meta( $post->ID, 'sf_title_'.$group, true ) );
			$description = esc_html( get_post_meta( $post->ID, 'sf_description_'.$group, true ) );
			$image       = esc_attr( get_post_meta( $post->ID, 'sf_image_'.$group, true ) );
		?>
		<div class="sf-additional-fields">
			<div class="sf-attachments">
				<div class="sf-attachment-slider" id="sf-attachment-slider">
					<?php $SocialFlow_Post->post_attachments( $post->ID, $post->post_content ); ?>
				</div>

				<span title="<?php _e( 'Previous', 'socialflow' ) ?>" class="prev icon" id="sf-attachment-slider-prev"><?php _e( 'Previous', 'socialflow' ); ?></span>
				<span title="<?php _e( 'Next', 'socialflow' ) ?>" class="next icon" id="sf-attachment-slider-next"><?php _e( 'Next', 'socialflow' ); ?></span>
				<span class="sf-update-attachments icon reload" id="sf-update-attachments"><?php _e( 'Update attachments' ); ?></span>

				<input id="sf-current-attachment" type="hidden" name="socialflow[image][<?php echo esc_attr( $group ); ?>]" value="<?php echo esc_attr( $image ); ?>" />
			</div>

			<input data-content-selector="#title" class="autofill sf-title widefat socialflow-title-<?php echo esc_attr( $group ); ?>" type="text" name="socialflow[title][<?php echo esc_attr( $group ); ?>]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php _e( 'Title', 'socialflow' ); ?>" />
			<textarea data-content-selector="editor" class="autofill sf-description widefat socialflow-description-<?php echo esc_attr( $group ); ?>" name="socialflow[description][<?php echo esc_attr( $group ); ?>]" cols="30" rows="5" placeholder="<?php _e( 'Description', 'socialflow' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
		</div>
		<?php endif; // fecebook group ?>
	</div>
<?php endforeach; // accounts loop ?>

<?php  // Render advenced settings ?>
<div class="advanced-settings">
	<a id="sf-advanced-toggler" class="advanced-toggler" href="#"><?php _e( 'Advanced Settings', 'socialflow' ) ?></a>
	<!-- display none -->
	<div id="sf-advanced-content" class="advanced-settings-content" >
		<table><tbody>

		<?php 
		// Get saved settings
		$advanced = get_post_meta( $post->ID, 'sf_advanced', true );
		$methods = array( 'publish', 'hold', 'optimize' );

		$_must = __( 'Must Send', 'socialflow' );
		$_can = __( 'Can Send', 'socialflow' );

		// array of enabled account ids
		$send_to = ( '' !== get_post_meta( $post->ID, 'sf_send_accounts', true ) ) ? get_post_meta( $post->ID, 'sf_send_accounts', true ) : $socialflow->options->get( 'send', array() );

		foreach ( $accounts as $user_id => $account ) : 

			// Extract acctount advanced variables
			extract( $SocialFlow_Post->get_user_advanced_options( $account, $advanced ) );
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
						<option value="optimize" <?php selected( $publish_option, 'optimize' ); ?>><?php _e( 'Optimize', 'socialflow' ); ?></option>
						<option value="publish now" <?php selected( $publish_option, 'publish now' ); ?>><?php _e( 'Publish Now', 'socialflow' ); ?></option>
						<option value="hold" <?php selected( $publish_option, 'hold' ); ?>><?php _e( 'Hold', 'socialflow' ); ?></option>
						<option value="schedule" <?php selected( $publish_option, 'schedule' ); ?>><?php _e( 'Schedule', 'socialflow' ); ?></option>
					</select>

					<span class="optimize">
						<span class="clickable must_send" data-toggle_html="<?php echo ( 0 == $must_send ) ? $_must : $_can; ?>"><?php echo ( 0 == $must_send ) ? $_can : $_must; ?></span>
						<input class="must_send" type="hidden" value="<?php echo esc_attr( $must_send ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][must_send]" />

						<select class="optimize-period" name="socialflow[<?php echo esc_attr( $user_id ); ?>][optimize_period]">
							<option <?php selected( $optimize_period, '10 minutes' ); ?> value="10 minutes" >10 minutes</option>
							<option <?php selected( $optimize_period, '1 hour' ); ?> value="1 hour">1 hour</option>
							<option <?php selected( $optimize_period, '1 day' ); ?> value="1 day">1 day</option>
							<option <?php selected( $optimize_period, '1 week' ); ?> value="1 week">1 week</option>
							<option <?php selected( $optimize_period, 'anytime' ); ?> value="anytime">Anytime</option>
							<option <?php selected( $optimize_period, 'range' ); ?> value="range">Pick a range</option>
						</select>

						<span class="optimize-range" <?php if ( $optimize_period != 'range' ) echo 'style="display:none;"' ?>>
							<?php _e( 'from', 'socialflow' ); ?>
							<input class="time datetimepicker" type="text" value="<?php echo esc_attr( $optimize_start_date ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][optimize_start_date]" data-tz-offset="<?php echo ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ?>" />
							<?php _e( 'to', 'socialflow' ); ?>
							<input class="time datetimepicker" type="text" value="<?php echo esc_attr( $optimize_end_date ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][optimize_end_date]" data-tz-offset="<?php echo ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); ?>" />
						</span>
					</span>

					<span class="schedule">
						<?php _e( 'Send at', 'socialflow' ); ?>
						<input class="time datetimepicker" type="text" value="<?php echo esc_attr( $scheduled_date ); ?>" name="socialflow[<?php echo esc_attr( $user_id ); ?>][scheduled_date]" data-tz-offset="<?php echo ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); ?>" />
					</span>
				</td>

			</tr><!-- .field -->
		<?php endforeach; ?>
		</tbody></table>
	</div><!-- #sf-advanced-content -->
</div><!-- .advanced-settings -->