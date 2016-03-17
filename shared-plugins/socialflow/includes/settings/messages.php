<?php
/**
 * Holds the SocialFlow Admin Message settings class
 *
 * @package SocialFlow
 */
class SocialFlow_Admin_Settings_Messages extends SocialFlow_Admin_Settings_Page {

	/**
	 * Add actions to manipulate messages
	 */
	function __construct() {
		global $socialflow;

		// Current page slug
		$this->slug = 'messages';

		// Add current page only if application is connected to socialflow
		if ( $socialflow->is_authorized() ) {

			// Store current page object
			$socialflow->pages[ $this->slug ] = $this;

			// add menu subpage
			add_submenu_page( 
				'socialflow',
				esc_attr__( 'Messages', 'socialflow' ),
				esc_attr__( 'Messages', 'socialflow' ),
				'manage_options',
				$this->slug,
				array( $this, 'page' )
			);

			// add action to delete message
			add_action( 'admin_init' , array( $this, 'delete_message' ) );
			// add action to update queue
			add_action( 'admin_init' , array( $this, 'update_queue' ) );

			// Add notices
			add_action( 'admin_notices', array( $this, 'message_deleted' ) );
		}
	}

	/**
	 * Render admin page with user messages
	 */
	function page() {
		global $socialflow;
		// Get all user publish accounts
		$accounts = $socialflow->accounts->get( array( array( 'key' => 'service_type', 'value' => 'publishing' ) ) ); ?>

		<div class="wrap socialflow">
			<h2><?php esc_html_e( 'Messages', 'socialflow' ); ?> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->slug . '&sf_action=update_queue' ) ); ?>"><?php esc_html_e( "Update messages", 'socialflow' ) ?>.</a></h2>

			<?php if ( empty( $accounts ) ): ?>
				<p><?php esc_html_e( "You don't have any accounts on SocialFlow.", 'socialflow' ) ?></p>
			<?php return; endif ?>

			<?php foreach ( $accounts as $account ): ?>
				<?php $this->render_queue( $account ) ?>
			<?php endforeach ?>

		</div>
		<?php
	}

	/**
	 * Render account queue
	 * @param array account
	 */
	function render_queue( $account ) {
		global $socialflow;

		$queue = $this->get_queue( $account );

		if ( is_wp_error( $queue ) ) {
			echo $queue->get_error_message();
			return;
		}
		?>

		<div class="account-messages">

			<h4 class="account"><?php echo $socialflow->accounts->get_display_name( $account ); ?></h4>

			<?php if ( $queue->queue_length > 0 ): ?>
			<table cellspacing="0" class="wp-list-table widefat fixed sf-messages">
				<thead><tr>
					<th style="width:200px" class="manage-column column-username" scope="col">
						<span><?php esc_html_e('Message', 'socialflow') ?></span>
					</th>
					<th style="width:200px" class="manage-column column-account-type" scope="col">
						<span><?php esc_html_e('Publish Status', 'socialflow') ?></span>
					</th>
					<th style="width:200px" class="manage-column column-account-type" scope="col">
						
					</th>
				</tr></thead>

				<tfoot><tr>
					<th style="width:200px" class="manage-column column-username" scope="col">
						<span><?php esc_html_e('Message', 'socialflow') ?></span>
					</th>
					<th style="width:200px" class="manage-column column-account-type" scope="col">
						<span><?php esc_html_e('Publish Status', 'socialflow') ?></span>
					</th>
					<th style="width:200px" class="manage-column column-account-type" scope="col">
						
					</th>
				</tr></tfoot>

				<tbody class="list:user">
					<?php foreach ( $queue->queue as $message ) : ?>
					<tr class="alternate">
						<td class="username column-username">
							<?php echo esc_textarea( $message->content ); ?>
						</td>
						<td class="name column-account-type"><?php echo esc_attr( $message->status ); ?></td>
						<td><a class="clickable" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->slug . '&item_id=' . $message->content_item_id . '&service_user_id=' . $account['service_user_id'] . '&account_type=' . $account['account_type'] . '&sf_action=delete' ) ); ?>"><?php esc_html_e( 'delete', 'socialflow' ) ?></a></td>
					</tr>
					<?php endforeach ?>
				</tbody>
			</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No messages for this account.', 'socialflow' ) ?></p>
			<?php endif; ?>

		</div>

		<?php

	}

	/**
	 * Retrieve client account queue
	 *
	 * @param array account
	 * @return mixed ( array, WP_Error, false ) Account queue from transient. If the transient does not exist, does not have a value, or has expired, then get_queue will return false.
	 */
	function get_queue( $account ) {
		global $socialflow;

		// try to get queue from transient
		$queue = get_transient( 'sf_queue_' . $account['service_user_id'] );

		$page = 1;
		$limit = 20;

		if ( true ) {
			$api = $socialflow->get_api();

			$queue = $api->get_queue( $account['service_user_id'], $account['account_type'], 'date', $page, $limit );

			// update transient (1 hour) if we have appropriate response
			if ( !is_wp_error( $queue ) ) 
				set_transient( 'sf_queue_' . $account['service_user_id'], $queue, 60*60 );
		}

		return $queue;
	}

	/**
	 * Remove user message from queue
	 */
	function delete_message() {
		global $socialflow;
		if ( isset( $_GET[ 'sf_action' ] ) AND ( 'delete' == $_GET[ 'sf_action' ] ) AND current_user_can( 'manage_options' ) ) {
			require_once( SF_ABSPATH . '/libs/class-wp-socialflow.php' );
			$token = $socialflow->options->get('access_token');
			$api = new WP_SocialFlow( SF_KEY, SF_SECRET, $token['oauth_token'], $token['oauth_token_secret'] );

			// check for required vars and remove message from queue 
			$result = false;
			// delete_message( $content_item_id, $service_user_id, $account_type )
			if ( isset( $_GET['item_id'] ) AND isset( $_GET['service_user_id'] ) AND isset( $_GET['account_type'] ) ) {
				$result = $api->delete_message( $_GET['item_id'], $_GET['service_user_id'], $_GET['account_type'] );

				// clear service user id cache on success removal
				if ( true == $result )
					delete_transient( 'sf_queue_' . $_GET['service_user_id'] );
			}

			wp_redirect( admin_url( 'admin.php?page=' . $socialflow->pages['messages'] . '&deleted=' . ( true == $result ) ) );
			exit;
		}
	}

	/**
	 * Message deleted notice
	 */
	function message_deleted() {
		global $socialflow;

		if ( $socialflow->is_page( 'messages' ) AND isset( $_GET['deleted'] ) ) {
			$class = ( '1' == $_GET['deleted'] ) ? 'updated' : 'error';
			echo '<div id="sf-initial-nag" class="' . $class . '">';
			echo '<p>';
			if ( '1' == $_GET['deleted'] ) {
				echo '<strong>' . esc_attr__( 'NOTICE:', 'socialflow' ) . '</strong> ';
				printf( esc_attr__( 'Message was removed from queue.', 'socialflow' ) );
			} else {
				echo '<strong>' . esc_attr__( 'Error:', 'socialflow' ) . '</strong> ';
				printf( esc_attr__( 'No message were removed from queue.', 'socialflow' ) );
			}
			echo '</p></div>';
		} else {
			// We are on socialflow admin page
		}
	}

	/**
	 * Perform queue update
	 */
	function update_queue() {
		global $socialflow;
		if ( isset( $_GET[ 'sf_action' ] ) AND ( 'update_queue' == $_GET[ 'sf_action' ] ) AND current_user_can( 'manage_options' ) ) {
			
			$accounts = $socialflow->filter_accounts( array( array( 'key' => 'service_type', 'value' => 'publishing' ) ), $socialflow->options->get( 'accounts' ) );

			// delete all transients if empty update_queue
			if ( !isset( $_GET[ 'service_user_id' ] ) AND !empty( $accounts ) )
				foreach ( $accounts as $account ) {
					delete_transient( 'sf_queue_' . $account['service_user_id'] );
				}

			wp_redirect( admin_url( 'admin.php?page=' . $this->slug ) );
			exit;
		}
	}

}