<?php
// Custom IntenseDebate Comments template.
// Loads comments from the WordPress database using your own comments template,
// loads IntenseDebate UI for users with Javascript enabled.

if ( 0 == get_option( 'id_revertMobile' ) && id_is_mobile() ) :
	// Display the comments template from the active theme
	id_get_original_comment_template();
else :
?>
	<div id='idc-container'></div>
	<div id="idc-noscript">
		<?php id_get_original_comment_template(); ?>
	</div>
	<script type="text/javascript">
	/* <![CDATA[ */
	function IDC_revert() { document.getElementById('idc-loading-comments').style.display='none'; if ( !document.getElementById('IDCommentsHead') ) { document.getElementById('idc-noscript').style.display='block'; document.getElementById('idc-comment-wrap-js').parentNode.removeChild(document.getElementById('idc-comment-wrap-js')); } else { document.getElementById('idc-noscript').style.display='none'; } }
	idc_ns = document.getElementById('idc-noscript');
	idc_ns.style.display='none'; idc_ld = document.createElement('div');
	idc_ld.id = 'idc-loading-comments'; idc_ld.style.verticalAlign='middle';
	idc_ld.innerHTML = "<img src='<?php echo plugins_url( 'loading.gif', __FILE__ ); ?>' alt='Loading' border='0' align='absmiddle' /> <?php _e( 'Loading IntenseDebate Comments...', 'intensedebate' ); ?>";
	idc_ns.parentNode.insertBefore(idc_ld, idc_ns);
	setTimeout( IDC_revert, 10000 );
	/* ]]> */
	</script>
<?php
// Queue up the comment UI to load now that everything else is in place
id_postload_js( ID_BASEURL . '/js/wordpressTemplateCommentWrapper2.php?acct=' . get_option( 'id_blogAcct' ) . '&postid=' . $post->ID . '&title=' . urlencode( $post->post_title ) . '&url=' . urlencode( get_permalink( $post->ID ) ) . '&posttime=' . urlencode( $post->post_date_gmt ) . '&postauthor=' . urlencode( get_the_author_meta( 'display_name' ) ) . '&guid=' . urlencode( $post->guid ), 'idc-comment-wrap-js' );

endif; // revertMobile
