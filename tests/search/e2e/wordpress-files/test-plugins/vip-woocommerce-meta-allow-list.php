<?php
// phpcs:ignoreFile
/**
 * Plugin Name: Enable some protected meta keys for WooCommerce shop_orders
 * Description: By default, no meta keys are configured. This plugin configures the meta keys for testing WooCommerce feature.
 * Version:     1.0.0
 * Author:      Automattic
 * License:     GPLv2 or later
 */

/**
 * Add some protected meta keys for WooCommerce shop_orders for tests
 *
 * @see https://github.com/10up/ElasticPress/blob/42da91b3a6daf687b539c4fd1283665d499b0af0/includes/classes/Feature/WooCommerce/WooCommerce.php#L54-L136
 */
add_filter( 'vip_search_post_meta_allow_list', 'vip_woo_meta_allow_list', 10, 2 );
function vip_woo_meta_allow_list( $allow, $post = null  ) {
	if ( is_object( $post ) && 'shop_order' === $post->post_type ) {
		$allow['_customer_user'] = true;
		$allow['_billing_first_name'] = true;
		$allow['_billing_last_name'] = true;
		$allow['_billing_address_1'] = true;
		$allow['_billing_address_2'] = true;
		$allow['_shipping_first_name'] = true;
		$allow['_shipping_last_name'] = true;
		$allow['_shipping_address_1'] = true;
		$allow['_shipping_address_2'] = true;
	}

	return $allow;
}
