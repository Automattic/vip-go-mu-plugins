<div id="shopify-settings" class="wrap">
	<div class="header">
		<h2>Shopify for WordPress</h2>
		<div id="shopify-error" class="error header-notice"></div>
		<div id="shopify-help-link">
			Need help? <a href="options.php?page=shopify_help">Visit the Shopify support page</a>
		</div>
	</div>

	<div id="shopify-store-signin" style="<?php if( $setup === "true" ) echo "display: none;" ?>" data-wordpressdomain="<?php echo esc_html( rawurlencode( admin_url() ) ) ?>">
		<img src="<?php echo esc_url( plugins_url( '../images/shopify-logo.png', __FILE__ ) ); ?>" alt="shopify logo">
		<h4>Enter your Shopify store address</h4>
		<div>
			<input class="large" type="text" name="login-shopify-url" id="login-shopify-url" placeholder="store-name" autofocus="autofocus" />
			<span class="large">.myshopify.com</span>
		</div>
		<div>
			<a href="#" onclick="Shopify.connectStore()" class="button button-primary">Connect this account</a>
		</div>
	</div>

	<div id="shopify-store-signup" style="<?php if( $setup === "true" ) echo "display: none;" ?>">
		Don't have a Shopify account?
		<?php global $current_user; get_currentuserinfo(); ?>
		<a href="<?php echo esc_url( "http://www.shopify.com/wordpress?email=" . rawurlencode( $current_user->user_email ) . "&store_name=" . rawurlencode( get_bloginfo( 'name' ) ) ); ?>" target="blank">Create your store now</a>
	</div>


	<div style="<?php if( $setup === "false" ) echo "display:none" ?>">
		<div id="shopify-getting-started" class="updated header-notice" style="display: none;">
			<h3>Let's get started!</h3>
			<p>Read our <a href='http://en.support.wordpress.com/shopify/' target="_blank">step-by-step guide</a> on how to embed your Shopify store’s products into your WordPress blog.</p>
			<p><a href="#" onclick="Shopify.hideTip()" class="button" >Dismiss</a></p>
		</div>

		<form id="shopify-settings-form" method="post" action="options.php">
			<?php settings_fields( $this->option_group ); ?>
			<?php do_settings_sections( $this->menu_slug );?>
			<?php submit_button( 'Save settings', 'primary', 'save-shopify-settings' ) ?>
		</form>

		<div id="shopify-widget-preview">
			<h3>Preview of embedded product</h3>
			<?php
				$sample_settings = array_merge( $settings, array(
					'product' => 'http://wordpress-demo.myshopify.com/products/test',
					'myshopify_domain' => 'wordpress-demo.myshopify.com',
				) );
				echo Shopify_Widget::generate( $sample_settings );
			?>
		</div>
	</div>
</div>
