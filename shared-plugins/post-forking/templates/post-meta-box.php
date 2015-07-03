<?php if ( $fork = $this->user_has_fork( $post->ID ) ) { ?>
	<a href="<?php echo admin_url( "post.php?post=$fork&action=edit" ); ?>"><?php _e( 'View Fork', 'post-forking' ); ?></a>
<?php } else { ?>
	<a href="<?php echo wp_nonce_url( admin_url( "?fork={$post->ID}" ), 'post-forking-fork_' . $post->ID ); ?>" class="button button-primary"><?php _e( 'Fork', 'post-forking' ); ?></a>
<?php }
