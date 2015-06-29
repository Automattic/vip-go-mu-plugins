<?php /*

**************************************************************************

Plugin Name:  AYS Publish
Description:  When publishing a post, a prompt will pop up to confirm that you actually meant to publish the post.

**************************************************************************

*/

add_action( 'edit_form_advanced', 'ays_publish' );
add_action( 'edit_page_form', 'ays_publish' );

function ays_publish() {
	global $post;

	if ( in_array( $post->post_status, array( 'publish', 'future' ) ) )
		return;

	?>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			$("#publish").click(function(){
				var ays = confirm("Are you sure you want to publish this post?");
				if ( ! ays ) {
					return false;
				}
			});
		});
	</script>
<?php
}

?>