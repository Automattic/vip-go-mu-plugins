<?php


$options = get_option( 'scroll_wp_options' );
$scrollkit_id = get_post_meta( get_the_ID(), '_scroll_id', true );

$nonce = wp_create_nonce( 'scrollkit-action' );

$state = get_post_meta( get_the_ID(), '_scroll_state', true );

$mobile_redirect = get_post_meta( get_the_ID(), '_scroll_mobile_redirect', true);

$deactivate_link = add_query_arg(
		array(
			'nonce' => $nonce,
			'scrollkit' => 'deactivate',
			'scrollkit_cms_id' => get_the_ID()
		), get_bloginfo('url') );

$activate_link = add_query_arg(
		array(
			'nonce' => $nonce,
			'scrollkit' => 'activate',
			'scrollkit_cms_id' => get_the_ID()
		), get_bloginfo('url') );

$delete_link = add_query_arg(
		array(
			'nonce' => $nonce,
			'scrollkit' => 'delete',
			'scrollkit_cms_id' => get_the_ID()
		), get_bloginfo('url') );

$manually_update_link = add_query_arg(
		array(
			'nonce' => $nonce,
			'scrollkit' => 'manualupdate',
			'scrollkit_cms_id' => get_the_ID(),
		), get_bloginfo('url') );

$mobile_redirect_on_link = add_query_arg(
		array(
			'nonce' => $nonce,
			'scrollkit' => 'mobileredirecton',
			'scrollkit_cms_id' => get_the_ID(),
		), get_bloginfo('url') );

$mobile_redirect_off_link = add_query_arg(
		array(
			'nonce' => $nonce,
			'scrollkit' => 'mobileredirectoff',
			'scrollkit_cms_id' => get_the_ID(),
		), get_bloginfo('url') );

$copy = array();

switch($state){
	case 'active':
		$copy['heading'] = "This post is a scroll";
		break;
	case 'inactive':
		$copy['heading'] = "This post has an inactive scroll";
		$copy['activate'] = "Activate";
		break;
	default:
		$copy['heading'] = "This post is not a scroll";
		$copy['activate'] = "Convert";
}

?>

<h4><?php echo esc_html( $copy['heading'] ) ?></h4>

<?php if (!empty($scrollkit_id)): ?>
<a href="<?php echo esc_url( ScrollKit::build_edit_url( $scrollkit_id ) ); ?>"
		target="_blank"
		class="button">
	Edit
</a>
<?php endif; ?>

<?php if( $state !== 'active' ): ?>
<a href="<?php echo esc_url( $activate_link ) ?>"
		class="button js-sk-disable-on-dirty">
	<?php echo esc_html( $copy['activate'] ) ?>
</a>

<a href="#TB_inline?height=155&width=300&inlineId=sk-load-scroll"
	class="button thickbox js-sk-disable-on-dirty">
	Use Existing Scroll
</a>
<?php else: ?>

<div class="updated">
	<p>
		This post is a scroll.
		<a href="<?php echo ScrollKit::build_edit_url( $scrollkit_id ) ?>" target="_blank">
			Edit this post with Scroll Kit
		</a>
	</p>
</div>

<?php endif ?>


<?php if ( $state === 'active' ): ?>
<a href="<?php echo esc_url( $deactivate_link )  ?>"
		title="Turn this back into a normal wordpress post"
		class="button js-sk-disable-on-dirty">
	Deactivate
</a>
<?php endif ?>

<?php if ( !empty( $state ) ): ?>
<a href="<?php echo esc_url( $delete_link ) ?>"
		onclick="return confirm('This will permanently delete the scroll associated with this post, are you sure you want to delete it?');"
		title="Permanently deletes the scroll associated with this post"
		class="button js-sk-disable-on-dirty">
	Delete
</a>
<?php endif ?>

<?php if ( $state === 'active' ): ?>
<p>
	<small>
		<a href="<?php echo esc_url( $manually_update_link ) ?>"
				title="Manually update if your server is not publically accessibly (e.g. testing)"
				class="js-sk-disable-on-dirty">
			manually update scroll
		</a>
	</small>
</p>

<?php if ( $mobile_redirect === 'on' ): ?>
<a href="<?php echo esc_url( $mobile_redirect_off_link ) ?>"
		title="On mobile browsers, render a scroll"
		class="button js-sk-disable-on-dirty">
	Don't Redirect on Mobile
</a>
<?php endif ?>
<?php if ( $mobile_redirect !== 'on' ): ?>
<a href="<?php echo esc_url( $mobile_redirect_on_link ) ?>"
		title="On mobile browsers, don't render a scroll"
		class="button js-sk-disable-on-dirty">
	Redirect on Mobile
</a>
<?php endif ?>
<?php endif ?>

<div class="js-sk-enable-on-dirty" style="visibility: hidden; color: #a00">
	<p>Save this post to activate Scroll Kit features</p>
</div>

<?php if (WP_DEBUG === true): ?>
<pre>
DEBUG
_scroll_id: <?php echo esc_attr( get_post_meta( get_the_ID(), '_scroll_id', true ) ); ?>

_scroll_state: <?php echo esc_attr( get_post_meta( get_the_ID(), '_scroll_state', true ) ); ?>
</pre>
<?php endif ?>

<script>
	(function(){
		var postStatus = "<?php echo get_post_status() ?>";

		isPostDirty = function(){
			if (postStatus === 'auto-draft')
				return true;

			var mce = typeof(tinymce) != 'undefined' ? tinymce.activeEditor : false, title, content;

			if ( mce && !mce.isHidden() ) {
				return mce.isDirty();
			} else {
				if ( fullscreen && fullscreen.settings.visible ) {
					title = jQuery('#wp-fullscreen-title').val() || '';
					content = jQuery("#wp_mce_fullscreen").val() || '';
				} else {
					title = jQuery('#post #title').val() || '';
					content = jQuery('#post #content').val() || '';
				}

				return ( ( title || content ) && title + content != autosaveLast );
			}
		}

		var disableIfDirty = function() {
			if ( isPostDirty() ) {
				jQuery('.js-sk-disable-on-dirty').addClass('button-disabled');
				jQuery('.js-sk-enable-on-dirty').css('visibility', 'visible');
			}
		}

		jQuery('#title, #content').on('keydown', disableIfDirty);

		// hook into the tiny mce iframe's iframe that lives within
		// #content_ifr
		jQuery(window).load(function() {
			jQuery('#content_ifr').contents().on('keydown', disableIfDirty);
		});

		disableIfDirty();

	})();
</script>
