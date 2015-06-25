<?php
/**
 * Template for displaying post compose form
 *
 * @since 1.0
 *
 * @param $accounts        - array of active socialflow accounts
 * @param $post            - current post object
 * @param $SocialFlow_Post - reference to SocialFlow_Post class object
 */
?><div id="socialflow-compose" class="socialflow socialflow-compose">

	<?php $SocialFlow_Post->display_messages( $post ); ?>

	<?php if ( !empty( $accounts ) ) : ?>
		<?php $SocialFlow_Post->display_compose_form( $post, $accounts ); ?>
	<?php else : ?>
		<div class="misc-pub-section"><p><span class="sf-error"><?php _e( "You don't have any active accounts.", 'socialflow' ); ?></p></div>
	<?php endif; ?>

</div><!-- .socialflow-box -->