<?php
global $dsq_response, $dsq_version;

if ( ! function_exists( 'dsq_render_single_comment' ) ) {
	function dsq_render_single_comment( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
		?>
		<li id="dsq-comment-<?php echo (int) get_comment_ID(); ?>">
			<div id="dsq-comment-header-<?php echo (int) get_comment_ID(); ?>" class="dsq-comment-header">
				<cite id="dsq-cite-<?php echo (int) get_comment_ID(); ?>">
					<?php if(comment_author_url()) : ?>
						<a id="dsq-author-user-<?php echo (int) get_comment_ID(); ?>" href="<?php echo esc_url( get_comment_author_url() ); ?>" target="_blank" rel="nofollow"><?php echo esc_html( get_comment_author() ); ?></a>
					<?php else : ?>
						<span id="dsq-author-user-<?php echo (int) get_comment_ID(); ?>"><?php echo esc_html( get_comment_author() ); ?></span>
					<?php endif; ?>
				</cite>
			</div>
			<div id="dsq-comment-body-<?php echo (int) get_comment_ID(); ?>" class="dsq-comment-body">
				<div id="dsq-comment-message-<?php echo (int) get_comment_ID(); ?>" class="dsq-comment-message"><?php wp_filter_kses(comment_text()); ?></div>
			</div>
		</li>
		<?php
	}
}

?>
<div id="disqus_thread">
	<div id="dsq-content">
		<ul id="dsq-comments">
			<?php
			wp_list_comments( array(
				'callback' => 'dsq_render_single_comment',
				'per_page' => '25',
			) );
			?>
		</ul>
		<?php paginate_comments_links(); ?>
	</div>
</div>
		
<a href="http://disqus.com" class="dsq-brlink">blog comments powered by <span class="logo-disqus">Disqus</span></a>

<script type="text/javascript" charset="utf-8">
	var disqus_url = <?php echo wp_json_encode( get_permalink() ); ?>;
	var disqus_identifier = <?php echo wp_json_encode( dsq_identifier_for_post($post) ); ?>;
	var disqus_container_id = 'disqus_thread';
	var disqus_domain = <?php echo wp_json_encode( DISQUS_DOMAIN ); ?>;
	var disqus_shortname = <?php echo wp_json_encode( strtolower(get_option('disqus_forum_url'))); ?>;
	<?php if (false && get_option('disqus_developer')): ?>
		var disqus_developer = 1;
	<?php endif; ?>
	var disqus_config = function () {
	    var config = this; // Access to the config object

	    /* 
	       All currently supported events:
	        * preData — fires just before we request for initial data
	        * preInit - fires after we get initial data but before we load any dependencies
	        * onInit  - fires when all dependencies are resolved but before dtpl template is rendered
	        * afterRender - fires when template is rendered but before we show it
	        * onReady - everything is done
	     */

		config.callbacks.preData.push(function() {
			// clear out the container (its filled for SEO/legacy purposes)
			document.getElementById(disqus_container_id).innerHTML = '';
		})
		config.callbacks.onReady.push(function() {
/*
			// sync comments in the background so we don't block the page
			var req = new XMLHttpRequest();
			req.open('GET', '?cf_action=sync_comments&post_id=<?php echo (int) $post->ID; ?>', true);
			req.send(null);
*/
		});
		
		<?php do_action('disqus_config_js'); // call action for custom Disqus config js ?>
	};
	
	var facebookXdReceiverPath = <?php echo wp_json_encode( DSQ_PLUGIN_URL . '/xd_receiver.htm' ); ?>;
</script>

<script type="text/javascript" charset="utf-8">
	var DsqLocal = {
		'trackbacks': [
<?php
	$count = 0;
	foreach ($comments as $comment) {
		$comment_type = get_comment_type();
		if ( $comment_type != 'comment' ) {
			if( $count ) { echo ','; }
?>
			{
				'author_name':	<?php echo wp_json_encode(get_comment_author() ); ?>,
				'author_url':	<?php echo wp_json_encode(get_comment_author_url() ); ?>,
				'date':			<?php echo wp_json_encode( comment_date('m/d/Y h:i A') ); ?>,
				'excerpt':		<?php echo wp_json_encode( str_replace(array("\r\n", "\n", "\r"), '<br />', get_comment_excerpt() ) ); ?>,
				'type':			<?php echo wp_json_encode( $comment_type ); ?>
			}
<?php
			$count++;
		}
	}
?>
		],
		'trackback_url': <?php echo wp_json_encode( get_trackback_url() ); ?>
	};
</script>

<script type="text/javascript" charset="utf-8">
(function() {
	var dsq = document.createElement('script'); dsq.type = 'text/javascript';
	dsq.async = true;
	dsq.src = '//' + disqus_shortname + '.' + disqus_domain + '/embed.js?pname=wordpress&pver=<?php echo rawurlencode( $dsq_version ); ?>';
	(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
})();
</script>
