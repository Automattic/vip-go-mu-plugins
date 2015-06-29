<?php
/**
 * Template for displaying post compose form
 *
 * Available variables
 *
 * @param $post             - object current post
 * @param $SocialFlow_Post  - reference to SocialFlow_Post class object
 * 
 *
 * @since 2.0
 */

$post = $data['post'];
$SocialFlow_Post = $data['SocialFlow_Post'];

?>
<form id="sf-compose-form" action="options.php">
	<?php $SocialFlow_Post->meta_box( $post ); ?>

	<input name="action" type="hidden" value="sf-compose" >
	<input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>" />

	<p><input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e( 'Send Message', 'socialflow' ) ?>" /> <img class="sf-loader" style="display:none;" src="<?php echo plugins_url( 'assets/images/wpspin.gif', SF_FILE ) ?>" alt=""></p>
	<div id="ajax-messages"></div>
</form>
<script type="text/javascript">

	var sf_post = {
		'#title': '<?php echo addslashes( $post->post_title ) ?>',
		'#content': '<?php echo trim( str_replace( array( "\r", "\n" ), "", addslashes( $post->post_content ) ) ); ?>'
	};

	jQuery.init_compose_form( true )
</script>