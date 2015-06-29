<?php
/*
Plugin Name: NBCS Moderation Queue Alerts
Description: Plugin to send emails when the comment moderation queue grows too large
Version: 1.0
Author: James Dowd
License: GPL2
*/

function nbcs_moderation_queue_alerts_check_queue() {
	$options = get_option( 'nbcs-moderation-queue' );
	$options['email'] = nbcs_moderation_queue_check_email( $options['email'] );
	
	if ( false !== get_transient( 'nbcs-moderation-queue-delay' ) || false === $options['minimum'] || false === $options['frequency'] || empty( $options['email'] ) ) {
		return; // Don't do anything if the settings have not been set
	}

	$comment_count = wp_count_comments();
	if ( $comment_count->moderated >= intval( $options['minimum'] ) ) {
		if ( intval( $options['frequency'] ) > 0 ) {
			set_transient( 'nbcs-moderation-queue-delay', true, 60 * intval( $options['frequency'] ) );
		}

		$blog_name = get_bloginfo( 'name' );
		$subject = sprintf( __( '%s Moderation Queue Alert', 'nbcs-moderation-queue' ), $blog_name );
		$message = sprintf( __( 'There are currently %d comments in the %s moderation queue.', 'nbcs-moderation-queue' ), $comment_count->moderated, $blog_name );
		if ( $options['frequency'] > 0 ) {
			$message .= sprintf( __( ' You will not receive another alert for %d minutes.', 'nbcs-moderation-queue' ), $options['frequency'] );
		}
		$message .= '</p><p><a href="' . admin_url( 'edit-comments.php' ) . '">' . __( 'Go to comments page', 'nbcs-moderation-queue' ) . '</a></p>';
		
		$headers = array( 'Content-Type: text/html' );
		
		$subject = apply_filters( 'nbcs-moderation-queue-subject', $subject, $comment_count );
		$message = apply_filters( 'nbcs-moderation-queue-message', $message, $comment_count );
		
		wp_mail( $options['email'], $subject, $message, $headers );
	}
}
add_action( 'wp_insert_comment', 'nbcs_moderation_queue_alerts_check_queue' );

function nbcs_moderation_queue_alerts_settings_api_init() {
	if ( false === get_option( 'nbcs-moderation-queue' ) ) {
		$defaults = array(
			'minimum' => 100,
			'email' => '',
			'frequency' => 15,
		);
		update_option( 'nbcs-moderation-queue', $defaults );
	}

	add_settings_field( 'nbcs-moderation-queue-minimum', 'Moderation Queue Alerts', 'nbcs_moderation_queue_minimum_settings_field', 'discussion', 'default' );
	
	register_setting( 'discussion', 'nbcs-moderation-queue', 'nbcs_moderation_queue_check_options' );
}
add_action( 'admin_init', 'nbcs_moderation_queue_alerts_settings_api_init' );

function nbcs_moderation_queue_check_options( $options ) {
	$options['minimum'] = intval( $options['minimum'] );
	$options['email'] = nbcs_moderation_queue_check_email( $options['email'] );
	$options['frequency'] = intval( $options['frequency'] );
	
	return $options;
}

function nbcs_moderation_queue_check_email( $email ) {
	$addresses = preg_split( '/[,;]/', $email );
	$final = array();
	foreach ( $addresses as $address ) {
		$address = trim( $address );
		if ( is_email( $address ) ) {
			$final[] = $address;
		}
	}
	
	return implode( ', ', $final );
}

function nbcs_moderation_queue_minimum_settings_field() {
	$options = get_option( 'nbcs-moderation-queue' );

?>
	<label for="nbcs-moderation-queue-minimum"><?php _e( 'Send an alert email if the comment moderation queue has at least this many comments in it:', 'nbcs-moderation-queue' ); ?> <input type="text" name="nbcs-moderation-queue[minimum]" id="nbcs-moderation-queue-minimum" size="2" value="<?php echo esc_attr( $options['minimum'] ); ?>" /></label>
	<br />
	<label for="nbcs-moderation-queue-email"><?php _e( 'Send moderation queue alert emails to the following email address(es):', 'nbcs-moderation-queue' ); ?> <input type="text" name="nbcs-moderation-queue[email]" id="nbcs-moderation-queue-email" size="45" value="<?php echo esc_attr( $options['email'] ); ?>" /></label>
	<br />
	<label for="nbcs-moderation-queue-frequency"><?php _e( 'Do not send another moderation queue alert email until this many minutes have passed:', 'nbcs-moderation-queue' ); ?> <input type="text" name="nbcs-moderation-queue[frequency]" id="nbcs-moderation-queue-frequency" size="2" value="<?php echo esc_attr( $options['frequency'] ); ?>" /></label>
<?php
}

?>