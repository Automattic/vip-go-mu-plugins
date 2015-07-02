<div id="fork-info">
<p>
<?php printf( __( 'Forked from <a href="%1$s">%2$s</a>', 'post-forking' ), admin_url( "post.php?post={$post->post_parent}&action=edit" ), $this->get_parent_name( $post ) ); ?> <a href="<?php echo admin_url( "revision.php?page=fork-diff&right={$post->ID}" ); ?>"><span class="fork-compare button"><?php _e( 'Compare', 'post-forking' ); ?></span></a>
</p>
<div class="clear"></div>
</div>
<div id="major-publishing-actions">
<div id="delete-action">
<?php submit_button( __( 'Save Fork', 'post-forking' ), 'button button-large', 'save', false ); ?>
</div>

<div id="publishing-action">
<img src="<?php echo admin_url( '/images/wpspin_light.gif' ); ?>" class="ajax-loading" id="ajax-loading" alt="" style="visibility: hidden; ">
<input name="original_publish" type="hidden" id="original_publish" value="Publish">
<?php submit_button( __( 'Merge', 'post-forking' ), 'primary', 'publish', false ); ?>
</div>
<div class="clear"></div>
</div>
