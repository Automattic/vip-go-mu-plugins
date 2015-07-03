<div class="fork-actions">
    <a href="<?php echo wp_nonce_url( admin_url( "?fork={$post->ID}" ), 'post-forking-fork_' . $post->ID ); ?>" class="button button-primary create-new-fork-button"><?php _e( 'Create New Branch', 'post-forking' ); ?></a>
    <div class="clear"></div>
</div>
