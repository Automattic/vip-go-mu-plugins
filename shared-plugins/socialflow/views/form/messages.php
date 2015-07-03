<?php
/**
 * Template for displaying compose form errors
 *
 * @since 2.0
 *
 * @param object WP_Error
 */
if ( is_wp_error( $errors ) ) : ?>
<div class="socialflow-messages">
	<?php foreach ( $errors->get_error_messages() as $message ): ?>
		<p class="sf-error"><?php echo esc_html( $message ); ?></p>
	<?php endforeach ?>
</div><!-- .socialflow-messages -->
<?php endif; ?>