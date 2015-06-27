<?php
/**
 * Template for displaying not authorized notice
 *
 * @since 2.0
 */
?>
<div id="sf-initial-nag" class="updated">
	<p><?php printf( __( '<strong>NOTICE</strong> Now that you have activated SocialFlow, please <a href="%s">visit the settings page</a> and connect to SocialFlow.', 'socialflow' ), admin_url( 'options-general.php?page=socialflow' ) ); ?></p>
	<p><?php printf( __( '<a href="%s">Take me to the settings page</a>', 'socialflow' ), admin_url( 'options-general.php?page=socialflow' ) ) ?></p>
</div>