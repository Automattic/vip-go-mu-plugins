<?php include(dirname(__FILE__) . '/stylesheet.php'); ?>
<?php include(dirname(__FILE__) . '/scripts.php'); ?>

<div class="wrap" id="wp-zemanta">

    <div class="logo"><img src="<?php echo plugins_url('/img/logo.png', dirname(__FILE__)); ?>" alt="Zemanta | social blogging" /></div>

    <div class="cols clearfix">
      <div class="col-left">
        <div class="video">
         <iframe src="http://player.vimeo.com/video/46745200?title=0&amp;byline=0&amp;portrait=0" width="100%" height="280px" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>
        </div>
        <p>
          <a href="http://prefs.zemanta.com/" target="_blank" class="signin-button prefs-signin">Sign In</a>
        </p>
        <p class="below-signin-button"><a href="http://prefs.zemanta.com/" target="_blank" class="prefs-signin">to check stats, change settings and more</a></p>
      </div>
      <div class="col-right">
        <div id="tweets_div"></div>
      </div>
    </div>

  <form action="options.php" method="post" class="settings-form">
    <?php settings_fields('zemanta_options'); ?>
    <?php do_settings_sections('zemanta'); ?>
    <?php do_action('zemanta_options_form'); ?>

	<?php if(!$is_pro) : ?>
    <p class="submit">
      <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" class="button-primary" />
    </p>
	<?php endif; ?>
    
  </form>
  
</div>
