<div class="greeting-box">
	<div class="image">
		<img src="<?php echo ECWID_PLUGIN_URL; ?>/images/store_inprogress.png" width="140" />
	</div>
	<div class="text">
	<h3><?php _e( 'Thank you for choosing Ecwid to build your online store', 'ecwid-wordpress-shortcode' ); ?></h3>
	<p><?php _e( 'Follow these simple steps to publish your Ecwid store on your site', 'ecwid-wordpress-shortcode' ); ?></p>
	</div>
</div>

<hr />


<h3><?php _e( 'Register at Ecwid', 'ecwid-wordpress-shortcode' ); ?></h3>
<p>
	<?php _e( 'Create a new Ecwid account which you will use to manage your store and inventory. The registration is free.', 'ecwid-wordpress-shortcode' ); ?>
</p>

<div class="buttons">
	<a class="button first" target="_blank" href="http://www.ecwid.com/wordpress-com-shopping-cart?source=wpcom">
		<?php _e( 'Create new Ecwid account', 'ecwid-wordpress-shortcode' ); ?>
	</a>
	<a class="button last" target="_blank" href="https://my.ecwid.com/cp/?source=wpcom#t1=&t2=Dashboard">
		<?php _e( 'I already have Ecwid account, sign in', 'ecwid-wordpress-shortcode' ); ?>
	</a>
</div>

<p class="note">
	<?php _e( 'You will be able to sign up through your existing Google, Facebook or PayPal profiles as well.', 'ecwid-wordpress-shortcode' ); ?>
</p>

<h3><?php _e( 'Enter your Store ID', 'ecwid-wordpress-shortcode' ); ?></h3>
<p>
	<?php echo sprintf(
		__( 'Store ID is a unique identifier of any Ecwid store, it consists of several digits. You can find it on the "Dashboard" page of <a %s>Ecwid control panel</a>. Also the Store ID will be sent in the Welcome email after the registration at Ecwid.', 'ecwid-wordpress-shortcode' ),
		'href="https://my.ecwid.com/cp/?source=wpcom#t1=&t2=Dashboard" target="_blank"'
	);
	?>
</p>
<div>
	<label for="ecwid_store_id">
		<?php _e('Enter your store id here:', 'ecwid-wordpress-shortcode' ); ?>
	</label>
	<input
		type="number"
		name="ecwid_store_id"
		value="<?php echo esc_attr( get_option( 'ecwid_store_id' ) ); ?>"
		class="store-id"
		placeholder="<?php esc_attr_e( 'Store ID', 'ecwid-wordpress-shortcode' ); ?>"
		/>
	<input type="submit" class="button button-primary" value="<?php esc_attr_e( __( 'Save and get a shortcode', 'ecwid-wordpress-shortcode' ) ); ?>" />
</div>