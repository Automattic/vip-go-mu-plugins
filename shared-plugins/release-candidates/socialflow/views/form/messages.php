<?php
/**
 * Template for displaying compose form errors
 *
 * @since 2.0
 *
 * @param object WP_Error
 */
if ( is_wp_error( $data['errors'] ) ) : ?>
<div class="socialflow-messages">
	<?php foreach ( $data['errors']->get_error_messages() as $message ): ?>
		<p class="sf-error"><?php echo wp_kses_post( $message ); ?></p>
	<?php endforeach ?>
</div><!-- .socialflow-messages -->
<?php endif;