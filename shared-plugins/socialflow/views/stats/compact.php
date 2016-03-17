<?php
/**
 * Template for displaying full message statistics
 *
 * @since 1.0
 *
 * @param $form_messages - array of successful form submission statuses
 * @param $last_sent     - string for last sent date in mysql date format
 */

global $socialflow;

$form_messages = $data['form_messages'];
$last_sent     = $data['last_sent'];
$post_id       = $data['post_id'];

$i = 0;
if ( !empty( $form_messages ) ) : ?>
<table cellspacing="0" class="wp-list-table widefat fixed sf-statistics">
	<tbody class="list:statistics">
		<tr>
			<th colspan="2">
				<a href="#" class="sf-js-update-multiple-messages clickable"><?php esc_html_e( 'Refresh Stats', 'socialflow' ) ?></a>
			</th>
			<th class="refresh-column"></th>
		</tr>
		<?php $i = 0; ?>
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
				<?php if ( $first ) : ?>
				<tr class="message <?php echo esc_attr( $alt ); ?>" >
					<th class="massage-date" colspan="3" >
						<?php echo mysql2date( 'd F, Y h:i', $date ); ?>
					</th>
				</tr>
				<?php endif; ?>
				<tr class="message <?php echo esc_attr( $alt ); ?>" data-id="<?php echo esc_attr( $message['content_item_id'] ); ?>" data-date="<?php echo esc_attr( $date ); ?>" data-account-id="<?php echo esc_attr( $user_id ); ?>" data-post_id="<?php echo esc_attr( $post_id ); ?>" >
					<td class="account column-account">
						<?php echo esc_attr( $socialflow->accounts->get_display_name( $user_id, false ) ); ?>
					</td>
					<td class="column-status">
						<span class="status">
							<?php echo esc_attr( $message['status'] ); ?>
							<?php echo esc_attr( $queue_status ); ?>
						</span>
					</td>
					<td class="refresh-column">
						<img class="sf-message-loader" style="display:none;" src="<?php echo plugins_url( 'assets/images/wpspin.gif', SF_FILE ) ?>" alt="">
					</td>
				</tr>

			<?php $first = false; endforeach; ?>
		<?php endforeach ?>
	</tbody>
</table>
<?php endif; // we have statuses !