<?php
if ( !defined( 'ABSPATH' ) )
	die( 'You cannot access this template file directly' );
?>
<noscript>JavaScript is required to load the comments.</noscript>
<div id="comments-loading" style="display:none"><?php esc_html_e( 'Loading comments...', 'ajax_comment_loading' ); ?></div>
<div id="comments-loaded"></div>
<script type="text/javascript">document.getElementById('comments-loading').style.display = 'block';</script>