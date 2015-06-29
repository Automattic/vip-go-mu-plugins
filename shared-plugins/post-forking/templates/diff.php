<script type="text/javascript">
( function( $ ) {

$( document ).ready( function() {
	$('#compare').mergely( {
		cmsettings: {
			readOnly: <?php echo $this->diff->user_can_merge ? 'false' : 'true'; ?>,
			lineNumbers: false,
			lineWrapping: true
		},
		lhs: function( setValue ) {
			setValue( <?php echo json_encode( wp_kses_post( $this->diff->left->post_content ) ); ?> ); 
		},
		rhs: function( setValue ) {
			setValue( <?php echo json_encode( wp_kses_post( $this->diff->right->post_content ) ); ?> ); 
		},
		ignorews: true,
		sidebar: false,
	} );
	<?php if ( $this->diff->user_can_merge ): ?>
	$( '#mergetool' ).submit( function( e ) {
		$( '#manual-post-content' ).val( $( '#compare' ).mergely( 'get', 'lhs' ) );
	} );
	<?php endif; ?>
} );

} )( jQuery );
</script>

<div class="wrap">
	<h2><?php _e( 'Compare Fork:', 'post-forking' ); ?> <?php echo esc_html( $this->diff->right->post_title ); ?></h2>
	<p><?php printf( __( 'Forked from <a href="%1$s">%2$s</a>', 'post-forking' ), admin_url( "post.php?post={$this->diff->right->post_parent}&action=edit" ), $this->get_parent_name( $this->diff->right ) ); ?></p>

	<div id="mergely-resizer">
		<div id="compare">
		</div>
	</div>
	<?php if ( $this->diff->user_can_merge ): ?>
		<div style="clear: left;">
			<form id="mergetool" method="POST">
				<input type="hidden" name="post_content" id="manual-post-content" value="" />
				<?php wp_nonce_field( 'manually_merge_diff', 'diff_merge_nonce' ); ?>
				<?php submit_button( __( 'Manually Merge Fork' ) ); ?>
			</form>
			<p><?php _e( 'Clicking "Manually Merge Fork" will save your changes above to the original post and mark the fork as merged.' ); ?></p>
		</div>
	<?php endif; ?>
</div>