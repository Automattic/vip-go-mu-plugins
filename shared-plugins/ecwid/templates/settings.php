<div class="wrap ecwid-settings <?php if ( get_option( 'ecwid_store_id' ) ) echo 'complete'; ?>">
	<h2><?php echo esc_html( $this->get_page_title() ); ?></h2>
	<form method="POST" action="options.php">
	<?php settings_fields( 'ecwid_options_page' ); ?>

	<div class="settings-block initial">
		<?php include ECWID_PLUGIN_DIR . 'templates/settings-initial.php'; ?>
	</div>

	<div class="settings-block complete">
		<?php include ECWID_PLUGIN_DIR . 'templates/settings-complete.php'; ?>
	</div>

	<hr />

	<p><?php echo sprintf( __( 'Questions? Visit <a %s>%s</a>', 'ecwid-wordpress-shortcode' ), 'target="_blank" href="http://en.support.wordpress.com/ecwid/"', 'http://en.support.wordpress.com/ecwid/' ); ?></p>
	</form>
</div>