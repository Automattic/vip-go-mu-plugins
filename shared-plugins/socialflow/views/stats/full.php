<?php
/**
 * Template for displaying full message statistics
 *
 * @since 1.0
 *
 * @param $form_messages - array of successful form submission statuses
 * @param $last_sent     - string for last sent date in mysql date format
 * @param $post_id       - int ID for current post
 */
global $socialflow;

$form_messages = $data['form_messages'];
$last_sent     = $data['last_sent'];
$post_id       = $data['post_id'];

$i = 0;
?>
<div class="full-stats-container">
<?php if ( !empty( $form_messages ) ) : ?>
<p>
	<?php printf(  esc_attr__( 'Last time message was successfully sent at %s', 'socialflow' ), mysql2date( 'd F, Y h:i a', $last_sent ) ); ?>
	<span id="js-sf-toggle-statistics" class="clickable"><?php esc_html_e( 'Expand Statistics.', 'socialflow' ); ?></span>
</p>
<table id="sf-statistics" cellspacing="0" class="wp-list-table widefat fixed sf-statistics" style="display:none">
	<thead><tr>
		<th style="width:150px" class="manage-column column-date" scope="col">
			<span><?php esc_html_e( 'Last Sent', 'socialflow' ) ?></span>
		</th>
		<th class="manage-column column-status" scope="col">
			<?php esc_html_e( 'Account', 'socialflow' ) ?>
		</th>
		<th class="manage-column column-status" scope="col">
			<?php esc_html_e( 'Status', 'socialflow' ) ?>
		</th>
		<th scope="col" width="20px">
			<img title="<?php esc_html_e( 'Refresh Message Stats', 'socialflow' ); ?>" alt="<?php esc_attr_e( 'Refresh', 'socialflow' ); ?>" class="sf-js-update-multiple-messages" src="<?php echo plugins_url( 'assets/images/reload.png', SF_FILE ) ?>" >
		</th>
	</tr></thead>

	<tbody class="list:statistics">
		<?php foreach ( $form_messages as $date => $success ) : 
			$first = true;
			$alt = ( $i%2 == 0 ) ? 'alternate' : '';
			$i++;
		?>
			<?php foreach ( $success as $user_id => $message ) : 
				$account = $socialflow->accounts->get( $user_id );

				// In queue status
				if ( isset( $message['is_published'] ) ) {
					$queue_status = ( 0 == $message['is_published'] ) ?  esc_attr__( 'In Queue', 'socialflow' ) :  esc_attr__( 'Published', 'socialflow' );
				} else {
					$queue_status = '';
				}
			?>
				<tr class="message <?php echo esc_attr( $alt ); ?>" data-id="<?php echo esc_attr( $message['content_item_id'] ); ?>" data-date="<?php echo esc_attr( $date ); ?>" data-account-id="<?php echo esc_attr( $user_id ); ?>" data-post_id="<?php echo esc_attr( $post_id ); ?>" >
					<?php if ( $first ) : ?>
					<td class="username column-username" rowspan="<?php echo count( $success ); ?>"  >
						<?php echo mysql2date( 'd F, Y h:i', $date ); ?>
					</td>
					<?php endif; ?>
					<td class="account column-account">
						<?php echo esc_attr( $socialflow->accounts->get_display_name( $user_id ) ); ?>
					</td>
					<td class="status column-status" >
						<?php echo wp_kses_post( $message['status'] ); ?>
						<?php echo wp_kses_post( $queue_status ); ?>
					</td>
					<td>
						<img class="sf-message-loader" style="display:none;" src="<?php echo plugins_url( 'assets/images/wpspin.gif', SF_FILE ) ?>" alt="">
					</td>
				</tr>

			<?php $first = false; endforeach; ?>
		<?php endforeach ?>
	</tbody>
</table>
<?php endif; // we have statuses ! ?>
</div><!-- full stats container -->